import { HttpErrorResponse } from '@angular/common/http';
import { Component, inject, OnInit, signal } from '@angular/core';
import { ActivatedRoute, RouterLink } from '@angular/router';
import { of } from 'rxjs';
import { HlmBadgeImports } from '@spartan-ng/helm/badge';
import { HlmButton } from '@spartan-ng/helm/button';
import { HlmCardImports } from '@spartan-ng/helm/card';
import { HlmNumberedPagination } from '@spartan-ng/helm/pagination';
import { HlmDialogService } from '@spartan-ng/helm/dialog';
import { HlmProgressImports } from '@spartan-ng/helm/progress';
import { HlmSkeletonImports } from '@spartan-ng/helm/skeleton';
import {
  ConfirmDialogComponent,
  ConfirmDialogContext,
} from '../../../../shared/modals/confirm-dialog/confirm-dialog.component';
import { ListErrorComponent } from '../../../../shared/components/list-error/list-error.component';
import { StatusBadgeComponent } from '../../../../shared/components/status-badge/status-badge.component';
import {
  TableSkeletonColumn,
  TableSkeletonRowsComponent,
} from '../../../../shared/components/table-skeleton-rows/table-skeleton-rows.component';
import { formatDateTime } from '../../../../shared/utils/format-date';
import { injectPagedList } from '../../../../shared/utils/paged-list';
import {
  AGENT_WORKING_RUNNER_JOB_STATUSES,
  AGENT_WORKING_RUNNER_STATUSES,
  rateLimitWindowLabel,
  RUNNER_JOB_KIND_LABELS,
  RUNNER_JOB_MODE_LABELS,
  RUNNER_JOB_STATUS_BADGE_VARIANTS,
  RUNNER_JOB_STATUS_LABELS,
  RUNNER_RATE_LIMIT_STATUS_BADGE_VARIANTS,
  RUNNER_RATE_LIMIT_STATUS_LABELS,
  RUNNER_STATUS_BADGE_VARIANTS,
  RUNNER_STATUS_LABELS,
  RunnerJobOverview,
  RunnerJobStatus,
  RunnerOverview,
  RunnerRateLimitStatus,
  RunnerStatus,
} from '../../../../core/models/runner.model';
import { StatusBadgeVariant } from '../../../../core/models/status-badge.model';
import { RunnerService } from '../../../../core/services/runner.service';
import { AI_AGENT_ENGINE_LABELS } from '../../../../core/models/criteria.model';

@Component({
  selector: 'app-runner-detail-page',
  standalone: true,
  imports: [
    RouterLink,
    HlmCardImports,
    HlmBadgeImports,
    StatusBadgeComponent,
    HlmButton,
    HlmSkeletonImports,
    TableSkeletonRowsComponent,
    ListErrorComponent,
    HlmNumberedPagination,
    HlmProgressImports,
  ],
  templateUrl: './runner-detail-page.component.html',
})
export class RunnerDetailPageComponent implements OnInit {
  private readonly runnerService = inject(RunnerService);
  private readonly route = inject(ActivatedRoute);
  private readonly dialogService = inject(HlmDialogService);

  private readonly runnerId = this.route.snapshot.paramMap.get('id') ?? '';

  protected readonly statusLabels = RUNNER_STATUS_LABELS;
  protected readonly agentEngineLabels = AI_AGENT_ENGINE_LABELS;
  protected readonly jobKindLabels = RUNNER_JOB_KIND_LABELS;
  protected readonly jobModeLabels = RUNNER_JOB_MODE_LABELS;
  protected readonly jobStatusLabels = RUNNER_JOB_STATUS_LABELS;
  protected readonly rateLimitStatusLabels = RUNNER_RATE_LIMIT_STATUS_LABELS;
  protected readonly rateLimitWindowLabel = rateLimitWindowLabel;

  protected readonly runner = signal<RunnerOverview | null>(null);
  protected readonly isLoading = signal(true);
  protected readonly loadError = signal<string | null>(null);

  protected readonly isArchiving = signal(false);
  protected readonly isRequestingUpdate = signal(false);
  protected readonly actionError = signal<string | null>(null);

  protected readonly jobsList = injectPagedList<RunnerJobOverview>((page, limit) =>
    this.runnerId
      ? this.runnerService.listJobs(this.runnerId, page, limit)
      : of({
          items: [],
          pagination: {
            page: 1,
            limit,
            total: 0,
            totalPages: 0,
            hasNextPage: false,
            hasPreviousPage: false,
          },
        })
  );

  protected readonly jobsSkeletonColumns: TableSkeletonColumn[] = [
    { width: 'w-24' },
    { width: 'w-32' },
    { width: 'w-28' },
    { width: 'w-16' },
    { width: 'w-10' },
    { width: 'w-40' },
    { width: 'w-24' },
  ];

  ngOnInit(): void {
    if (!this.runnerId) {
      this.isLoading.set(false);
      this.loadError.set('Invalid runner identifier.');
      return;
    }

    this.runnerService.get(this.runnerId).subscribe({
      next: runner => {
        this.runner.set(runner);
        this.isLoading.set(false);
      },
      error: (error: HttpErrorResponse) => {
        this.isLoading.set(false);
        this.loadError.set(
          404 === error.status ? 'Runner not found.' : 'Failed to load the runner.'
        );
      },
    });
  }

  archiveRunner(): void {
    if (this.isArchiving() || !this.runnerId) {
      return;
    }

    this.dialogService
      .open<boolean, ConfirmDialogContext>(ConfirmDialogComponent, {
        context: {
          title: 'Archive this runner?',
          description:
            'It moves to the archive and keeps its job history, but the API key it ' +
            'authenticates with is revoked for good. Running under this name again needs a new key.',
          confirmLabel: 'Archive runner',
          destructive: true,
        },
      })
      .closed$.subscribe(confirmed => {
        if (confirmed) {
          this.performArchive();
        }
      });
  }

  requestUpdate(): void {
    if (this.isRequestingUpdate() || !this.runnerId) {
      return;
    }

    this.isRequestingUpdate.set(true);
    this.actionError.set(null);

    this.runnerService.requestUpdate(this.runnerId).subscribe({
      next: runner => {
        this.runner.set(runner);
        this.isRequestingUpdate.set(false);
      },
      error: () => {
        this.isRequestingUpdate.set(false);
        this.actionError.set('Failed to request the update.');
      },
    });
  }

  private performArchive(): void {
    this.isArchiving.set(true);
    this.actionError.set(null);

    this.runnerService.archive(this.runnerId).subscribe({
      next: runner => {
        this.runner.set(runner);
        this.isArchiving.set(false);
      },
      error: () => {
        this.isArchiving.set(false);
        this.actionError.set('Failed to archive the runner.');
      },
    });
  }

  statusBadgeVariant(status: RunnerStatus): StatusBadgeVariant {
    return RUNNER_STATUS_BADGE_VARIANTS[status];
  }

  isAgentWorking(status: RunnerStatus): boolean {
    return AGENT_WORKING_RUNNER_STATUSES.has(status);
  }

  jobStatusBadgeVariant(status: RunnerJobStatus): StatusBadgeVariant {
    return RUNNER_JOB_STATUS_BADGE_VARIANTS[status];
  }

  rateLimitBadgeVariant(status: RunnerRateLimitStatus): StatusBadgeVariant {
    return RUNNER_RATE_LIMIT_STATUS_BADGE_VARIANTS[status];
  }

  percent(used: number, size: number): number {
    return size > 0 ? Math.min(100, Math.round((used / size) * 100)) : 0;
  }

  /** 1234 → "1.2k", 1234567 → "1.2M": token counts are read at a glance, not audited. */
  compactNumber(value: number): string {
    return new Intl.NumberFormat('en-GB', { notation: 'compact', maximumFractionDigits: 1 }).format(
      value
    );
  }

  formatCost(cost: { amount: number; currency: string }): string {
    return new Intl.NumberFormat('en-GB', {
      style: 'currency',
      currency: cost.currency,
      maximumFractionDigits: 4,
    }).format(cost.amount);
  }

  isAgentWorkingOnJob(status: RunnerJobStatus): boolean {
    return AGENT_WORKING_RUNNER_JOB_STATUSES.has(status);
  }

  resultSummary(job: RunnerJobOverview): string {
    return job.errorMessage || job.resultSummary || '—';
  }

  /** Route to the owning Qualification or Shift detail page, based on the job's kind. */
  ownerRoute(job: RunnerJobOverview): string[] {
    return 'qualification' === job.kind
      ? ['/dashboard/qualifications', job.ownerId]
      : ['/dashboard/shifts', job.ownerId];
  }

  protected readonly formatDate = formatDateTime;
}
