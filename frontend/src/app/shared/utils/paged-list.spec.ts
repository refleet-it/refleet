import { TestBed } from '@angular/core/testing';
import { Observable, Subject, throwError } from 'rxjs';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import { Page } from '../../core/models/pagination.model';
import { injectPagedList } from './paged-list';

const pagination = {
  page: 1,
  limit: 20,
  total: 2,
  totalPages: 1,
  hasNextPage: false,
  hasPreviousPage: false,
};

function pageOf(items: string[]): Page<string> {
  return { items, pagination };
}

/**
 * Every list screen is built on this, and the part worth pinning is the one nobody sees working:
 * a response that arrives after a newer request has already been issued has to be dropped, or the
 * screen shows the page the user has just navigated away from.
 */
describe('injectPagedList', () => {
  let fetches: Subject<Page<string>>[];
  let fetchPage: ReturnType<typeof vi.fn> &
    ((page: number, limit: number) => Observable<Page<string>>);

  beforeEach(() => {
    TestBed.configureTestingModule({});
    fetches = [];
    fetchPage = vi.fn((_page: number, _limit: number) => {
      const subject = new Subject<Page<string>>();
      fetches.push(subject);

      return subject.asObservable();
    }) as typeof fetchPage;
  });

  function create() {
    return TestBed.runInInjectionContext(() => injectPagedList<string>(fetchPage));
  }

  it('starts loading immediately and reports the first load as initial', () => {
    const list = TestBed.runInInjectionContext(() => {
      const created = injectPagedList<string>(fetchPage);
      TestBed.tick();

      return created;
    });

    expect(fetchPage).toHaveBeenCalledWith(1, 20);
    expect(list.isLoading()).toBe(true);
    expect(list.isInitialLoading()).toBe(true);

    fetches[0].next(pageOf(['a']));

    expect(list.items()).toEqual(['a']);
    expect(list.pagination()).toEqual(pagination);
    expect(list.isLoading()).toBe(false);
    expect(list.isInitialLoading()).toBe(false);
  });

  it('does not call a refetch an initial load, so the screen keeps its rows', () => {
    const list = create();
    TestBed.tick();
    fetches[0].next(pageOf(['a']));

    list.refresh();

    expect(list.isLoading()).toBe(true);
    expect(list.isInitialLoading()).toBe(false);
  });

  // The reason requestId exists: a slow first request must not overwrite the newer one's result.
  it('ignores a response that a newer request has already superseded', () => {
    const list = create();
    TestBed.tick();

    list.refresh();
    expect(fetches).toHaveLength(2);

    fetches[1].next(pageOf(['new']));
    fetches[0].next(pageOf(['stale']));

    expect(list.items()).toEqual(['new']);
  });

  it('ignores a failure that a newer request has already superseded', () => {
    const list = create();
    TestBed.tick();

    list.refresh();
    fetches[1].next(pageOf(['new']));
    fetches[0].error(new Error('slow request finally failed'));

    expect(list.error()).toBe(false);
    expect(list.items()).toEqual(['new']);
  });

  it('raises the error flag and stops loading when a request fails', () => {
    fetchPage.mockImplementationOnce(() => throwError(() => new Error('500')));
    const list = create();
    TestBed.tick();

    expect(list.error()).toBe(true);
    expect(list.isLoading()).toBe(false);
    expect(list.isInitialLoading()).toBe(false);
  });

  it('clears the error flag when a retry is issued', () => {
    fetchPage.mockImplementationOnce(() => throwError(() => new Error('500')));
    const list = create();
    TestBed.tick();
    expect(list.error()).toBe(true);

    list.refresh();

    expect(list.error()).toBe(false);
  });

  it('refetches when the page or the page size changes', () => {
    const list = create();
    TestBed.tick();
    fetches[0].next(pageOf(['a']));

    list.page.set(3);
    TestBed.tick();

    expect(fetchPage).toHaveBeenLastCalledWith(3, 20);

    list.pageSize.set(50);
    TestBed.tick();

    expect(fetchPage).toHaveBeenLastCalledWith(3, 50);
  });
});
