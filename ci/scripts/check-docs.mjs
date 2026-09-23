#!/usr/bin/env node

/**
 * Checks the documentation holds together. No dependencies, so it runs anywhere Node does.
 *
 *   node ci/scripts/check-docs.mjs
 *
 * Three things go wrong on their own and none of them are visible in a diff: a link to a page
 * that was renamed or never written, a link to a heading that no longer exists, and a page that
 * is never linked from the index — which, because docs/README.md is the navigation, means it
 * cannot be reached in the application at all.
 */

import { readdir, readFile } from 'node:fs/promises';
import { existsSync } from 'node:fs';
import { dirname, join, normalize, relative, resolve } from 'node:path';
import { fileURLToPath } from 'node:url';

const DOCS_DIR = resolve(dirname(fileURLToPath(import.meta.url)), '../../docs');
const INDEX = 'README.md';

/** Mirrors the anchor ids scripts/build-docs.mjs gives headings. */
function anchorFor(text) {
  return text
    .toLowerCase()
    .replace(/`/g, '')
    .replace(/[^\p{Letter}\p{Number}]+/gu, '-')
    .replace(/^-+|-+$/g, '');
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

const problems = [];

function report(file, message) {
  problems.push(`${relative(DOCS_DIR, file)}: ${message}`);
}

const files = await markdownFiles(DOCS_DIR);
const anchors = new Map();
const contents = new Map();

for (const file of files) {
  const text = await readFile(file, 'utf8');
  contents.set(file, text);
  anchors.set(
    file,
    new Set([...text.matchAll(/^#{1,6}\s+(.+)$/gm)].map(match => anchorFor(match[1].trim())))
  );
}

for (const [file, text] of contents) {
  for (const match of text.matchAll(/\[([^\]]*)\]\(([^)\s]+)\)/g)) {
    const [, label, href] = match;
    if (/^([a-z]+:|\/)/i.test(href)) {
      continue;
    }

    const [path, anchor] = href.split('#');
    const target = path ? normalize(join(dirname(file), path)) : file;

    if (path && !existsSync(target)) {
      report(file, `"${label}" points at ${href}, which does not exist`);
      continue;
    }

    if (anchor && anchors.has(target) && !anchors.get(target).has(anchor)) {
      report(file, `"${label}" points at #${anchor}, which is not a heading in ${path || 'this page'}`);
    }
  }
}

// docs/README.md is the navigation: a page it does not link to is unreachable in the app.
const indexFile = join(DOCS_DIR, INDEX);
const linked = new Set(
  [...contents.get(indexFile).matchAll(/\[[^\]]*\]\(([^)\s#]+)/g)].map(match =>
    normalize(join(DOCS_DIR, match[1]))
  )
);

for (const file of files) {
  if (file !== indexFile && !linked.has(file)) {
    report(file, 'is not linked from README.md, so it has no place in the sidebar');
  }
}

if (problems.length > 0) {
  console.error(`docs: ${problems.length} problem(s)\n`);
  for (const problem of problems) {
    console.error(`  ${problem}`);
  }
  process.exit(1);
}

console.log(`docs: ${files.length} page(s), all links resolve and every page is in the index`);
