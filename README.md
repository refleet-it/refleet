<p align="center">
  <img src="frontend/public/logo.svg" alt="Refleet" width="260">
</p>

<p align="center">
  Roll out the same change across every repository in your GitLab group — and review what
  came back as ordinary merge requests.
</p>

---

Refleet points coding agents at a fleet of repositories. You describe a change once; it
decides which projects it applies to, runs an agent against each of them on machines you
control, and opens a merge request per project. Nothing merges on its own — the output is a
pile of MRs with your name on the review.

It is the part nobody builds: not the agent, but everything around running one across
forty repositories without losing track of what happened where.

**Self-hosted, and that is the normal way to run it.** The agents run on your machines with
your API keys; the code never leaves your infrastructure. There is a hosted instance at
[refleet.it](https://refleet.it) if you would rather not run the server yourself.

> **GitLab only, for now.** Refleet talks to GitLab (gitlab.com or self-managed). GitHub
> support is the most obvious next step and the most welcome contribution — see
> [CONTRIBUTING.md](CONTRIBUTING.md).

## Run it

You need Docker, a hostname pointing at the machine, and an SMTP transport.

```bash
git clone https://github.com/refleet-it/refleet.git
cd refleet
cp .env.selfhost.dist .env     # fill in APP_DOMAIN and MAILER_DSN
make selfhost
make selfhost-account EMAIL=you@example.com ADMIN=1
```

That brings up the API, a queue worker, the server-rendered frontend, PostgreSQL and a Caddy
that gets its own certificate. Sign in, create your organisation, connect GitLab.

Then give it a runner — the thing that actually does the work. It only needs to reach the
API, so it belongs wherever the agent CLIs live:

```bash
npm install -g @refleet-it/runner
refleet login --api-url https://<your-domain>/api
refleet run
```

Full walkthrough: **[docs/self-hosting.md](docs/self-hosting.md)**.

## How a change happens

1. **Connect a GitLab group.** Refleet syncs its projects and keeps them in sync.
2. **Write a playbook.** Task or rule, in plain prose — it becomes part of the prompt, and
   you see the final prompt before anything runs.
3. **Qualify.** Every project gets a read-only pass first: does this change apply here at
   all? The agent answers 1–5 with its reasoning; 4 and 5 qualify.
4. **Apply.** Runners claim qualified projects one at a time, run the agent against a real
   checkout, commit, push a branch and open a merge request labelled `refleet`.
5. **Review.** You read the MRs. Running the same project again updates its MR rather than
   opening another.

Agents: **Claude Code** and **Kiro**. A runner registers one loop per agent CLI it finds, so
one host with both installed shows up as two runners and the API only offers each the jobs
it can run.

## What is in here

| | |
| --- | --- |
| `backend/` | Symfony 7.4 / PHP 8.5. Nine bounded contexts, DDD, CQRS, one PostgreSQL schema each. |
| `frontend/` | Angular 21 with real server-side rendering, Tailwind 4, spartan-ng. |
| `runner/agent/` | The runner: TypeScript, zero runtime dependencies, published as [`@refleet-it/runner`](https://www.npmjs.com/package/@refleet-it/runner). |
| `mcp-server/` | An MCP server exposing this repository's own dev tooling to an agent. |
| `docs/` | The documentation, also rendered by the application at `/docs`. |

Architecture, and why the contexts are separated the way they are:
**[docs/architecture](docs/architecture/README.md)** and the
[ADRs](docs/adr/0001-multiple-kernels.md).

## Developing

```bash
cp .env.dist .env && cp backend/.env.dist backend/.env
make init          # build, migrate, load fixtures
make quality-check # the gate: everything CI runs
```

`make quality-check` has to pass before anything is merged. It is slow on purpose — it runs
php-cs-fixer, Rector, PHPStan, PHPMD, three PHPArkitect suites, schema validation, PHPUnit,
then Prettier, ESLint, `tsc`, the frontend tests and production build, the runner's tests and
the MCP server's. Details in [CONTRIBUTING.md](CONTRIBUTING.md).

## Licence

[Refleet License](LICENSE) — the Apache License 2.0 plus conditions covering hosted
services, commercial embedding and the name. You can read it, change it, and run it for your
own organisation, including commercially. You cannot sell Refleet itself as a competing
hosted service, or ship it under its own name.

The runner is the exception: [`runner/agent/`](runner/agent/LICENSE) is MIT, with none of
those conditions attached. It runs on your machines, and forking or embedding it is meant to
be unrestricted.
