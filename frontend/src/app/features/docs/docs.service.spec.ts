import { provideHttpClient } from '@angular/common/http';
import { provideHttpClientTesting } from '@angular/common/http/testing';
import { TestBed } from '@angular/core/testing';
import { beforeEach, describe, expect, it } from 'vitest';
import { DocsSearchIndex } from './docs.model';
import { DocsService } from './docs.service';

/**
 * Documentation search fails in the one way nobody reports: it returns nothing, the page looks
 * empty, and the reader concludes the docs do not cover their question. So these cases are about
 * what the search finds, and about the excerpt that has to make the hit recognisable.
 */
const index: DocsSearchIndex = {
  pages: [
    {
      slug: 'runner/installation',
      title: 'Installing a runner',
      headings: [{ id: 'requirements', text: 'Hardware requirements', depth: 2 }],
      text:
        'Start here. '.repeat(20) +
        'The runner needs Docker to claim jobs. ' +
        'Read on. '.repeat(20),
    },
    {
      slug: 'operations/backups',
      title: 'Backups',
      headings: [],
      text:
        'Snapshots run nightly. ' +
        'Filler that pushes the page past one excerpt window. '.repeat(2) +
        'RESTORE PROCEDURE lives here. ' +
        'More filler so the page does not end early. '.repeat(3),
    },
  ],
};

describe('DocsService search', () => {
  let service: DocsService;

  beforeEach(() => {
    TestBed.configureTestingModule({
      providers: [provideHttpClient(), provideHttpClientTesting()],
    });
    service = TestBed.inject(DocsService);
  });

  // A one-character query matches nearly every page, which is indistinguishable from the search
  // being broken — better to return nothing until the reader has typed something meaningful.
  it.each(['', ' ', 'r'])('ignores the query %o as too short to mean anything', query => {
    expect(service.search(index, query)).toEqual([]);
  });

  it('finds a page by its title', () => {
    const hits = service.search(index, 'backups');

    expect(hits.map(hit => hit.slug)).toEqual(['operations/backups']);
  });

  it('finds a page by a heading, and says which heading matched', () => {
    const hits = service.search(index, 'hardware');

    expect(hits).toHaveLength(1);
    expect(hits[0].slug).toBe('runner/installation');
    expect(hits[0].heading?.text).toBe('Hardware requirements');
  });

  it('finds a page by its body text alone', () => {
    const hits = service.search(index, 'docker');

    expect(hits.map(hit => hit.slug)).toEqual(['runner/installation']);
    expect(hits[0].heading).toBeUndefined();
  });

  it('matches regardless of case', () => {
    expect(service.search(index, 'DOCKER')).toHaveLength(1);
  });

  it('returns nothing when no page mentions the query', () => {
    expect(service.search(index, 'kubernetes')).toEqual([]);
  });

  // The excerpt is the only part of the page the reader sees before clicking, so it has to contain
  // the thing they searched for rather than whatever the page happens to open with.
  it('cuts the excerpt around the match and marks both trimmed ends', () => {
    const [hit] = service.search(index, 'docker');

    expect(hit.excerpt).toContain('Docker');
    expect(hit.excerpt.startsWith('…')).toBe(true);
    expect(hit.excerpt.endsWith('…')).toBe(true);
  });

  // A title-only match has no position in the body to centre on. Without the fallback the window
  // would be computed around -1, yielding a shorter, ellipsis-suffixed slice — so the assertions
  // below are on the two things that actually differ between the branches.
  it('falls back to a wider opening of the page when only the title matched', () => {
    const [hit] = service.search(index, 'backups');

    expect(hit.excerpt.startsWith('Snapshots run nightly.')).toBe(true);
    expect(hit.excerpt).toContain('RESTORE PROCEDURE');
    expect(hit.excerpt.endsWith('…')).toBe(false);
  });

  it('names a page by its slug through the manifest', () => {
    const manifest = {
      generatedFrom: 'docs/',
      sections: [],
      pages: [{ title: 'Installing a runner', slug: 'runner/installation', source: 'x.md' }],
    };

    expect(service.titleFor(manifest, 'runner/installation')).toBe('Installing a runner');
    expect(service.titleFor(manifest, 'nope')).toBeUndefined();
  });
});
