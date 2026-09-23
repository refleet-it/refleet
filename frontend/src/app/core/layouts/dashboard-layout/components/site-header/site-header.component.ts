import { ChangeDetectionStrategy, Component, computed, inject, input } from '@angular/core';
import { toSignal } from '@angular/core/rxjs-interop';
import { NavigationEnd, Router } from '@angular/router';
import { filter, map, startWith } from 'rxjs';
import { HlmBreadcrumbImports } from '@spartan-ng/helm/breadcrumb';
import { HlmSeparatorImports } from '@spartan-ng/helm/separator';
import { HlmSidebarImports } from '@spartan-ng/helm/sidebar';
import { MenuItemInterface } from '../../interfaces/menu-item.interface';

interface BreadcrumbCrumb {
  label: string;
  link: string | null;
}

/**
 * Maps a section's URL path (e.g. a top-level item's routerLink, or a nested
 * child's routerLink) to the full breadcrumb trail that should be shown for it.
 */
function buildBreadcrumbTrails(items: MenuItemInterface[]): Map<string, BreadcrumbCrumb[]> {
  return new Map<string, BreadcrumbCrumb[]>(
    items.flatMap(item => {
      if (item.children?.length) {
        return item.children
          .filter(
            (child): child is typeof child & { routerLink: string } => child.routerLink !== null
          )
          .map(
            child =>
              [
                child.routerLink,
                [
                  { label: item.label, link: item.routerLink },
                  { label: child.label, link: null },
                ],
              ] as [string, BreadcrumbCrumb[]]
          );
      }
      if (item.routerLink === null) {
        return [];
      }
      return [
        [item.routerLink, [{ label: item.label, link: null }]] as [string, BreadcrumbCrumb[]],
      ];
    })
  );
}

/** Minimal header: sidebar trigger + a breadcrumb trail for the active section. */
@Component({
  selector: 'app-site-header',
  standalone: true,
  imports: [HlmSidebarImports, HlmSeparatorImports, HlmBreadcrumbImports],
  templateUrl: './site-header.component.html',
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class SiteHeaderComponent {
  private readonly router = inject(Router);

  menuItems = input<MenuItemInterface[]>([]);

  private readonly breadcrumbTrails = computed(() => buildBreadcrumbTrails(this.menuItems()));

  private readonly currentUrl = toSignal(
    this.router.events.pipe(
      filter((event): event is NavigationEnd => event instanceof NavigationEnd),
      map(event => event.urlAfterRedirects),
      startWith(this.router.url)
    ),
    { initialValue: this.router.url }
  );

  protected readonly breadcrumbTrail = computed<BreadcrumbCrumb[]>(() => {
    // segments[0] is this layout's own base path (e.g. "dashboard"/"admin");
    // menu item routerLinks are relative to it, so section starts at segments[1].
    const segments = this.currentUrl().split('/').filter(Boolean);
    const section = segments.length > 1 ? segments[1] : '';
    const subSection = segments[2];
    const trails = this.breadcrumbTrails();

    if (section && subSection) {
      const nested = trails.get(`${section}/${subSection}`);
      if (nested) {
        return nested;
      }
    }

    return trails.get(section) ?? [];
  });
}
