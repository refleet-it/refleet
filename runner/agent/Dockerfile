# Build from this directory: docker build .
#
# Bakes in every agent CLI this runner knows how to auto-detect (currently claude,
# kiro-cli) — `refleet run` decides at startup which of them to actually
# register as Runners based on what's really usable (see src/detect.ts).
FROM node:22-alpine

# bash and curl are only needed by the Kiro installer below; git is the one runtime
# dependency the fleet loop has (GitLab is talked to over its REST API from Node).
RUN apk add --no-cache bash git curl \
    && npm install -g @anthropic-ai/claude-code \
    # Kiro CLI ships its own installer (https://kiro.dev/docs/cli/), not an npm
    # package; it installs to ~/.local/bin by default, which is root's home during
    # this RUN step. Move it to /usr/local/bin so it stays reachable once the
    # container drops to the non-root `runner` user below (verify the install path
    # against a fresh `curl -fsSL https://cli.kiro.dev/install | bash` run if this
    # breaks — installers change).
    && curl -fsSL https://cli.kiro.dev/install | bash \
    && mv "$HOME/.local/bin/kiro-cli" /usr/local/bin/kiro-cli

RUN addgroup -S runner && adduser -S runner -G runner

# No runtime dependencies (only typescript/@types/node as devDependencies to compile),
# so node_modules is dropped entirely after tsc runs.
# CI passes the pipeline's sequence number so the image reports the same
# <major>.<minor>.<iid> version the npm package of that pipeline carries (see
# .runner-agent-version in ci/jobs/quality-runner.yml); a local build keeps the bare series.
ARG RUNNER_VERSION_PATCH=""
WORKDIR /agent
COPY package.json package-lock.json tsconfig.json ./
COPY src ./src
RUN if [ -n "$RUNNER_VERSION_PATCH" ]; then \
        SERIES=$(node -p "require('./package.json').version.split('.').slice(0, 2).join('.')") \
        && npm version "${SERIES}.${RUNNER_VERSION_PATCH}" --no-git-tag-version --allow-same-version > /dev/null; \
    fi \
    && npm ci --no-audit --no-fund && npm run build && rm -rf node_modules src

WORKDIR /workspace
RUN chown -R runner:runner /workspace /agent

USER runner

# The mounted volume; an npm install of the same package defaults to ~/.cache instead.
ENV WORKSPACE_CACHE_DIR=/workspace/cache

ENTRYPOINT ["node", "/agent/dist/cli.js"]
CMD ["run"]
