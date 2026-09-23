import { Component, computed, DestroyRef, effect, inject, signal } from '@angular/core';
import { takeUntilDestroyed, toObservable } from '@angular/core/rxjs-interop';
import { ActivatedRoute, RouterLink } from '@angular/router';
import { debounceTime, distinctUntilChanged, interval, Subscription } from 'rxjs';
import { FormsModule } from '@angular/forms';
import { HlmButton } from '@spartan-ng/helm/button';
import { HlmCardImports } from '@spartan-ng/helm/card';
import { HlmInput } from '@spartan-ng/helm/input';
import { HlmNumberedPagination } from '@spartan-ng/helm/pagination';
import { HlmProgressImports } from '@spartan-ng/helm/progress';
import {
  AGENT_WORKING_QUALIFICATION_STATUSES,
  QUALIFICATION_STATUS_BADGE_VARIANTS,
  QUALIFICATION_STATUS_LABELS,
  QualificationOverview,
  QualificationStatus,
} from '../../../../core/models/qualification.model';
import { StatusBadgeVariant } from '../../../../core/models/status-badge.model';
import { QualificationService } from '../../../../core/services/qualification.service';
import { ListErrorComponent } from '../../../../shared/components/list-error/list-error.component';
import { StatusBadgeComponent } from '../../../../shared/components/status-badge/status-badge.component';
import {
  TableSkeletonColumn,
  TableSkeletonRowsComponent,
} from '../../../../shared/components/table-skeleton-rows/table-skeleton-rows.component';
import { injectPagedList } from '../../../../shared/utils/paged-list';
import { formatDateTime } from '../../../../shared/utils/format-date';
import { POLL_INTERVAL_MS, SEARCH_DEBOUNCE_MS } from '../../../../core/config/timings';

@Component({
  selector: 'app-qualifications-page',
  standalone: true,
  imports: [
    RouterLink,
    FormsModule,
    HlmButton,
    HlmCardImports,
    StatusBadgeComponent,
    HlmInput,
    HlmProgressImports,
    TableSkeletonRowsComponent,
    ListErrorComponent,
    HlmNumberedPagination,
  ],
  templateUrl: './qualifications-page.component.html',
})
export class QualificationsPageComponent {
  private readonly qualificationService = inject(QualificationService);
  private readonly destroyRef = inject(DestroyRef);
  private readonly route = inject(ActivatedRoute);

  /** The /archive route renders this same page over the archived side of the list. */
  protected readonly archived: boolean = true === this.route.snapshot.data['archived'];

  protected readonly statusLabels = QUALIFICATION_STATUS_LABELS;
  protected readonly skeletonColumns: TableSkeletonColumn[] = [
    { width: 'w-40' },
    { width: 'w-20' },
    { width: 'w-28' },
    { width: 'w-16' },
    { width: 'w-28' },
  ];
  protected readonly statusFilterOptions = Object.entries(QUALIFICATION_STATUS_LABELS) as [
    QualificationStatus,
    string,
  ][];

  protected readonly searchQuery = signal('');
  protected readonly statusFilter = signal<QualificationStatus | 'all'>('all');
  protected readonly hasActiveFilters = computed(
    () => '' !== this.searchQuery().trim() || 'all' !== this.statusFilter()
  );

  protected readonly list = injectPagedList<QualificationOverview>((page, limit) => {
    const status = this.statusFilter();
    return this.qualificationService.list(
      page,
      limit,
      this.searchQuery().trim() || undefined,
      'all' === status ? undefined : status,
      this.archived
    );
  });

  private pollSubscription: Subscription | null = null;

  constructor() {
    toObservable(this.searchQuery)
      .pipe(debounceTime(SEARCH_DEBOUNCE_MS), distinctUntilChanged(), takeUntilDestroyed())
      .subscribe(() => this.refetchFromFirstPage());

    effect(() => {
      if (!this.list.isLoading()) {
        this.ensurePolling();
      }
    });
  }

  statusBadgeVariant(status: QualificationStatus): StatusBadgeVariant {
    return QUALIFICATION_STATUS_BADGE_VARIANTS[status];
  }

  isAgentWorking(status: QualificationStatus): boolean {
    return AGENT_WORKING_QUALIFICATION_STATUSES.has(status);
  }

  protected readonly formatDate = formatDateTime;

  updateSearchQuery(value: string): void {
    this.searchQuery.set(value);
  }

  updateStatusFilter(value: string): void {
    this.statusFilter.set(value as QualificationStatus | 'all');
    this.refetchFromFirstPage();
  }

  private refetchFromFirstPage(): void {
    this.list.page.set(1);
    this.list.refresh();
  }

  private ensurePolling(): void {
    const hasActiveWork = this.list
      .items()
      .some(qualification => this.isAgentWorking(qualification.status));

    if (!hasActiveWork) {
      this.pollSubscription?.unsubscribe();
      this.pollSubscription = null;
      return;
    }

    if (this.pollSubscription && !this.pollSubscription.closed) {
      return;
    }

    this.pollSubscription = interval(POLL_INTERVAL_MS)
      .pipe(takeUntilDestroyed(this.destroyRef))
      .subscribe(() => this.list.refresh());
  }
}
