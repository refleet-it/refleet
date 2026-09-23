/** Shape of assets/docs/manifest.json, written by scripts/build-docs.mjs. */

export interface DocsPageRef {
  readonly title: string;
  readonly slug: string;
}

export interface DocsSection {
  readonly title: string;
  readonly pages: readonly DocsPageRef[];
}

export interface DocsManifest {
  readonly generatedFrom: string;
  readonly sections: readonly DocsSection[];
  readonly pages: readonly (DocsPageRef & { readonly source: string })[];
}

export interface DocsHeading {
  readonly id: string;
  readonly text: string;
  readonly depth: number;
}

export interface DocsSearchEntry {
  readonly slug: string;
  readonly title: string;
  readonly headings: readonly DocsHeading[];
  readonly text: string;
}

export interface DocsSearchIndex {
  readonly pages: readonly DocsSearchEntry[];
}

export interface DocsSearchHit {
  readonly slug: string;
  readonly title: string;
  /** Set when the match was a heading rather than the page itself. */
  readonly heading?: DocsHeading;
  readonly excerpt: string;
}
