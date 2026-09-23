import { ChangeDetectionStrategy, Component, input } from '@angular/core';
import { HlmSidebarImports } from '@spartan-ng/helm/sidebar';
import { MenuItemInterface } from '../../interfaces/menu-item.interface';
import { NavUserComponent } from '../nav-user/nav-user.component';
import { NavMainComponent } from './nav-main/nav-main.component';

@Component({
  selector: 'app-sidebar',
  standalone: true,
  imports: [HlmSidebarImports, NavMainComponent, NavUserComponent],
  templateUrl: './sidebar.component.html',
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class SidebarComponent {
  menuItems = input<MenuItemInterface[]>([]);
}
