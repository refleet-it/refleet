import { Component, DestroyRef, inject } from '@angular/core';
import { takeUntilDestroyed } from '@angular/core/rxjs-interop';
import { ActivatedRoute, RouterOutlet } from '@angular/router';
import { HlmSidebarImports } from '@spartan-ng/helm/sidebar';
import { SidebarComponent } from './components/sidebar/sidebar.component';
import { SiteHeaderComponent } from './components/site-header/site-header.component';
import { MenuItemInterface } from './interfaces/menu-item.interface';

@Component({
  selector: 'app-dashboard-layout',
  standalone: true,
  imports: [RouterOutlet, HlmSidebarImports, SidebarComponent, SiteHeaderComponent],
  templateUrl: './dashboard-layout.component.html',
})
export class DashboardLayoutComponent {
  private readonly route = inject(ActivatedRoute);
  private readonly destroyRef = inject(DestroyRef);

  menuItems: MenuItemInterface[] = [];

  skipToContent(event: Event, main: HTMLElement): void {
    event.preventDefault();
    main.focus();
  }

  constructor() {
    this.route.data.pipe(takeUntilDestroyed(this.destroyRef)).subscribe(data => {
      this.menuItems = data['menuItems'] ?? [];
    });
  }
}
