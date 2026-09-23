import {
  afterNextRender,
  Component,
  computed,
  effect,
  inject,
  Injector,
  PLATFORM_ID,
  signal,
} from '@angular/core';
import { CommonModule, DOCUMENT, isPlatformBrowser } from '@angular/common';
import { DomSanitizer, SafeHtml, Title } from '@angular/platform-browser';
import { ActivatedRoute, RouterLink, RouterLinkActive } from '@angular/router';
import { toSignal } from '@angular/core/rxjs-interop';
import { catchError, combineLatest, map, of, startWith, switchMap } from 'rxjs';
import { DocsService } from '../../docs.service';

type PageState =
  | { readonly status: 'loading' }
  | { readonly status: 'ready'; readonly html: SafeHtml }
  | { readonly status: 'missing' };

@Component({
  selector: 'app-docs-page',
  standalone: true,
  imports: [CommonModule, RouterLink, RouterLinkActive],
  templateUrl: './docs-page.component.html',
})
export class DocsPageComponent {
  private readonly route = inject(ActivatedRoute);
  private readonly docs = inject(DocsService);
  private readonly sanitizer = inject(DomSanitizer);
  private readonly title = inject(Title);
  private readonly document = inject(DOCUMENT);
  private readonly platformId = inject(PLATFORM_ID);
  private readonly injector = inject(Injector);

  private readonly fragment = toSignal(this.route.fragment, { initialValue: null });

  /** `/docs` renders the index; `/docs/runner/installation` renders that page. */
  private readonly slug$ = this.route.url.pipe(
    map(segments => segments.map(segment => segment.path).join('/') || 'index')
  );

  readonly slug = toSignal(this.slug$, { initialValue: 'index' });

  readonly manifest = toSignal(this.docs.manifest().pipe(catchError(() => of(undefined))), {
    initialValue: undefined,
  });

  readonly sections = computed(() => this.manifest()?.sections ?? []);

  readonly query = signal('');

  private readonly searchIndex = toSignal(
    this.docs.searchIndex().pipe(catchError(() => of(undefined))),
    { initialValue: undefined }
  );

  readonly hits = computed(() => {
    const index = this.searchIndex();

    return index ? this.docs.search(index, this.query()) : [];
  });

  readonly searching = computed(() => this.query().trim().length >= 2);

  /**
   * A slug is a path, so it has to become several route segments. Passing it whole makes
   * routerLink encode the slash and produce /docs/runner%2Finstallation.
   */
  docsLink(slug: string): string[] {
    return ['/docs', ...slug.split('/')];
  }

  skipToContent(event: Event, main: HTMLElement): void {
    event.preventDefault();
    main.focus();
  }

  /**
   * Whether a page exists is decided by the manifest, not by the response status. Asking the
   * server settles nothing: a dev server and most static hosting answer an unknown path with
   * the SPA shell and a 200, so an unknown slug would otherwise render as a blank article.
   */
  readonly page = toSignal(
    combineLatest([this.slug$, this.docs.manifest()]).pipe(
      switchMap(([slug, manifest]) => {
        if (!manifest.pages.some(page => page.slug === slug)) {
          return of<PageState>({ status: 'missing' });
        }

        return this.docs.page(slug).pipe(
          map((html): PageState => ({
            status: 'ready',
            html: this.sanitizer.bypassSecurityTrustHtml(html),
          })),
          catchError(() => of<PageState>({ status: 'missing' })),
          startWith<PageState>({ status: 'loading' })
        );
      }),
      catchError(() => of<PageState>({ status: 'missing' }))
    ),
    { initialValue: { status: 'loading' } as PageState }
  );

  /** Non-null only once the page has loaded, so the template needs no narrowing cast. */
  readonly html = computed(() => {
    const state = this.page();

    return 'ready' === state.status ? state.html : null;
  });

  constructor() {
    // The body is injected after navigation, so the router's own anchor scrolling has
    // nothing to find yet — scroll once the page it belongs to is actually on screen.
    effect(() => {
      const fragment = this.fragment();
      if ('ready' !== this.page().status || !fragment || !isPlatformBrowser(this.platformId)) {
        return;
      }

      afterNextRender(
        () => this.document.getElementById(fragment)?.scrollIntoView({ block: 'start' }),
        { injector: this.injector }
      );
    });

    effect(() => {
      const manifest = this.manifest();
      const title = manifest ? this.docs.titleFor(manifest, this.slug()) : undefined;

      this.title.setTitle(title ? `${title} — Refleet docs` : 'Refleet docs');
    });
  }
}
