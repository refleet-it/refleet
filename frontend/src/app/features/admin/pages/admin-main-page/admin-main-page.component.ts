import { Component, inject, OnInit, signal } from '@angular/core';
import { HlmButton } from '@spartan-ng/helm/button';
import { HlmCardImports } from '@spartan-ng/helm/card';
import { AccountListItem } from '../../../../core/models/account.model';
import { AccountService } from '../../../../core/services/account.service';
import { AuthService } from '../../../../core/services/auth.service';
import { ListErrorComponent } from '../../../../shared/components/list-error/list-error.component';
import { StatusBadgeComponent } from '../../../../shared/components/status-badge/status-badge.component';
import {
  TableSkeletonColumn,
  TableSkeletonRowsComponent,
} from '../../../../shared/components/table-skeleton-rows/table-skeleton-rows.component';
import { formatDateTime } from '../../../../shared/utils/format-date';

@Component({
  selector: 'app-admin-main-page',
  standalone: true,
  imports: [
    HlmCardImports,
    StatusBadgeComponent,
    HlmButton,
    TableSkeletonRowsComponent,
    ListErrorComponent,
  ],
  templateUrl: './admin-main-page.component.html',
})
export class AdminMainPageComponent implements OnInit {
  private readonly authService = inject(AuthService);
  private readonly accountService = inject(AccountService);

  protected readonly currentUser = this.authService.getCurrentUser();

  protected readonly accounts = signal<AccountListItem[]>([]);
  protected readonly isLoading = signal(true);
  protected readonly isLoadingMore = signal(false);
  protected readonly loadError = signal<string | null>(null);
  protected readonly hasNextPage = signal(false);

  protected readonly skeletonColumns: TableSkeletonColumn[] = [
    { width: 'w-48' },
    { width: 'w-20' },
    { width: 'w-16' },
    { width: 'w-28' },
  ];

  private nextCursor: string | null = null;

  ngOnInit(): void {
    this.loadAccounts();
  }

  protected retry(): void {
    this.loadAccounts();
  }

  protected loadMore(): void {
    if (this.isLoadingMore() || !this.hasNextPage()) {
      return;
    }
    this.loadAccounts(this.nextCursor);
  }

  protected readonly formatDate = formatDateTime;

  private loadAccounts(cursor: string | null = null): void {
    if (cursor) {
      this.isLoadingMore.set(true);
    } else {
      this.isLoading.set(true);
      this.loadError.set(null);
    }

    this.accountService.list(cursor).subscribe({
      next: page => {
        this.accounts.set(cursor ? [...this.accounts(), ...page.data] : page.data);
        this.hasNextPage.set(page.pagination.hasNextPage);
        this.nextCursor = page.pagination.nextCursor;
        this.isLoading.set(false);
        this.isLoadingMore.set(false);
      },
      error: () => {
        this.isLoading.set(false);
        this.isLoadingMore.set(false);
        this.loadError.set('Could not load accounts. Please try again.');
      },
    });
  }
}
