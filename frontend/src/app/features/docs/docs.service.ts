import { HttpClient } from '@angular/common/http';
import { inject, Injectable } from '@angular/core';
import { Observable, shareReplay } from 'rxjs';
import { DOCS_ASSETS_BASE } from '../../core/tokens/docs-assets-base.token';
import { DocsManifest, DocsSearchHit, DocsSearchIndex } from './docs.model';

const EXCERPT_RADIUS = 90;

/**
 * Reads what scripts/build-docs.mjs rendered out of docs/ at build time. Both the pages and
 * the navigation are static files, so the manifest is fetched once and shared.
 */
@Injectable({ providedIn: 'root' })
export class DocsService {
  private readonly http = inject(HttpClient);
  private readonly base = inject(DOCS_ASSETS_BASE);

  private readonly manifest$ = this.http
    .get<DocsManifest>(`${this.base}/manifest.json`)
    .pipe(shareReplay({ bufferSize: 1, refCount: false }));

  private readonly searchIndex$ = this.http
    .get<DocsSearchIndex>(`${this.base}/search-index.json`)
    .pipe(shareReplay({ bufferSize: 1, refCount: false }));

  manifest(): Observable<DocsManifest> {
    return this.manifest$;
  }

  /** Pre-rendered HTML for one page. The slug is a path such as `runner/installation`. */
  page(slug: string): Observable<string> {
    return this.http.get(`${this.base}/${slug}.html`, { responseType: 'text' });
  }

  titleFor(manifest: DocsManifest, slug: string): string | undefined {
    return manifest.pages.find(page => page.slug === slug)?.title;
  }

  searchIndex(): Observable<DocsSearchIndex> {
    return this.searchIndex$;
  }

  /**
   * Plain case-insensitive matching over titles, headings and body text. There are a handful
   * of pages, so ranking them is not the problem worth solving; finding them is.
   */
  search(index: DocsSearchIndex, query: string): DocsSearchHit[] {
    const needle = query.trim().toLowerCase();
    if (needle.length < 2) {
      return [];
    }

    const hits: DocsSearchHit[] = [];

    for (const page of index.pages) {
      const heading = page.headings.find(candidate =>
        candidate.text.toLowerCase().includes(needle)
      );
      const inTitle = page.title.toLowerCase().includes(needle);
      const position = page.text.toLowerCase().indexOf(needle);

      if (!inTitle && !heading && position < 0) {
        continue;
      }

      hits.push({
        slug: page.slug,
        title: page.title,
        heading,
        excerpt: this.excerpt(page.text, position),
      });
    }

    return hits;
  }

  private excerpt(text: string, position: number): string {
    if (position < 0) {
      return text.slice(0, EXCERPT_RADIUS * 2).trim();
    }

    const start = Math.max(0, position - EXCERPT_RADIUS);
    const end = Math.min(text.length, position + EXCERPT_RADIUS);

    return `${start > 0 ? '…' : ''}${text.slice(start, end).trim()}${end < text.length ? '…' : ''}`;
  }
}
