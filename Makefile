.PHONY: all clean init jwt-keys selfhost selfhost-account start network-heal lint quality-check lighthouse phpcsfixer phpstan rector test test-runner test-coverage secret-scan audit-backend audit-frontend audit-landing audit-mcp audit-runner trivy-scan trivy-scan-image tofu-check test-mcp build-landing xdebug-on xdebug-off exec front-exec db-recreate db-reset

# One bounded context per PostgreSQL schema and per Doctrine entity manager. Extend this
# list when a context is added; the drop/validate targets below are derived from it.
CONTEXTS := identity file notification organization project runner qualification shift playbook

# psql runs inside the database container using its own credentials, so the targets keep
# working whatever POSTGRES_USER/POSTGRES_DB compose is configured with. The query is piped
# over stdin rather than passed to -c so it can safely contain single quotes (e.g. string
# literals like 'idle in transaction') without breaking the surrounding shell quoting.
PSQL = printf '%s' "$(1)" | docker compose exec -T database sh -c 'exec psql -v ON_ERROR_STOP=1 -U "$$POSTGRES_USER" -d "$$POSTGRES_DB"'

all: init

clean:
	@echo "🧹 Cleaning up Docker resources..."
	docker compose down --volumes --remove-orphans
	@echo "✅ Cleanup completed!"

init:
	@$(MAKE) --no-print-directory jwt-keys
	@echo "🔧 Building and starting Docker containers..."
	docker compose up -d database
	@$(MAKE) --no-print-directory network-heal
	docker compose up --build -d
	@echo "📦 Installing Composer dependencies..."
	@docker compose exec backend composer install --quiet
	@echo "🗄️ Running database migrations..."
	@$(MAKE) --no-print-directory db-reset
	@docker compose exec backend php bin/console doctrine:migrations:migrate --no-interaction --quiet
	@echo "🌱 Loading fixtures..."
	@docker compose exec backend php bin/console doctrine:fixtures:load --no-interaction --append --quiet || true
	@echo "🔥 Warming up test cache for PHPStan..."
	@docker compose exec backend php bin/console cache:warmup --env=test --quiet
	@echo "✅ Initialization completed successfully!"

db-reset: ## Drop every context schema (migrations recreate them)
	@$(call PSQL,SELECT pg_terminate_backend(pid) FROM pg_stat_activity WHERE datname = current_database() AND state = 'idle in transaction' AND pid != pg_backend_pid();) > /dev/null
	@$(call PSQL,$(foreach ctx,$(CONTEXTS),DROP SCHEMA IF EXISTS $(ctx) CASCADE;) DROP TABLE IF EXISTS public.doctrine_migration_versions CASCADE; DROP TABLE IF EXISTS public.messenger_messages CASCADE;) > /dev/null

db-recreate: ## Recreate database with migrations and fixtures
	@echo "🗄️ Dropping database schema..."
	@$(MAKE) --no-print-directory db-reset
	@echo "🗄️ Running database migrations..."
	@docker compose exec backend php bin/console doctrine:migrations:migrate --no-interaction --quiet
	@echo "🌱 Loading fixtures..."
	@docker compose exec backend php bin/console doctrine:fixtures:load --no-interaction --append --quiet || true
	@echo "✅ Database recreated successfully!"

# --- Self-hosting -------------------------------------------------------------------------
#
# Everything above drives the development stack (compose.yml + compose.override.yml, sources
# bind-mounted, one container per bounded context). The two targets here drive
# compose.selfhost.yml instead: published images, a single backend on APP_ID=monolith, and a
# Caddy that obtains its own certificate. See docs/self-hosting.md.

SELFHOST = docker compose -f compose.selfhost.yml

selfhost: ## Bring up a self-hosted instance, generating any secrets it still needs
	@[ -f .env ] || { echo "❌ No .env — run: cp .env.selfhost.dist .env"; exit 1; }
	@./ci/scripts/selfhost-secrets.sh .env
	@echo "📦 Pulling images..."
	@$(SELFHOST) pull --quiet
	@echo "🚀 Starting (migrations run first, then the rest)..."
	@$(SELFHOST) up -d --wait
	@echo ""
	@echo "✅ Refleet is up. Create the first account with:"
	@echo "     make selfhost-account EMAIL=you@example.com"

selfhost-account: ## Create an account on a running self-hosted instance (EMAIL=..., ADMIN=1 for administrator)
	@[ -n "$(EMAIL)" ] || { echo "❌ Usage: make selfhost-account EMAIL=you@example.com [ADMIN=1]"; exit 1; }
	@$(SELFHOST) exec backend php bin/console refleet:create-account --email="$(EMAIL)" $(if $(ADMIN),--admin,)

start:
	@echo "🚀 Starting Docker containers..."
	docker compose up -d database
	@$(MAKE) --no-print-directory network-heal
	docker compose up -d --remove-orphans

# compose.yml refuses to start without JWT_PUBLIC_KEY/JWT_PRIVATE_KEY, and the templates ship
# them empty on purpose — no private key lives in the repository. Fill the gitignored copies
# with one generated pair (identity signs with it, every context verifies with it), once.
jwt-keys: ## Generate the local JWT keypair into .env and backend/.env if they do not have one yet
	@for f in .env backend/.env; do \
		[ -f "$$f" ] || continue; \
		if grep -qE '^JWT_PRIVATE_KEY=.+' "$$f"; then continue; fi; \
		[ -n "$$pair" ] || pair=$$(./ci/scripts/jwt-keypair.sh); \
		priv=$$(printf '%s\n' "$$pair" | sed -n 's/^JWT_PRIVATE_KEY=//p'); \
		pub=$$(printf '%s\n' "$$pair" | sed -n 's/^JWT_PUBLIC_KEY=//p'); \
		sed -i.bak -e "s|^JWT_PRIVATE_KEY=.*|JWT_PRIVATE_KEY=$$priv|" -e "s|^JWT_PUBLIC_KEY=.*|JWT_PUBLIC_KEY=$$pub|" "$$f" && rm -f "$$f.bak"; \
		echo "🔑 Wrote a JWT keypair into $$f"; \
	done

# `database` has restart:always and rarely gets recreated (its image is a pinned public
# tag), so it's the container most likely to survive a Docker Desktop / host restart across
# a network-app recreation — Docker then reports it "healthy" via its own in-container
# pg_isready check while it's silently unreachable by DNS from every other container. Run
# this before anything depends_on's database so that failure never gets a chance to happen.
network-heal:
	@for cid in $$(docker compose ps -q); do \
		net=$$(docker inspect --format '{{.HostConfig.NetworkMode}}' "$$cid"); \
		case "$$net" in default|host|none|container:*) continue ;; esac; \
		attached=$$(docker inspect --format '{{json .NetworkSettings.Networks}}' "$$cid"); \
		if [ "$$attached" = "{}" ]; then \
			name=$$(docker inspect --format '{{.Name}}' "$$cid" | sed 's#^/##'); \
			svc=$$(docker inspect --format '{{index .Config.Labels "com.docker.compose.service"}}' "$$cid"); \
			echo "⚠️  $$name lost its attachment to $$net — reconnecting..."; \
			docker network connect "$$net" "$$cid" --alias "$$svc"; \
		fi; \
	done

lint:
	@echo "📝 front lint"
	 docker compose exec frontend ng lint --fix

lighthouse: ## Run Lighthouse against a fresh production build (no dev server involved)
	@echo "📦 Building the frontend for production..."
	docker compose exec frontend npm run build
	@echo "🔦 Running Lighthouse performance audit..."
	@cd frontend && npx lhci autorun
	@echo "✅ Lighthouse audit completed! Reports are in frontend/.lighthouseci/"

# phpmd 2.15 / pdepend 2.16 are the latest stable releases and still trip PHP 8.5 deprecations
# in their own code; those are noise about the tool, not findings about ours.
PHPMD = php -d error_reporting=E_ALL^E_DEPRECATED vendor/bin/phpmd

quality-check: ## Run all quality tools (Security: secret scan + dependency audits + Trivy + tofu check | Backend: PHP CS Fixer + PHPStan + Rector + Tests | Frontend: Prettier + ESLint + TypeScript + Build | Runner/MCP: test suites)
	@echo "🔍 Running comprehensive quality checks..."
	@echo ""
	@echo "=== Security Checks ==="
	@$(MAKE) --no-print-directory secret-scan
	@$(MAKE) --no-print-directory audit-backend
	@$(MAKE) --no-print-directory audit-frontend
	@$(MAKE) --no-print-directory audit-landing
	@$(MAKE) --no-print-directory audit-mcp
	@$(MAKE) --no-print-directory audit-runner
	@$(MAKE) --no-print-directory trivy-scan
	@$(MAKE) --no-print-directory tofu-check
	@echo ""
	@echo "=== Backend Quality Checks ==="
	@echo "1️⃣ Running PHP CS Fixer..."
	docker compose exec -w /app backend vendor/bin/php-cs-fixer fix --allow-risky=yes --config=tools/php-cs-fixer/.php-cs-fixer.dist.php
	@echo "2️⃣ Running Rector refactoring..."
	docker compose exec -w /app backend vendor/bin/rector process --config=tools/rector/rector.dist.php
	@echo "3️⃣ Running PHPStan analysis..."
	docker compose exec -w /app backend php -d memory_limit=1G vendor/bin/phpstan analyse --configuration=tools/phpstan/phpstan.dist.neon
	@echo "4️⃣ Running Arkitect (phparkitect) for src..."
	docker compose exec -w /app backend ./vendor/bin/phparkitect check --config=tools/phparkitect/phparkitect-src.php
	@echo "5️⃣ Running Arkitect (phparkitect) for tests..."
	docker compose exec -w /app backend ./vendor/bin/phparkitect check --config=tools/phparkitect/phparkitect-tests.php
	@echo "6️⃣ Running Arkitect for DTO location rules..."
	docker compose exec -w /app backend ./vendor/bin/phparkitect check --config=tools/phparkitect/phparkitect-dto-location.php
	@echo "7️⃣ Running PHPMD..."
	docker compose exec -w /app backend $(PHPMD) src,app text tools/phpmd/phpmd.xml --exclude "*/tests/*"
	@echo "8️⃣ Checking for missing migrations (all entity managers)..."
	@configured=$$(docker compose exec -T -w /app backend php bin/console debug:container --parameter=doctrine.entity_managers --format=json \
		| sed -n 's/.*"\([a-z]*\)": "doctrine\.orm\..*/\1/p' | sort | tr '\n' ' '); \
	expected=$$(echo $(CONTEXTS) | tr ' ' '\n' | sort | tr '\n' ' '); \
	[ "$$configured" = "$$expected" ] || { echo "❌ CONTEXTS is [$$expected] but Doctrine is configured with [$$configured] — update the Makefile"; exit 1; }
	@for em in $(CONTEXTS); do \
		docker compose exec -w /app backend php bin/console doctrine:schema:validate --em=$$em || exit 1; \
	done
	@echo "9️⃣ Running PHPUnit tests..."
	docker compose exec -e XDEBUG_MODE=off -w /app backend vendor/bin/phpunit --configuration=phpunit.xml.dist --no-coverage
	# A silently-dropped optional dependency (symfony/asset, needed only by NelmioApiDocBundle's
	# Swagger UI controller — see composer.json's `suggest`) took four days and sixteen green
	# quality-check runs to surface, because nothing here made a live request against the app.
	# This is the cheapest thing that would have caught it: a real request, a real response body.
	@echo "🩺 Checking that /api/doc serves the Swagger UI..."
	docker compose exec -T backend curl -fsS http://localhost/api/doc | grep -q "swagger-ui"
	@echo "🔗 Checking the documentation..."
	docker run --rm -v "$(PWD):/repo" -w /repo node:22-alpine node ci/scripts/check-docs.mjs
	@echo ""
	@echo "=== Frontend Quality Checks ==="
	@echo "🔟 Running Prettier format check..."
	docker compose exec frontend npm run format:check
	@echo "1️⃣1️⃣ Running ESLint..."
	docker compose exec frontend npm run lint
	@echo "1️⃣2️⃣ Running TypeScript type check..."
	docker compose exec frontend npm run type-check
	@echo "1️⃣3️⃣ Running frontend unit tests..."
	docker compose exec frontend npm run test:ci
	@echo "1️⃣4️⃣ Building frontend application..."
	docker compose exec frontend npm run build
	@echo ""
	@echo "=== Runner Quality Checks ==="
	@echo "1️⃣5️⃣ Running runner agent tests..."
	@$(MAKE) --no-print-directory test-runner
	@echo "1️⃣6️⃣ Testing the MCP server..."
	@$(MAKE) --no-print-directory test-mcp
	@echo "1️⃣7️⃣ Building the marketing site..."
	@$(MAKE) --no-print-directory build-landing
	@echo ""
	@echo "✅ All quality checks completed successfully!"

exec:
	@echo "📝 exec"
	 docker compose exec backend sh

front-exec:
	@echo "📝 front exec"
	 docker compose exec frontend sh

phpcsfixer:
	docker compose exec -w /app backend vendor/bin/php-cs-fixer fix --allow-risky=yes --config=tools/php-cs-fixer/.php-cs-fixer.dist.php

phpstan:
	docker compose exec -w /app backend php -d memory_limit=1G vendor/bin/phpstan analyse --configuration=tools/phpstan/phpstan.dist.neon

rector:
	docker compose exec -w /app backend vendor/bin/rector process --config=tools/rector/rector.dist.php

test: ## Run PHPUnit tests
	@echo "🧪 Running PHPUnit tests..."
	docker compose exec -e XDEBUG_MODE=off -w /app backend vendor/bin/phpunit --configuration=phpunit.xml.dist --no-coverage

# Mirrors security:frontend:audit:dev-tree, the wider of the two CI audits: the whole tree, failing
# from high upwards. The dev tree used to be exempt while the Lighthouse and Puppeteer chains
# carried advisories; the lighthouse override in package.json cleared those, so it gates like the
# other three. Mounted read-only so an audit can never rewrite the lock file, and run in a clean
# image rather than the frontend container, whose node_modules is a volume that npm has damaged
# here before.
audit-frontend: ## Check frontend dependencies against security advisories
	@echo "🛡️  Auditing frontend dependencies..."
	docker run --rm -v "$(PWD)/frontend:/app:ro" -w /app node:22-alpine \
		npm audit --audit-level=high

# Reads the committed lock file rather than what happens to be installed, so the answer matches
# what CI and production would resolve. Abandoned packages stay advisory here, as they are in
# security:backend:audit — only a real advisory fails the build.
audit-backend: ## Check backend dependencies against security advisories
	@echo "🛡️  Auditing backend dependencies..."
	docker compose exec -T -w /app backend composer audit --locked

# Scans history, the same as security:gitleaks in CI, because that is the part that cannot be
# taken back: a secret reaches main and rewriting history is off the table. A working-tree scan
# (--no-git) is deliberately not used here — it ignores .gitignore, so it reports the local
# infra/terraform/environments/*/.age-key.txt files on every run, and walks node_modules and
# vendor besides, which takes 44s against 2s for this.
secret-scan: ## Scan the git history for committed secrets
	@echo "🔐 Scanning history for committed secrets..."
	docker run --rm -v "$(PWD):/repo" -w /repo zricethezav/gitleaks:v8.22.1 \
		detect --source . --config .gitleaks.toml --no-banner

audit-landing: ## Check marketing site dependencies against security advisories
	@if [ ! -d landing ]; then echo "⏭️  No landing/ here — skipping."; exit 0; fi; \
	echo "🛡️  Auditing marketing site dependencies..."; \
	docker run --rm -v "$(PWD)/landing:/app:ro" -w /app node:22-alpine \
		npm audit --audit-level=high

audit-mcp: ## Check MCP server dependencies against security advisories
	@echo "🛡️  Auditing MCP server dependencies..."
	docker run --rm -v "$(PWD)/mcp-server:/app:ro" -w /app node:22-alpine \
		npm audit --audit-level=high

audit-runner: ## Check runner agent dependencies against security advisories
	@echo "🛡️  Auditing runner agent dependencies..."
	docker run --rm -v "$(PWD)/runner/agent:/app:ro" -w /app node:22-alpine \
		npm audit --audit-level=high

# Mirrors security:trivy:filesystem — HIGH and CRITICAL, exit-code 1. runner/workspace is
# excluded: it is a customer's cloned repository cached at runtime, not something we ship, and
# left in, 7 of 9 findings on the first run here were in someone else's dependencies. So is
# backend/var: Symfony's cache and profiler dumps, which on a machine that has run the suite a
# few times is tens of thousands of files and turned a 45s scan into a 32-minute one. A named
# volume keeps the CVE database between runs — 3m17s cold against 25s warm.
trivy-scan: ## Scan for HIGH and CRITICAL vulnerabilities in project dependencies
	@echo "🔍 Scanning for HIGH and CRITICAL vulnerabilities..."
	docker run --rm \
		-v "$(PWD):/repo:ro" \
		-v refleet-trivy-cache:/trivy-cache \
		-w /repo aquasec/trivy:0.58.0 \
		fs --exit-code 1 --severity HIGH,CRITICAL --scanners vuln \
		--skip-dirs runner/workspace --skip-dirs backend/var \
		--cache-dir /trivy-cache --db-repository ghcr.io/aquasecurity/trivy-db,mirror.gcr.io/aquasec/trivy-db .

# Mirrors security:trivy:image: the prod target of backend/Dockerfile, CRITICAL, with the same
# ignore file. Not part of quality-check — it builds the image first, which is minutes, and the
# findings it can raise are the base image's, not this commit's. Run it when bumping
# dunglas/frankenphp or editing .trivyignore.yaml, and prune entries whose finding is gone.
trivy-scan-image: ## Build the prod backend image and scan it for CRITICAL vulnerabilities
	@echo "🔍 Building the prod backend image..."
	docker build -f backend/Dockerfile --target prod -t refleet-backend-prod-local .
	@echo "🔍 Scanning the image for CRITICAL vulnerabilities..."
	docker run --rm \
		-v /var/run/docker.sock:/var/run/docker.sock \
		-v "$(PWD)/.trivyignore.yaml:/ignore.yaml:ro" \
		-v refleet-trivy-cache:/trivy-cache \
		aquasec/trivy:0.58.0 \
		image --exit-code 1 --severity CRITICAL --ignorefile /ignore.yaml \
		--cache-dir /trivy-cache --db-repository ghcr.io/aquasecurity/trivy-db,mirror.gcr.io/aquasec/trivy-db \
		refleet-backend-prod-local

# tofu:plan:dev/prod in CI run fmt -check, validate, then a real plan — but plan needs Hetzner
# credentials and talks to GitLab-managed remote state, neither of which belongs in a local gate.
# fmt and validate need neither: -backend=false skips remote state entirely, and validate only
# checks internal consistency, not real infrastructure, so dummy variable values are fine. infra/
# is mounted read-write so that .terraform/ (gitignored) persists as an ordinary local cache of the
# provider plugins instead of needing a named volume.
tofu-check: ## Check Terraform formatting and validity (no plan, no credentials, no state)
	@if [ ! -d infra/terraform ]; then echo "⏭️  No infra/terraform here — skipping."; exit 0; fi; \
	echo "🏗️  Checking Terraform formatting and validity..."; \
	docker run --rm --entrypoint sh \
		-v "$(PWD)/infra:/infra" \
		-e TF_VAR_hcloud_token=unused-for-validate \
		ghcr.io/opentofu/opentofu:1.8 -c '\
			set -e; \
			cd /infra/terraform && tofu fmt -check -recursive .; \
			for env in dev prod; do \
				cd /infra/terraform/environments/$$env; \
				tofu init -backend=false -input=false > /dev/null; \
				tofu validate; \
			done'

# mcp-server has no container of its own and no CI job mentions it, so nothing was checking it at
# all. `npm test` builds (which type-checks) and then runs the node:test suite against dist/, same
# pattern as test-runner. Same borrowed node image and cache volume.
build-landing: ## Format-check and build the marketing site (landing/)
	@if [ ! -d landing ]; then echo "⏭️  No landing/ here — skipping."; exit 0; fi; \
	echo "🏗️  Building the marketing site..."; \
	docker run --rm -v "$(PWD)/landing:/app" -w /app node:22-alpine \
		sh -c "npm ci --no-audit --silent && npm run format:check && npm run build"

test-mcp: ## Build, type-check and test the MCP server
	@echo "🧪 Testing the MCP server..."
	docker run --rm \
		-v "$(PWD)/mcp-server:/app" \
		-v refleet-mcp-npm:/root/.npm \
		-w /app node:22-alpine \
		sh -c "npm ci --prefer-offline --no-audit --silent && npm test"

# The runner image ships without a build toolchain, so this borrows a plain node image rather
# than the running container. The named volume keeps npm's cache between runs, which is what
# makes it a couple of seconds instead of a minute.
test-runner: ## Run the runner agent test suite
	@echo "🧪 Running runner agent tests..."
	docker run --rm \
		-v "$(PWD)/runner:/runner" \
		-v refleet-runner-npm:/root/.npm \
		-w /runner/agent node:22-alpine \
		sh -c "npm ci --prefer-offline --no-audit --silent && npm test"

test-coverage: ## Run PHPUnit tests with coverage report
	@echo "🧪 Running PHPUnit tests with coverage..."
	docker compose exec -e XDEBUG_MODE=coverage -w /app backend vendor/bin/phpunit --configuration=phpunit.xml.dist --coverage-html=var/coverage/html --coverage-clover=var/coverage/coverage.xml

xdebug-on: ## Enable Xdebug for development
	@echo "🔧 Enabling Xdebug..."
	docker compose exec backend sh -c "echo 'xdebug.mode=coverage,debug' > /usr/local/etc/php/conf.d/docker-php-ext-xdebug.ini"
	docker compose exec backend sh -c "echo 'xdebug.start_with_request=yes' >> /usr/local/etc/php/conf.d/docker-php-ext-xdebug.ini"
	docker compose exec backend sh -c "echo 'xdebug.client_host=host.docker.internal' >> /usr/local/etc/php/conf.d/docker-php-ext-xdebug.ini"
	docker compose exec backend sh -c "echo 'xdebug.client_port=9003' >> /usr/local/etc/php/conf.d/docker-php-ext-xdebug.ini"
	docker compose exec backend sh -c "echo 'xdebug.discover_client_host=1' >> /usr/local/etc/php/conf.d/docker-php-ext-xdebug.ini"
	docker compose exec backend sh -c "echo 'xdebug.log=/var/log/xdebug.log' >> /usr/local/etc/php/conf.d/docker-php-ext-xdebug.ini"
	@echo "✅ Xdebug enabled! Restart your IDE debugger."

xdebug-off: ## Disable Xdebug for better performance
	@echo "🔧 Disabling Xdebug..."
	docker compose exec backend sh -c "echo 'xdebug.mode=off' > /usr/local/etc/php/conf.d/docker-php-ext-xdebug.ini"
	@echo "✅ Xdebug disabled! Performance improved."
