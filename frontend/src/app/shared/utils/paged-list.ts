import {
  computed,
  DestroyRef,
  effect,
  inject,
  Signal,
  signal,
  WritableSignal,
} from '@angular/core';
import { takeUntilDestroyed } from '@angular/core/rxjs-interop';
import { Observable } from 'rxjs';
import { Page, PageInfo } from '../../core/models/pagination.model';

export interface PagedList<T> {
  readonly items: Signal<T[]>;
  readonly pagination: Signal<PageInfo | null>;
  /** True while any fetch (initial or refetch) is in flight. */
  readonly isLoading: Signal<boolean>;
  /** True only until the first fetch resolves; stays false for later refetches. */
  readonly isInitialLoading: Signal<boolean>;
  readonly error: Signal<boolean>;
  readonly page: WritableSignal<number>;
  readonly pageSize: WritableSignal<number>;
  refresh(): void;
}

export interface PagedListOptions {
  initialPageSize?: number;
}

/**
 * Wires a page/pageSize pair of signals to a paginated HTTP fetch, exposing the loading
 * state and result needed by `<hlm-numbered-pagination>`. Must be called from an
 * injection context (e.g. a component field initializer), like `inject()`.
 */
export function injectPagedList<T>(
  fetchPage: (page: number, limit: number) => Observable<Page<T>>,
  options: PagedListOptions = {}
): PagedList<T> {
  const destroyRef = inject(DestroyRef);

  const items = signal<T[]>([]);
  const pagination = signal<PageInfo | null>(null);
  const isLoading = signal(true);
  const hasLoaded = signal(false);
  const isInitialLoading = computed(() => isLoading() && !hasLoaded());
  const error = signal(false);
  const page = signal(1);
  const pageSize = signal(options.initialPageSize ?? 20);

  let requestId = 0;

  function load(): void {
    const requestedId = ++requestId;
    isLoading.set(true);
    error.set(false);

    fetchPage(page(), pageSize())
      .pipe(takeUntilDestroyed(destroyRef))
      .subscribe({
        next: result => {
          if (requestedId !== requestId) {
            return;
          }
          items.set(result.items);
          pagination.set(result.pagination);
          isLoading.set(false);
          hasLoaded.set(true);
        },
        error: () => {
          if (requestedId !== requestId) {
            return;
          }
          isLoading.set(false);
          hasLoaded.set(true);
          error.set(true);
        },
      });
  }

  effect(() => {
    page();
    pageSize();
    load();
  });

  return {
    items,
    pagination,
    isLoading,
    isInitialLoading,
    error,
    page,
    pageSize,
    refresh: load,
  };
}
