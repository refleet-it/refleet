import { ChangeDetectionStrategy, Component, inject, input } from '@angular/core';
import { toSignal } from '@angular/core/rxjs-interop';
import { NavigationEnd, Router, RouterLink, RouterLinkActive } from '@angular/router';
import { filter, map, startWith } from 'rxjs';
import { NgIcon, provideIcons } from '@ng-icons/core';
import {
  lucideBookOpen,
  lucideChevronRight,
  lucideFolderGit2,
  lucideLayoutDashboard,
  lucideListChecks,
  lucideSearchCheck,
  lucideServer,
  lucideSettings,
  lucideUsers,
} from '@ng-icons/lucide';
import { HlmCollapsibleImports } from '@spartan-ng/helm/collapsible';
import { HlmSidebarImports, HlmSidebarService } from '@spartan-ng/helm/sidebar';
import { MenuItemInterface } from '../../../interfaces/menu-item.interface';

@Component({
  selector: 'app-nav-main',
  standalone: true,
  imports: [HlmSidebarImports, HlmCollapsibleImports, NgIcon, RouterLink, RouterLinkActive],
  // Menu item icons are dynamic lucide names coming from MenuItemInterface.icon.
  // @ng-icons requires icons to be registered statically, so any new icon used
  // in a menu const (dashboard-menu.const.ts) must be added to this provideIcons() call.
  providers: [
    provideIcons({
      lucideBookOpen,
      lucideChevronRight,
      lucideFolderGit2,
      lucideLayoutDashboard,
      lucideListChecks,
      lucideSearchCheck,
      lucideServer,
      lucideSettings,
      lucideUsers,
    }),
  ],
  templateUrl: './nav-main.component.html',
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class NavMainComponent {
  items = input<MenuItemInterface[]>([]);

  private readonly router = inject(Router);
  private readonly sidebarService = inject(HlmSidebarService);

  // A signal rather than reading router.url on demand: this component is OnPush, so a plain read
  // only looks right because the routerLinkActive directives beside it happen to mark it dirty on
  // navigation. Drop those and the expanded group would keep highlighting the page you left.
  private readonly currentUrl = toSignal(
    this.router.events.pipe(
      filter((event): event is NavigationEnd => event instanceof NavigationEnd),
      map(event => event.urlAfterRedirects),
      startWith(this.router.url)
    ),
    { initialValue: this.router.url }
  );

  closeMobileSidebar(): void {
    this.sidebarService.setOpenMobile(false);
  }

  isChildActive(menuItem: MenuItemInterface): boolean {
    if (!menuItem.children?.length) {
      return false;
    }

    const url = this.currentUrl();

    return menuItem.children.some(
      child => !!child.routerLink && url.includes(`/${child.routerLink}`)
    );
  }
}
