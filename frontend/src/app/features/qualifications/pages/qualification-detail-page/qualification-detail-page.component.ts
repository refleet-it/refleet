import { Component, DestroyRef, inject, OnInit, signal } from '@angular/core';
import { takeUntilDestroyed } from '@angular/core/rxjs-interop';
import { ActivatedRoute, RouterLink } from '@angular/router';
import { FormsModule } from '@angular/forms';
import { HttpErrorResponse } from '@angular/common/http';
import { interval, of, Subscription, switchMap } from 'rxjs';
import { NgIcon, provideIcons } from '@ng-icons/core';
import { lucideRefreshCw, lucideRotateCcw } from '@ng-icons/lucide';
import { HlmButton } from '@spartan-ng/helm/button';
import { HlmCardImports } from '@spartan-ng/helm/card';
import { HlmFieldImports } from '@spartan-ng/helm/field';
import { HlmInput } from '@spartan-ng/helm/input';
import { HlmLabel } from '@spartan-ng/helm/label';
import { HlmNumberedPagination } from '@spartan-ng/helm/pagination';
import { HlmBadgeImports } from '@spartan-ng/helm/badge';
import { HlmProgressImports } from '@spartan-ng/helm/progress';
import { HlmSkeletonImports } from '@spartan-ng/helm/skeleton';
import { HlmDialogService } from '@spartan-ng/helm/dialog';
import { ResultDetailModalComponent } from '../../../../shared/modals/result-detail-modal/result-detail-modal.component';
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
import { injectPagedList } from '../../../../shared/utils/paged-list';
import { formatDateTime } from '../../../../shared/utils/format-date';
import { CRITERIA_ENGINE_LABELS } from '../../../../core/models/criteria.model';
import {
  AGENT_WORKING_QUALIFICATION_STATUSES,
  AGENT_WORKING_QUALIFICATION_TARGET_STATUSES,
  ARCHIVABLE_QUALIFICATION_STATUSES,
  OVERRIDABLE_QUALIFICATION_TARGET_STATUSES,
  QUALIFICATION_STATUS_BADGE_VARIANTS,
  QUALIFICATION_STATUS_LABELS,
  QUALIFICATION_TARGET_STATUS_BADGE_VARIANTS,
  QUALIFICATION_TARGET_STATUS_DESCRIPTIONS,
  QUALIFICATION_TARGET_STATUS_LABELS,
  QualificationDetails,
  QualificationRerunPrefill,
  QualificationStatus,
  QualificationTargetOverview,
  QualificationTargetStatus,
  RETRYABLE_QUALIFICATION_STATUSES,
  TERMINAL_QUALIFICATION_STATUSES,
} from '../../../../core/models/qualification.model';
import { StatusBadgeVariant } from '../../../../core/models/status-badge.model';
import { QualificationService } from '../../../../core/services/qualification.service';
import { POLL_INTERVAL_MS } from '../../../../core/config/timings';

@Component({
  selector: 'app-qualification-detail-page',
  standalone: true,
  imports: [
    FormsModule,
    RouterLink,
    NgIcon,
    HlmButton,
    HlmCardImports,
    HlmFieldImports,
    HlmInput,
    HlmLabel,
    HlmBadgeImports,
    StatusBadgeComponent,
    HlmProgressImports,
    HlmSkeletonImports,
    TableSkeletonRowsComponent,
    ListErrorComponent,
    HlmNumberedPagination,
  ],
  providers: [provideIcons({ lucideRefreshCw, lucideRotateCcw })],
  templateUrl: './qualification-detail-page.component.html',
})
export class QualificationDetailPageComponent implements OnInit {
  private readonly qualificationService = inject(QualificationService);
  private readonly route = inject(ActivatedRoute);
  private readonly destroyRef = inject(DestroyRef);
  private readonly dialogService = inject(HlmDialogService);

  private readonly qualificationId = this.route.snapshot.paramMap.get('id') ?? '';

  protected readonly statusLabels = QUALIFICATION_STATUS_LABELS;
  protected readonly targetStatusLabels = QUALIFICATION_TARGET_STATUS_LABELS;
  protected readonly targetStatusDescriptions = QUALIFICATION_TARGET_STATUS_DESCRIPTIONS;
  protected readonly criteriaEngineLabels = CRITERIA_ENGINE_LABELS;

  protected readonly qualification = signal<QualificationDetails | null>(null);
  protected readonly isLoading = signal(true);
  protected readonly loadError = signal<string | null>(null);

  /** Every target's project id, fetched once (not paginated) — feeds rerunPrefill()'s
   *  synchronous [state] binding. The target set is fixed once a qualification starts,
   *  so this doesn't need to track polling like the table below does. */
  private readonly allTargetProjectIds = signal<string[]>([]);

  protected readonly targetsList = injectPagedList<QualificationTargetOverview>((page, limit) =>
    this.qualificationId
      ? this.qualificationService.listTargets(this.qualificationId, page, limit)
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

  protected readonly actionError = signal<string | null>(null);
  protected readonly isStarting = signal(false);
  protected readonly isCancelling = signal(false);
  protected readonly isArchiving = signal(false);

  protected readonly isRetrying = signal(false);
  protected readonly retryingTargetId = signal<string | null>(null);

  protected readonly overridingTargetId = signal<string | null>(null);
  protected readonly overrideNote = signal('');
  protected readonly isOverriding = signal(false);

  protected readonly showCancelForm = signal(false);
  protected readonly cancelReason = signal('');

  protected readonly targetsSkeletonColumns: TableSkeletonColumn[] = [
    { width: 'w-40' },
    { width: 'w-24' },
    { width: 'w-28' },
    { width: 'w-32' },
  ];

  private pollSubscription: Subscription | null = null;

  ngOnInit(): void {
    this.loadAll();
  }

  statusBadgeVariant(status: QualificationStatus): StatusBadgeVariant {
    return QUALIFICATION_STATUS_BADGE_VARIANTS[status];
  }

  isAgentWorking(status: QualificationStatus): boolean {
    return AGENT_WORKING_QUALIFICATION_STATUSES.has(status);
  }

  targetStatusBadgeVariant(status: QualificationTargetStatus): StatusBadgeVariant {
    return QUALIFICATION_TARGET_STATUS_BADGE_VARIANTS[status];
  }

  isAgentWorkingOnTarget(status: QualificationTargetStatus): boolean {
    return AGENT_WORKING_QUALIFICATION_TARGET_STATUSES.has(status);
  }

  targetStatusTooltip(target: QualificationTargetOverview): string {
    return this.targetStatusDescriptions[target.status];
  }

  protected readonly formatDate = formatDateTime;

  resultSummary(target: QualificationTargetOverview): string {
    return target.summary || '—';
  }

  /** "4/5" next to the verdict, so a borderline 3 and a confident 5 read differently at a glance. */
  scoreLabel(target: QualificationTargetOverview): string | null {
    return null === target.score ? null : `${String(target.score)}/5`;
  }

  hasResultDetail(target: QualificationTargetOverview): boolean {
    return '—' !== this.resultSummary(target);
  }

  /** The prompt exactly as the job carries it — framing and answer contract included, not just the stored text. */
  showPrompt(): void {
    const qualification = this.qualification();
    if (!qualification?.qualificationPrompt) {
      return;
    }

    this.qualificationService
      .previewPrompt({
        qualificationPrompt: qualification.qualificationPrompt,
        qualificationRules: qualification.qualificationRules ?? undefined,
      })
      .subscribe({
        next: prompt => this.openPromptModal(qualification.title, prompt),
        error: () => this.openPromptModal(qualification.title, qualification.qualificationPrompt),
      });
  }

  private openPromptModal(subtitle: string, body: string): void {
    this.dialogService.open(ResultDetailModalComponent, {
      contentClass: 'sm:max-w-[700px]',
      context: {
        title: 'Prompt sent to the agent',
        subtitle,
        body,
      },
    });
  }

  showResultDetail(target: QualificationTargetOverview): void {
    this.dialogService.open(ResultDetailModalComponent, {
      contentClass: 'sm:max-w-[700px]',
      context: {
        title: target.projectSnapshot.name,
        subtitle: this.targetStatusLabels[target.status],
        body: this.resultSummary(target),
      },
    });
  }

  statusBreakdownEntries(): {
    status: string;
    label: string;
    description: string;
    count: number;
  }[] {
    const breakdown = this.qualification()?.statusBreakdown ?? {};
    return Object.entries(breakdown)
      .filter(([, count]) => count > 0)
      .map(([status, count]) => ({
        status,
        label: QUALIFICATION_TARGET_STATUS_LABELS[status as QualificationTargetStatus] ?? status,
        description: this.targetStatusDescriptions[status as QualificationTargetStatus] ?? '',
        count,
      }));
  }

  targetTotal(): number {
    return this.qualification()?.targetCount ?? 0;
  }

  progressPercent(): number {
    // Null only when there are no targets at all, and the template shows an empty state
    // for that case instead of a bar.
    return this.qualification()?.progressPercent ?? 0;
  }

  isTerminalStatus(status: QualificationStatus): boolean {
    return TERMINAL_QUALIFICATION_STATUSES.includes(status);
  }

  canArchive(qualification: QualificationDetails): boolean {
    return (
      !qualification.archivedAt && ARCHIVABLE_QUALIFICATION_STATUSES.includes(qualification.status)
    );
  }

  archive(): void {
    if (this.isArchiving() || !this.qualificationId) {
      return;
    }

    this.dialogService
      .open<boolean, ConfirmDialogContext>(ConfirmDialogComponent, {
        context: {
          title: 'Archive this qualification?',
          description:
            'It moves to the archive and keeps its targets, but it can no longer be changed or ' +
            'used to create shifts. Archiving cannot be undone.',
          confirmLabel: 'Archive qualification',
          destructive: true,
        },
      })
      .closed$.subscribe(confirmed => {
        if (confirmed) {
          this.performArchive();
        }
      });
  }

  private performArchive(): void {
    this.isArchiving.set(true);
    this.actionError.set(null);

    this.qualificationService.archive(this.qualificationId).subscribe({
      next: qualification => {
        this.qualification.set(qualification);
        this.isArchiving.set(false);
      },
      error: () => {
        this.isArchiving.set(false);
        this.actionError.set('Failed to archive the qualification.');
      },
    });
  }

  /** Passed as router state to the new-qualification page's "Run again" link. */
  rerunPrefill(): QualificationRerunPrefill {
    const qualification = this.qualification();
    return {
      title: qualification?.title ?? '',
      description: qualification?.description ?? '',
      qualificationMode: 'ai',
      qualificationEngine: qualification?.qualificationEngine ?? null,
      qualificationPrompt: qualification?.qualificationPrompt ?? '',
      qualificationModel: qualification?.qualificationModel ?? null,
      qualificationRules: qualification?.qualificationRules ?? null,
      qualificationSources: qualification?.qualificationSources ?? [],
      projectIds: this.allTargetProjectIds(),
    };
  }

  start(): void {
    if (this.isStarting()) {
      return;
    }
    this.isStarting.set(true);
    this.actionError.set(null);

    this.qualificationService.start(this.qualificationId).subscribe({
      next: () => {
        this.isStarting.set(false);
        this.reload();
      },
      error: () => {
        this.isStarting.set(false);
        this.actionError.set('Failed to start the qualification.');
      },
    });
  }

  failedTargetCount(qualification: QualificationDetails): number {
    if (
      qualification.archivedAt ||
      !RETRYABLE_QUALIFICATION_STATUSES.includes(qualification.status)
    ) {
      return 0;
    }
    return qualification.statusBreakdown['failed'] ?? 0;
  }

  retryFailed(): void {
    if (this.isRetrying()) {
      return;
    }
    this.isRetrying.set(true);
    this.actionError.set(null);

    this.qualificationService.retryFailedTargets(this.qualificationId).subscribe({
      next: qualification => {
        this.isRetrying.set(false);
        this.qualification.set(qualification);
        this.reload();
      },
      error: () => {
        this.isRetrying.set(false);
        this.actionError.set('Failed to retry the failed targets.');
      },
    });
  }

  canRetry(target: QualificationTargetOverview): boolean {
    const qualification = this.qualification();
    return (
      'failed' === target.status &&
      !!qualification &&
      !qualification.archivedAt &&
      RETRYABLE_QUALIFICATION_STATUSES.includes(qualification.status)
    );
  }

  retryTarget(target: QualificationTargetOverview): void {
    if (this.retryingTargetId()) {
      return;
    }
    this.retryingTargetId.set(target.id);
    this.actionError.set(null);

    this.qualificationService.retryTarget(this.qualificationId, target.id).subscribe({
      next: () => {
        this.retryingTargetId.set(null);
        this.reload();
      },
      error: () => {
        this.retryingTargetId.set(null);
        this.actionError.set('Failed to retry the target.');
      },
    });
  }

  canOverride(target: QualificationTargetOverview): boolean {
    return OVERRIDABLE_QUALIFICATION_TARGET_STATUSES.includes(target.status);
  }

  startOverride(target: QualificationTargetOverview): void {
    this.overridingTargetId.set(target.id);
    this.overrideNote.set('');
  }

  cancelOverride(): void {
    this.overridingTargetId.set(null);
    this.overrideNote.set('');
  }

  submitOverride(target: QualificationTargetOverview, qualified: boolean): void {
    if (this.isOverriding()) {
      return;
    }
    this.isOverriding.set(true);
    this.actionError.set(null);

    const note = this.overrideNote().trim();
    this.qualificationService
      .overrideTarget(this.qualificationId, target.id, {
        qualified,
        ...(note ? { note } : {}),
      })
      .subscribe({
        next: () => {
          this.isOverriding.set(false);
          this.overridingTargetId.set(null);
          this.overrideNote.set('');
          this.reload();
        },
        error: () => {
          this.isOverriding.set(false);
          this.actionError.set('Failed to override the qualification decision.');
        },
      });
  }

  toggleCancelForm(): void {
    this.showCancelForm.update(current => !current);
  }

  confirmCancel(): void {
    if (this.isCancelling()) {
      return;
    }
    this.isCancelling.set(true);
    this.actionError.set(null);

    const reason = this.cancelReason().trim();
    this.qualificationService.cancel(this.qualificationId, reason ? { reason } : {}).subscribe({
      next: () => {
        this.isCancelling.set(false);
        this.showCancelForm.set(false);
        this.cancelReason.set('');
        this.reload();
      },
      error: () => {
        this.isCancelling.set(false);
        this.actionError.set('Failed to cancel the qualification.');
      },
    });
  }

  private loadAll(): void {
    if (!this.qualificationId) {
      this.isLoading.set(false);
      this.loadError.set('Invalid qualification identifier.');
      return;
    }

    this.qualificationService.get(this.qualificationId).subscribe({
      next: qualification => {
        this.qualification.set(qualification);
        this.isLoading.set(false);
        this.loadError.set(null);
        this.ensurePolling();
      },
      error: (error: HttpErrorResponse) => {
        this.isLoading.set(false);
        this.loadError.set(
          404 === error.status ? 'Qualification not found.' : 'Failed to load the qualification.'
        );
      },
    });

    this.qualificationService.listTargets(this.qualificationId, 1, 100).subscribe({
      next: targets => this.allTargetProjectIds.set(targets.items.map(target => target.projectId)),
      error: () => {
        // Best-effort only — feeds the optional "Run again" prefill, not the page itself.
      },
    });
  }

  /** Reloads the qualification and the current page of targets after a mutating action. */
  private reload(): void {
    this.loadAll();
    this.targetsList.refresh();
  }

  private ensurePolling(): void {
    if (this.pollSubscription && !this.pollSubscription.closed) {
      return;
    }
    const status = this.qualification()?.status;
    if (!status || this.isTerminalStatus(status)) {
      return;
    }

    this.pollSubscription = interval(POLL_INTERVAL_MS)
      .pipe(
        switchMap(() => this.qualificationService.get(this.qualificationId)),
        takeUntilDestroyed(this.destroyRef)
      )
      .subscribe({
        next: qualification => {
          this.qualification.set(qualification);
          this.targetsList.refresh();
          if (this.isTerminalStatus(qualification.status)) {
            this.pollSubscription?.unsubscribe();
          }
        },
        error: () => {
          this.pollSubscription?.unsubscribe();
        },
      });
  }
}
