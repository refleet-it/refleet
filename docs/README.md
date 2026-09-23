# Refleet documentation

These pages are the source of truth for how Refleet is built, run and operated. They are plain
Markdown so they read correctly in two places: here in GitLab, and at `/docs` in the application,
which renders the same files at build time.

## Contents

### Using Refleet

- [From a GitLab group to merged merge requests](using-refleet/README.md)

### Getting started

- [Running the stack locally](getting-started/README.md)
- [Running your own instance](self-hosting.md)

### Architecture

- [How the backend is put together](architecture/README.md)
- [ADR 1 — Bounded contexts become separate Symfony applications](adr/0001-multiple-kernels.md)
- [ADR 2 — Playbooks compose prompts; the answer contract stays in the framing](adr/0002-playbooks.md)

### API

- [Authenticating and the resources](api/README.md)

### Runner

- [How a runner works](runner/how-it-works.md)
- [Installing and updating a runner](runner/installation.md)


### Contributing

- [Conventions and the quality gate](contributing/README.md)

## How these files are written

The rules exist so one set of files can serve both renderers without either needing special cases.

**Every page opens with a single `#` heading.** That heading is the page title — in GitLab because
it is simply the first heading, and in the application because the build step reads it. Nothing
carries a separate title field.

**No YAML front matter.** It would have to be stripped for one renderer or the other, and a title
that lives in two places drifts. The heading is enough.

**Links between pages are relative and point at the `.md` file**, the way
`[installing a runner](runner/installation.md)` does above. GitLab follows those directly; the
build step rewrites them to `/docs/...` routes. A link written as a route would be broken here.

**This file is the navigation.** The sidebar in the application is generated from the contents list
above, so a page appears there once it is linked here — in this order, under these headings. There
is no second place to register a page and no per-file ordering metadata to keep in sync.
