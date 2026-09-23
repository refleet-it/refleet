import { Component, DestroyRef, inject, OnInit, signal } from '@angular/core';
import { takeUntilDestroyed } from '@angular/core/rxjs-interop';
import { ActivatedRoute, RouterLink } from '@angular/router';
import { FormsModule } from '@angular/forms';
import { HttpErrorResponse } from '@angular/common/http';
import { interval, Observable, of, Subscription, switchMap } from 'rxjs';
import { NgIcon, provideIcons } from '@ng-icons/core';
import { lucideExternalLink, lucidePlay, lucideRotateCw } from '@ng-icons/lucide';
import { HlmButton } from '@spartan-ng/helm/button';
import { HlmCardImports } from '@spartan-ng/helm/card';
import { HlmFieldImports } from '@spartan-ng/helm/field';
import { HlmInput } from '@spartan-ng/helm/input';
import { HlmLabel } from '@spartan-ng/helm/label';
import { HlmNumberedPagination } from '@spartan-ng/helm/pagination';
import { HlmTextarea } from '@spartan-ng/helm/textarea';
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
import {
  AiAgentEngine,
  AiModel,
  CRITERIA_ENGINE_LABELS,
} from '../../../../core/models/criteria.model';
import { AvailableModels } from '../../../../core/models/runner.model';
import { RunnerService } from '../../../../core/services/runner.service';
import {
  AGENT_WORKING_SHIFT_STATUSES,
  AGENT_WORKING_SHIFT_TARGET_STATUSES,
  ARCHIVABLE_SHIFT_STATUSES,
  DefineShiftChangePayload,
  RERUNNABLE_SHIFT_TARGET_STATUSES,
  SHIFT_STATUS_BADGE_VARIANTS,
  SHIFT_STATUS_LABELS,
  SHIFT_TARGET_STATUS_BADGE_VARIANTS,
  SHIFT_TARGET_STATUS_DESCRIPTIONS,
  SHIFT_TARGET_STATUS_LABELS,
  ShiftDetails,
  ShiftStatus,
  ShiftTargetOverview,
  ShiftTargetStatus,
  TARGET_RUNNABLE_SHIFT_STATUSES,
  TERMINAL_SHIFT_STATUSES,
} from '../../../../core/models/shift.model';
import { StatusBadgeVariant } from '../../../../core/models/status-badge.model';
import { PromptSource } from '../../../../core/models/playbook.model';
import { ShiftService } from '../../../../core/services/shift.service';
import {
  PlaybookApplication,
  PlaybookPickerComponent,
} from '../../../../shared/components/playbook-picker/playbook-picker.component';
import { PromptPreviewComponent } from '../../../../shared/components/prompt-preview/prompt-preview.component';
import { POLL_INTERVAL_MS } from '../../../../core/config/timings';

interface DefineChangeForm {
  changeEngine: AiAgentEngine;
  changePrompt: string;
  changeModel: AiModel | '';
  changeRules: string;
  changeSources: PromptSource[];
}

const EMPTY_AVAILABLE_MODELS: AvailableModels = { claude: [], kiro: [] };

const EMPTY_CHANGE_FORM: DefineChangeForm = {
  changeEngine: 'claude',
  changePrompt: '',
  changeModel: '',
  changeRules: '',
  changeSources: [],
};

@Component({
  selector: 'app-shift-detail-page',
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
    HlmTextarea,
    HlmBadgeImports,
    StatusBadgeComponent,
    HlmProgressImports,
    HlmSkeletonImports,
    TableSkeletonRowsComponent,
    ListErrorComponent,
    HlmNumberedPagination,
    PlaybookPickerComponent,
    PromptPreviewComponent,
  ],
  providers: [provideIcons({ lucideExternalLink, lucidePlay, lucideRotateCw })],
  templateUrl: './shift-detail-page.component.html',
})
export class ShiftDetailPageComponent implements OnInit {
  private readonly shiftService = inject(ShiftService);
  private readonly runnerService = inject(RunnerService);
  private readonly route = inject(ActivatedRoute);
  private readonly destroyRef = inject(DestroyRef);
  private readonly dialogService = inject(HlmDialogService);

  private readonly shiftId = this.route.snapshot.paramMap.get('id') ?? '';

  protected readonly statusLabels = SHIFT_STATUS_LABELS;
  protected readonly targetStatusLabels = SHIFT_TARGET_STATUS_LABELS;
  protected readonly targetStatusDescriptions = SHIFT_TARGET_STATUS_DESCRIPTIONS;
  protected readonly criteriaEngineLabels = CRITERIA_ENGINE_LABELS;

  protected readonly shift = signal<ShiftDetails | null>(null);
  protected readonly isLoading = signal(true);
  protected readonly loadError = signal<string | null>(null);

  protected readonly targetsList = injectPagedList<ShiftTargetOverview>((page, limit) =>
    this.shiftId
      ? this.shiftService.listTargets(this.shiftId, page, limit)
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
  protected readonly isDefiningChange = signal(false);
  protected readonly isStartingChange = signal(false);
  protected readonly isCancelling = signal(false);
  protected readonly isArchiving = signal(false);

  protected readonly changeForm = signal<DefineChangeForm>({ ...EMPTY_CHANGE_FORM });
  protected readonly availableModels = signal<AvailableModels>(EMPTY_AVAILABLE_MODELS);
  protected readonly showChangeForm = signal(false);
  protected readonly showCancelForm = signal(false);
  protected readonly cancelReason = signal('');

  protected readonly markingMergedTargetId = signal<string | null>(null);
  protected readonly runningTargetId = signal<string | null>(null);

  protected readonly targetsSkeletonColumns: TableSkeletonColumn[] = [
    { width: 'w-40' },
    { width: 'w-20' },
    { width: 'w-32' },
    { width: 'w-16' },
  ];

  private pollSubscription: Subscription | null = null;

  ngOnInit(): void {
    this.loadAll();
    this.loadAvailableModels();
  }

  updateChangeForm<K extends keyof DefineChangeForm>(field: K, value: DefineChangeForm[K]): void {
    this.changeForm.update(current => ({ ...current, [field]: value }));
  }

  /** Playbook text lands in the editable fields; a task's preferred agent/model is only a suggestion. */
  applyPlaybooks(application: PlaybookApplication): void {
    this.changeForm.update(current => ({
      ...current,
      ...(undefined !== application.rules ? { changeRules: application.rules } : {}),
      ...(undefined !== application.prompt ? { changePrompt: application.prompt } : {}),
      ...(application.engine
        ? { changeEngine: application.engine, changeModel: application.model ?? '' }
        : {}),
      changeSources: application.sources,
    }));
  }

  /** Bound as a value so the preview component can call it with the form as it is at that moment. */
  protected readonly previewChangePrompt = (): Observable<string> =>
    this.shiftService.previewChangePrompt({
      changePrompt: this.changeForm().changePrompt,
      changeRules: this.changeForm().changeRules || undefined,
    });

  /** A previously selected model may not be valid for the newly selected agent. */
  updateChangeEngine(engine: AiAgentEngine): void {
    this.changeForm.update(current => ({ ...current, changeEngine: engine, changeModel: '' }));
  }

  modelsForEngine(engine: AiAgentEngine): string[] {
    return this.availableModels()[engine];
  }

  statusBadgeVariant(status: ShiftStatus): StatusBadgeVariant {
    return SHIFT_STATUS_BADGE_VARIANTS[status];
  }

  isAgentWorking(status: ShiftStatus): boolean {
    return AGENT_WORKING_SHIFT_STATUSES.has(status);
  }

  targetStatusBadgeVariant(status: ShiftTargetStatus): StatusBadgeVariant {
    return SHIFT_TARGET_STATUS_BADGE_VARIANTS[status];
  }

  isAgentWorkingOnTarget(status: ShiftTargetStatus): boolean {
    return AGENT_WORKING_SHIFT_TARGET_STATUSES.has(status);
  }

  targetStatusTooltip(target: ShiftTargetOverview): string {
    return this.targetStatusDescriptions[target.status];
  }

  protected readonly formatDate = formatDateTime;

  resultSummary(target: ShiftTargetOverview): string {
    return target.changeSummary || '—';
  }

  hasResultDetail(target: ShiftTargetOverview): boolean {
    return '—' !== this.resultSummary(target);
  }

  /** The prompt exactly as the job carries it — framing and answer contract included, not just the stored text. */
  showPrompt(): void {
    const shift = this.shift();
    if (!shift?.changePrompt) {
      return;
    }

    this.shiftService
      .previewChangePrompt({
        changePrompt: shift.changePrompt,
        changeRules: shift.changeRules ?? undefined,
      })
      .subscribe({
        next: prompt => this.openPromptModal(shift.title, prompt),
        error: () => this.openPromptModal(shift.title, shift.changePrompt ?? ''),
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

  showResultDetail(target: ShiftTargetOverview): void {
    this.dialogService.open(ResultDetailModalComponent, {
      contentClass: 'sm:max-w-[700px]',
      context: {
        title: target.projectName,
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
    const breakdown = this.shift()?.statusBreakdown ?? {};
    return Object.entries(breakdown)
      .filter(([, count]) => count > 0)
      .map(([status, count]) => ({
        status,
        label: SHIFT_TARGET_STATUS_LABELS[status as ShiftTargetStatus] ?? status,
        description: this.targetStatusDescriptions[status as ShiftTargetStatus] ?? '',
        count,
      }));
  }

  hasTrialRunInProgress(): boolean {
    return (this.shift()?.statusBreakdown['change_in_progress'] ?? 0) > 0;
  }

  targetTotal(): number {
    return this.shift()?.targetCount ?? 0;
  }

  progressPercent(): number {
    // Null only when there are no targets at all, and the template shows an empty state
    // for that case instead of a bar.
    return this.shift()?.progressPercent ?? 0;
  }

  isChangeDefined(): boolean {
    const shift = this.shift();
    return !!shift && !!shift.changeMode;
  }

  isTerminalStatus(status: ShiftStatus): boolean {
    return TERMINAL_SHIFT_STATUSES.includes(status);
  }

  canArchive(shift: ShiftDetails): boolean {
    return !shift.archivedAt && ARCHIVABLE_SHIFT_STATUSES.includes(shift.status);
  }

  archive(): void {
    if (this.isArchiving() || !this.shiftId) {
      return;
    }

    this.dialogService
      .open<boolean, ConfirmDialogContext>(ConfirmDialogComponent, {
        context: {
          title: 'Archive this shift?',
          description:
            'It moves to the archive and keeps its targets, but it can no longer be changed. ' +
            'Archiving cannot be undone.',
          confirmLabel: 'Archive shift',
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

    this.shiftService.archive(this.shiftId).subscribe({
      next: shift => {
        this.shift.set(shift);
        this.isArchiving.set(false);
      },
      error: () => {
        this.isArchiving.set(false);
        this.actionError.set('Failed to archive the shift.');
      },
    });
  }

  submitDefineChange(): void {
    const form = this.changeForm();
    if (!this.isChangeFormValid(form) || this.isDefiningChange()) {
      return;
    }
    this.isDefiningChange.set(true);
    this.actionError.set(null);

    this.shiftService.defineChange(this.shiftId, this.toChangePayload(form)).subscribe({
      next: () => {
        this.isDefiningChange.set(false);
        this.changeForm.set({ ...EMPTY_CHANGE_FORM });
        this.showChangeForm.set(false);
        this.reload();
      },
      error: () => {
        this.isDefiningChange.set(false);
        this.actionError.set('Failed to define the change.');
      },
    });
  }

  startChange(): void {
    if (this.isStartingChange()) {
      return;
    }
    this.isStartingChange.set(true);
    this.actionError.set(null);

    this.shiftService.startChange(this.shiftId).subscribe({
      next: () => {
        this.isStartingChange.set(false);
        this.reload();
      },
      error: () => {
        this.isStartingChange.set(false);
        this.actionError.set('Failed to start applying the change.');
      },
    });
  }

  canMarkMerged(target: ShiftTargetOverview): boolean {
    return 'merge_request_open' === target.status;
  }

  /**
   * A pending target of a draft can be trial-run on its own to see what the prompt does
   * before the whole shift is started; a settled target can be sent through again.
   */
  targetRunAction(target: ShiftTargetOverview): 'run' | 'rerun' | null {
    const shift = this.shift();
    if (!shift || shift.archivedAt || !this.isChangeDefined()) {
      return null;
    }
    if (!TARGET_RUNNABLE_SHIFT_STATUSES.has(shift.status)) {
      return null;
    }
    if ('pending_change' === target.status) {
      return 'draft' === shift.status ? 'run' : null;
    }
    return RERUNNABLE_SHIFT_TARGET_STATUSES.has(target.status) ? 'rerun' : null;
  }

  runTarget(target: ShiftTargetOverview): void {
    if (this.runningTargetId()) {
      return;
    }
    this.runningTargetId.set(target.id);
    this.actionError.set(null);

    this.shiftService.startTargetChange(this.shiftId, target.id).subscribe({
      next: () => {
        this.runningTargetId.set(null);
        this.reload();
      },
      error: () => {
        this.runningTargetId.set(null);
        this.actionError.set('Failed to run the change on this target.');
      },
    });
  }

  markAsMerged(target: ShiftTargetOverview): void {
    if (this.markingMergedTargetId()) {
      return;
    }
    this.markingMergedTargetId.set(target.id);
    this.actionError.set(null);

    this.shiftService
      .reportMergeRequestStatus(this.shiftId, target.id, { status: 'merged' })
      .subscribe({
        next: () => {
          this.markingMergedTargetId.set(null);
          this.reload();
        },
        error: () => {
          this.markingMergedTargetId.set(null);
          this.actionError.set('Failed to mark the merge request as merged.');
        },
      });
  }

  toggleChangeFormVisibility(): void {
    const opening = !this.showChangeForm();
    if (opening) {
      this.changeForm.set(this.changeFormFromShift());
    }
    this.showChangeForm.set(opening);
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
    this.shiftService.cancel(this.shiftId, reason ? { reason } : {}).subscribe({
      next: () => {
        this.isCancelling.set(false);
        this.showCancelForm.set(false);
        this.cancelReason.set('');
        this.reload();
      },
      error: () => {
        this.isCancelling.set(false);
        this.actionError.set('Failed to cancel the shift.');
      },
    });
  }

  /** Editing starts from the change already on the shift, so the user only fixes what differs. */
  private changeFormFromShift(): DefineChangeForm {
    const shift = this.shift();
    if (!shift?.changeMode) {
      return { ...EMPTY_CHANGE_FORM };
    }
    return {
      changeEngine: shift.changeEngine ?? 'claude',
      changePrompt: shift.changePrompt ?? '',
      changeModel: shift.changeModel ?? '',
      changeRules: shift.changeRules ?? '',
      changeSources: shift.changeSources ?? [],
    };
  }

  private isChangeFormValid(form: DefineChangeForm): boolean {
    return !!form.changePrompt.trim();
  }

  private toChangePayload(form: DefineChangeForm): DefineShiftChangePayload {
    return {
      changeMode: 'ai',
      changePrompt: form.changePrompt.trim(),
      changeEngine: form.changeEngine,
      ...(form.changeModel ? { changeModel: form.changeModel } : {}),
      ...(form.changeRules.trim() ? { changeRules: form.changeRules } : {}),
      changeSources: form.changeSources,
    };
  }

  private loadAll(): void {
    if (!this.shiftId) {
      this.isLoading.set(false);
      this.loadError.set('Invalid shift identifier.');
      return;
    }

    this.shiftService.get(this.shiftId).subscribe({
      next: shift => {
        this.shift.set(shift);
        this.isLoading.set(false);
        this.loadError.set(null);
        this.ensurePolling();
      },
      error: (error: HttpErrorResponse) => {
        this.isLoading.set(false);
        this.loadError.set(404 === error.status ? 'Shift not found.' : 'Failed to load the shift.');
      },
    });
  }

  /** Best-effort: an empty/unavailable list just leaves the Model field's options empty rather than blocking the page. */
  private loadAvailableModels(): void {
    this.runnerService
      .availableModels()
      .pipe(takeUntilDestroyed(this.destroyRef))
      .subscribe({
        next: models => this.availableModels.set(models),
        error: () => this.availableModels.set(EMPTY_AVAILABLE_MODELS),
      });
  }

  /** Reloads the shift and the current page of targets after a mutating action. */
  private reload(): void {
    this.loadAll();
    this.targetsList.refresh();
  }

  private ensurePolling(): void {
    if (this.pollSubscription && !this.pollSubscription.closed) {
      return;
    }
    const status = this.shift()?.status;
    if (!status || this.isTerminalStatus(status)) {
      return;
    }

    this.pollSubscription = interval(POLL_INTERVAL_MS)
      .pipe(
        switchMap(() => this.shiftService.get(this.shiftId)),
        takeUntilDestroyed(this.destroyRef)
      )
      .subscribe({
        next: shift => {
          this.shift.set(shift);
          this.targetsList.refresh();
          if (this.isTerminalStatus(shift.status)) {
            this.pollSubscription?.unsubscribe();
          }
        },
        error: () => {
          this.pollSubscription?.unsubscribe();
        },
      });
  }
}
