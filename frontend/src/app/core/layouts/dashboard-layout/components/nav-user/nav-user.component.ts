import { Component, computed, ElementRef, inject, OnInit, signal, viewChild } from '@angular/core';
import { ComponentType } from '@angular/cdk/portal';
import { NgIcon, provideIcons } from '@ng-icons/core';
import {
  lucideChevronsUpDown,
  lucideCircleHelp,
  lucideLogOut,
  lucideSettings,
} from '@ng-icons/lucide';
import { HlmAvatarImports } from '@spartan-ng/helm/avatar';
import { HlmDialogService } from '@spartan-ng/helm/dialog';
import { HlmDropdownMenuImports } from '@spartan-ng/helm/dropdown-menu';
import { HlmSidebarImports, HlmSidebarService } from '@spartan-ng/helm/sidebar';
import { HelpModalComponent } from '../../../../../shared/modals/help-modal/help-modal.component';
import { SettingsModalComponent } from '../../../../../shared/modals/settings-modal/settings-modal.component';
import { AuthService } from '../../../../services/auth.service';
import { OrganizationService } from '../../../../services/organization.service';

/** User menu, pinned to the bottom of the sidebar. Account settings (modal) and logout live here. */
@Component({
  selector: 'app-nav-user',
  standalone: true,
  imports: [HlmAvatarImports, HlmDropdownMenuImports, HlmSidebarImports, NgIcon],
  providers: [
    provideIcons({ lucideChevronsUpDown, lucideSettings, lucideCircleHelp, lucideLogOut }),
  ],
  templateUrl: './nav-user.component.html',
})
export class NavUserComponent implements OnInit {
  private static readonly SETTINGS_DIALOG_CONTENT_CLASS = 'sm:max-w-[900px]';
  private static readonly HELP_DIALOG_CONTENT_CLASS = 'sm:max-w-[800px]';

  private readonly authService = inject(AuthService);
  private readonly organizationService = inject(OrganizationService);
  private readonly dialogService = inject(HlmDialogService);
  private readonly sidebarService = inject(HlmSidebarService);

  private readonly menuButton = viewChild<ElementRef<HTMLButtonElement>>('menuButton');

  /** Dropdown opens upward on mobile (bottom sheet), sideways on desktop. */
  protected readonly menuSide = computed(() => (this.sidebarService.isMobile() ? 'top' : 'right'));

  // Signals, not plain fields: the sidebar around this component is OnPush, so an async write to a
  // plain field would never repaint — the role below arrives after the first render.
  readonly userEmail = signal('');
  readonly userRole = signal('');
  readonly userInitial = signal('');

  ngOnInit(): void {
    this.loadUserData();
  }

  private loadUserData(): void {
    const user = this.authService.getCurrentUser();
    if (!user) {
      return;
    }

    this.userEmail.set(user.email);
    this.userInitial.set(user.email.charAt(0).toUpperCase());
    // Inside an organization "role" means the role held there, which is what /dashboard/team and
    // /dashboard/settings show. The account role is the fallback for accounts without one.
    this.userRole.set(user.role);

    this.organizationService.getMyOrganization().subscribe(overview => {
      this.userRole.set(overview.role ?? user.role);
    });
  }

  navigateToSettings(): void {
    this.openFromMenu(SettingsModalComponent, NavUserComponent.SETTINGS_DIALOG_CONTENT_CLASS);
  }

  navigateToHelp(): void {
    this.openFromMenu(HelpModalComponent, NavUserComponent.HELP_DIALOG_CONTENT_CLASS);
  }

  /**
   * The dialog restores focus to whatever opened it, but that is a dropdown item which the
   * dropdown destroys on close — so focus would land on the body. Send it to the menu button,
   * the nearest thing that outlives the dialog.
   */
  private openFromMenu(component: ComponentType<unknown>, contentClass: string): void {
    this.dialogService
      .open(component, { contentClass })
      .closed$.subscribe(() => this.menuButton()?.nativeElement.focus());
  }

  logout(): void {
    this.authService.logout();
  }
}
