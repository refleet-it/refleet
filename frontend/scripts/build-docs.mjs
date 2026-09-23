#!/usr/bin/env node

/**
 * Renders docs/ into assets the application serves at /docs.
 *
 * The contract these files follow is written down in docs/README.md, and this script is the
 * other half of it:
 *   - a page's title is its opening `#` heading, so there is no front matter to strip;
 *   - links between pages point at the .md file, which GitLab follows directly, and are
 *     rewritten here to /docs routes;
 *   - docs/README.md is the navigation — a page reaches the sidebar by being linked there.
 */

import { existsSync, watch } from 'node:fs';
import { readdir, readFile, writeFile, mkdir, rm } from 'node:fs/promises';
import { dirname, join, relative, posix } from 'node:path';
import { fileURLToPath } from 'node:url';
import { marked } from 'marked';

const __dirname = dirname(fileURLToPath(import.meta.url));
const DOCS_DIR = join(__dirname, '../../docs');
const OUT_DIR = join(__dirname, '../src/assets/docs');
const PRERENDER_ROUTES_FILE = join(__dirname, '../prerender-routes.txt');
const INDEX_FILE = 'README.md';

/**
 * docs/runner/installation.md -> runner/installation
 * docs/getting-started/README.md -> getting-started  (a directory's README is that directory,
 *   which is also how GitLab presents it when browsing)
 * docs/README.md -> index
 */
function slugFor(relativePath) {
  const withoutExtension = relativePath.replace(/\.md$/, '');

  if ('README' === withoutExtension) {
    return 'index';
  }

  return withoutExtension.replace(/\/README$/, '');
}

async function markdownFiles(dir) {
  const found = [];
  for (const entry of await readdir(dir, { withFileTypes: true })) {
    const full = join(dir, entry.name);
    if (entry.isDirectory()) {
      found.push(...(await markdownFiles(full)));
    } else if (entry.name.endsWith('.md')) {
      found.push(full);
    }
  }
  return found;
}

/** The opening `#` heading, which doubles as the page title. */
function titleOf(markdown, fallback) {
  const heading = markdown.match(/^#\s+(.+)$/m);
  return heading ? heading[1].trim() : fallback;
}

/** Heading text to an id a URL fragment can address. */
function anchorFor(text) {
  return text
    .toLowerCase()
    .replace(/<[^>]+>/g, '')
    .replace(/[^\p{Letter}\p{Number}]+/gu, '-')
    .replace(/^-+|-+$/g, '');
}

/** Markup out, readable words in — what the search index actually matches against. */
function plainText(html) {
  return html
    .replace(/<pre[\s\S]*?<\/pre>/g, ' ')
    .replace(/<[^>]+>/g, ' ')
    .replace(/&[a-z]+;|&#\d+;/gi, ' ')
    .replace(/\s+/g, ' ')
    .trim();
}

/**
 * Turns a link as written on disk into the route it maps to. Anchors survive, absolute
 * URLs and mailto: are left alone.
 */
function rewriteLink(href, fromDir) {
  if (/^([a-z]+:|\/|#)/i.test(href)) {
    return href;
  }

  const [path, anchor] = href.split('#');
  if (!path.endsWith('.md')) {
    return href;
  }

  const target = posix.normalize(posix.join(fromDir, path));
  const route = `/docs/${slugFor(target)}`.replace(/\/index$/, '');

  return anchor ? `${route}#${anchor}` : route;
}

/**
 * Reads the navigation out of the index: `###` headings become sections, and the links
 * beneath each one become its pages, in the order they appear.
 */
function navigationFrom(indexMarkdown) {
  const sections = [];
  let current = null;

  for (const line of indexMarkdown.split('\n')) {
    const heading = line.match(/^###\s+(.+)$/);
    if (heading) {
      current = { title: heading[1].trim(), pages: [] };
      sections.push(current);
      continue;
    }

    const link = line.match(/^\s*[-*]\s+\[([^\]]+)\]\(([^)]+)\)/);
    if (link && current) {
      const target = posix.normalize(link[2].split('#')[0]);
      current.pages.push({ title: link[1].trim(), slug: slugFor(target) });
    }
  }

  return sections.filter(section => section.pages.length > 0);
}

async function main() {
  // docs/ sits at the repository root, outside this package. Every build that has the
  // repository — local development, and the CI job that builds frontend/dist — sees it.
  // The SSR image is built with frontend/ alone as its context and does not, so warn and
  // leave the build alone rather than failing it. Fixing that means widening the image's
  // build context, which is tracked separately.
  if (!existsSync(DOCS_DIR)) {
    console.warn(
      `docs: ${DOCS_DIR} is not present — skipping, /docs will have no pages in this build`
    );
    return;
  }

  await rm(OUT_DIR, { recursive: true, force: true });
  await mkdir(OUT_DIR, { recursive: true });

  const files = await markdownFiles(DOCS_DIR);
  const pages = [];
  const searchEntries = [];

  for (const file of files) {
    const relativePath = relative(DOCS_DIR, file).split(/[\\/]/).join('/');
    const slug = slugFor(relativePath);
    const markdown = await readFile(file, 'utf8');
    const fromDir = posix.dirname(relativePath);

    const renderer = new marked.Renderer();
    const linkRenderer = renderer.link.bind(renderer);
    renderer.link = token => linkRenderer({ ...token, href: rewriteLink(token.href, fromDir) });

    // Headings carry an id so a URL fragment can address them and search can link into a page.
    const headings = [];
    const headingRenderer = renderer.heading.bind(renderer);
    renderer.heading = token => {
      const id = anchorFor(token.text);
      if (token.depth > 1) {
        headings.push({ id, text: token.text, depth: token.depth });
      }

      return headingRenderer(token).replace(/^<h(\d)/, `<h$1 id="${id}"`);
    };

    const html = marked.parse(markdown, { renderer, async: false });
    const outFile = join(OUT_DIR, `${slug}.html`);

    await mkdir(dirname(outFile), { recursive: true });
    await writeFile(outFile, html, 'utf8');

    const title = titleOf(markdown, slug);
    pages.push({ slug, title, source: `docs/${relativePath}` });
    searchEntries.push({ slug, title, headings, text: plainText(html) });
  }

  const index = await readFile(join(DOCS_DIR, INDEX_FILE), 'utf8');
  const manifest = {
    generatedFrom: `docs/${INDEX_FILE}`,
    sections: navigationFrom(index),
    pages: pages.sort((a, b) => a.slug.localeCompare(b.slug)),
  };

  await writeFile(join(OUT_DIR, 'manifest.json'), `${JSON.stringify(manifest, null, 2)}\n`, 'utf8');

  // Written from the manifest rather than kept by hand: a page added to docs/ would otherwise be
  // the one page still rendered per request, and nothing would say so. `/` is absent — it
  // renders nothing now and only redirects; the public page is the separate marketing site.
  const routes = ['/docs', ...pages.filter(page => 'index' !== page.slug).map(page => `/docs/${page.slug}`)];
  await writeFile(PRERENDER_ROUTES_FILE, `${routes.join('\n')}\n`, 'utf8');

  // Searched entirely in the browser: it is a handful of pages, and shipping an index beats
  // standing up a service for it.
  const searchIndex = { pages: searchEntries.sort((a, b) => a.slug.localeCompare(b.slug)) };
  await writeFile(join(OUT_DIR, 'search-index.json'), `${JSON.stringify(searchIndex)}\n`, 'utf8');

  const linked = new Set(
    manifest.sections.flatMap(section => section.pages.map(page => page.slug))
  );
  const unlisted = pages
    .map(page => page.slug)
    .filter(slug => slug !== 'index' && !linked.has(slug));

  console.log(`docs: rendered ${pages.length} page(s) into ${relative(process.cwd(), OUT_DIR)}`);
  if (unlisted.length > 0) {
    console.warn(
      `docs: not linked from ${INDEX_FILE}, so absent from the sidebar: ${unlisted.join(', ')}`
    );
  }
}

if (process.argv.includes('--watch')) {
  await main();

  // ng serve knows nothing about docs/: it lives outside the Angular workspace, so nothing
  // rebuilds these assets when a page changes. Without this a new page is not merely stale, it is
  // absent — and the dev server answers a missing asset with index.html and a 200, which reads as
  // "the page rendered empty" rather than "the page was never generated".
  let pending;
  watch(DOCS_DIR, { recursive: true }, (_event, filename) => {
    if (filename && !filename.endsWith('.md')) {
      return;
    }

    // Editors save in bursts (write, rename, chmod); one rebuild per burst is enough.
    clearTimeout(pending);
    pending = setTimeout(() => {
      main().catch(error => console.error('docs:', error.message));
    }, 100);
  });

  console.log(`docs: watching ${relative(process.cwd(), DOCS_DIR)}`);
} else {
  await main();
}
