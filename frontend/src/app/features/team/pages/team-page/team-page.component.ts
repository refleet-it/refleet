import { DatePipe } from '@angular/common';
import { HttpErrorResponse } from '@angular/common/http';
import { Component, inject, OnInit, signal } from '@angular/core';
import { RouterLink } from '@angular/router';
import { of } from 'rxjs';
import { HlmButton } from '@spartan-ng/helm/button';
import { HlmCardImports } from '@spartan-ng/helm/card';
import { HlmDialogService } from '@spartan-ng/helm/dialog';
import { HlmNumberedPagination } from '@spartan-ng/helm/pagination';
import { HlmSkeletonImports } from '@spartan-ng/helm/skeleton';
import {
  Employee,
  OrganizationOverview,
  PendingInvitation,
} from '../../../../core/models/organization.model';
import { OrganizationService } from '../../../../core/services/organization.service';
import { AuthService } from '../../../../core/services/auth.service';
import { ToastService } from '../../../../shared/services/toast.service';
import {
  ConfirmDialogComponent,
  ConfirmDialogContext,
} from '../../../../shared/modals/confirm-dialog/confirm-dialog.component';
import { ListErrorComponent } from '../../../../shared/components/list-error/list-error.component';
import {
  TableSkeletonColumn,
  TableSkeletonRowsComponent,
} from '../../../../shared/components/table-skeleton-rows/table-skeleton-rows.component';
import { injectPagedList } from '../../../../shared/utils/paged-list';

const EMPTY_PAGE = {
  page: 1,
  limit: 20,
  total: 0,
  totalPages: 0,
  hasNextPage: false,
  hasPreviousPage: false,
};

@Component({
  selector: 'app-team-page',
  standalone: true,
  imports: [
    RouterLink,
    DatePipe,
    HlmButton,
    HlmCardImports,
    HlmSkeletonImports,
    TableSkeletonRowsComponent,
    ListErrorComponent,
    HlmNumberedPagination,
  ],
  templateUrl: './team-page.component.html',
})
export class TeamPageComponent implements OnInit {
  private readonly organizationService = inject(OrganizationService);
  private readonly authService = inject(AuthService);
  private readonly toastService = inject(ToastService);
  private readonly dialogService = inject(HlmDialogService);

  protected readonly overview = signal<OrganizationOverview | null>(null);
  protected readonly isLoading = signal(true);
  protected readonly loadError = signal(false);
  protected readonly removingAccountId = signal<string | null>(null);
  protected readonly transferringAccountId = signal<string | null>(null);
  protected readonly cancellingInvitationId = signal<string | null>(null);

  protected readonly employeesList = injectPagedList<Employee>((page, limit) =>
    this.organizationService.listEmployees(page, limit)
  );

  protected readonly invitationsList = injectPagedList<PendingInvitation>((page, limit) =>
    this.isOwner
      ? this.organizationService.listPendingInvitations(page, limit)
      : of({ items: [], pagination: EMPTY_PAGE })
  );

  protected readonly employeesSkeletonColumns: TableSkeletonColumn[] = [
    { width: 'w-48' },
    { width: 'w-16' },
  ];
  protected readonly invitationsSkeletonColumns: TableSkeletonColumn[] = [
    { width: 'w-48' },
    { width: 'w-24' },
  ];

  ngOnInit(): void {
    this.loadOverview();
  }

  get isOwner(): boolean {
    return 'owner' === this.overview()?.role;
  }

  get currentAccountId(): string | undefined {
    return this.authService.getCurrentUser()?.id;
  }

  removeEmployee(accountId: string, email: string): void {
    if (null !== this.removingAccountId()) {
      return;
    }

    this.dialogService
      .open<boolean, ConfirmDialogContext>(ConfirmDialogComponent, {
        context: {
          title: 'Remove employee?',
          description: `${email} will lose access to this organization.`,
          confirmLabel: 'Remove',
          destructive: true,
        },
      })
      .closed$.subscribe(confirmed => {
        if (confirmed) {
          this.performRemoveEmployee(accountId);
        }
      });
  }

  private performRemoveEmployee(accountId: string): void {
    this.removingAccountId.set(accountId);

    this.organizationService.removeEmployee(accountId).subscribe({
      next: () => {
        this.removingAccountId.set(null);
        this.employeesList.refresh();
        this.toastService.add({ severity: 'success', summary: 'Employee removed' });
      },
      error: (error: HttpErrorResponse) => {
        this.removingAccountId.set(null);
        this.toastService.add({
          severity: 'error',
          summary: 'Could not remove employee',
          detail: this.resolveRemoveError(error),
        });
      },
    });
  }

  private resolveRemoveError(error: HttpErrorResponse): string {
    if (403 === error.status) {
      return 'Only the organization owner can remove teammates.';
    }
    if (404 === error.status) {
      return 'This employee is no longer in your organization.';
    }
    return 'Please try again.';
  }

  transferOwnership(accountId: string, email: string): void {
    if (null !== this.transferringAccountId()) {
      return;
    }

    this.dialogService
      .open<boolean, ConfirmDialogContext>(ConfirmDialogComponent, {
        context: {
          title: 'Transfer ownership?',
          description: `${email} will become the organization owner and you will lose owner privileges.`,
          confirmLabel: 'Make owner',
        },
      })
      .closed$.subscribe(confirmed => {
        if (confirmed) {
          this.performTransferOwnership(accountId);
        }
      });
  }

  private performTransferOwnership(accountId: string): void {
    this.transferringAccountId.set(accountId);

    this.organizationService.transferOwnership(accountId).subscribe({
      next: () => {
        this.transferringAccountId.set(null);
        const overview = this.overview();
        if (overview) {
          this.overview.set({ ...overview, role: 'user' });
        }
        this.employeesList.refresh();
        this.invitationsList.refresh();
        this.toastService.add({ severity: 'success', summary: 'Ownership transferred' });
      },
      error: (error: HttpErrorResponse) => {
        this.transferringAccountId.set(null);
        this.toastService.add({
          severity: 'error',
          summary: 'Could not transfer ownership',
          detail: this.resolveTransferError(error),
        });
      },
    });
  }

  private resolveTransferError(error: HttpErrorResponse): string {
    if (403 === error.status) {
      return 'Only the organization owner can transfer ownership.';
    }
    if (404 === error.status) {
      return 'This employee is no longer in your organization.';
    }
    return 'Please try again.';
  }

  cancelInvitation(invitationId: string): void {
    if (null !== this.cancellingInvitationId()) {
      return;
    }

    this.cancellingInvitationId.set(invitationId);

    this.organizationService.cancelInvitation(invitationId).subscribe({
      next: () => {
        this.cancellingInvitationId.set(null);
        this.invitationsList.refresh();
        this.toastService.add({ severity: 'success', summary: 'Invitation cancelled' });
      },
      error: () => {
        this.cancellingInvitationId.set(null);
        this.toastService.add({
          severity: 'error',
          summary: 'Could not cancel invitation',
          detail: 'Please try again.',
        });
      },
    });
  }

  protected retryOverview(): void {
    this.loadOverview();
  }

  private loadOverview(): void {
    this.isLoading.set(true);
    this.loadError.set(false);
    this.organizationService.getMyOrganization().subscribe({
      next: overview => {
        this.overview.set(overview);
        this.isLoading.set(false);
        if ('owner' === overview.role) {
          this.invitationsList.refresh();
        }
      },
      error: () => {
        this.isLoading.set(false);
        this.loadError.set(true);
      },
    });
  }
}
