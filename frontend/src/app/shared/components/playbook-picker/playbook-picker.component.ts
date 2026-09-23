import {
  ChangeDetectionStrategy,
  Component,
  computed,
  DestroyRef,
  effect,
  inject,
  input,
  OnInit,
  output,
  signal,
  untracked,
} from '@angular/core';
import { takeUntilDestroyed } from '@angular/core/rxjs-interop';
import { FormsModule } from '@angular/forms';
import { RouterLink } from '@angular/router';
import { HttpErrorResponse } from '@angular/common/http';
import { catchError, debounceTime, map, Observable, of, Subject, switchMap } from 'rxjs';
import { HlmBadgeImports } from '@spartan-ng/helm/badge';
import { HlmButton } from '@spartan-ng/helm/button';
import { HlmCheckboxImports } from '@spartan-ng/helm/checkbox';
import { HlmInput } from '@spartan-ng/helm/input';
import { HlmLabel } from '@spartan-ng/helm/label';
import {
  ComposedPrompt,
  Playbook,
  PlaybookUsage,
  PromptSource,
} from '../../../core/models/playbook.model';
import { AiModel, CriteriaEngine } from '../../../core/models/criteria.model';
import { PlaybookService } from '../../../core/services/playbook.service';

/**
 * What the picker wants written into the parent's fields after a composition. A field is
 * left out when the user has edited it by hand since the last composition — the text they
 * typed wins until they explicitly ask to replace it.
 */
export interface PlaybookApplication {
  rules?: string;
  prompt?: string;
  engine: CriteriaEngine | null;
  model: AiModel | null;
  sources: PromptSource[];
}

/**
 * Selects playbooks (rules on/off, at most one task with its parameters) and turns them into
 * text through the backend's compose endpoint. It never owns the prompt: the parent's
 * textareas do, so whatever the agent will see is always visible and editable next to it.
 */
@Component({
  selector: 'app-playbook-picker',
  standalone: true,
  imports: [
    FormsModule,
    RouterLink,
    HlmBadgeImports,
    HlmButton,
    HlmCheckboxImports,
    HlmInput,
    HlmLabel,
  ],
  changeDetection: ChangeDetectionStrategy.OnPush,
  host: { class: 'block' },
  templateUrl: './playbook-picker.component.html',
})
export class PlaybookPickerComponent implements OnInit {
  private readonly playbookService = inject(PlaybookService);
  private readonly destroyRef = inject(DestroyRef);

  readonly appliesTo = input.required<PlaybookUsage>();
  /** The parent's current rules text, so hand edits are detected and not overwritten. */
  readonly rules = input<string>('');
  /** The parent's current prompt text, same purpose. */
  readonly prompt = input<string>('');
  readonly idPrefix = input<string>('playbook');

  readonly applied = output<PlaybookApplication>();

  protected readonly playbooks = signal<Playbook[]>([]);
  protected readonly isLoading = signal(true);
  protected readonly loadError = signal<string | null>(null);
  protected readonly composeError = signal<string | null>(null);
  protected readonly isComposing = signal(false);

  protected readonly selectedRuleIds = signal<string[]>([]);
  protected readonly selectedTaskId = signal<string>('');
  protected readonly parameterValues = signal<Record<string, string>>({});

  protected readonly ruleOptions = computed(() => this.playbooks().filter(p => 'rule' === p.kind));
  protected readonly taskOptions = computed(() => this.playbooks().filter(p => 'task' === p.kind));
  protected readonly selectedTask = computed(
    () => this.taskOptions().find(task => task.id === this.selectedTaskId()) ?? null
  );

  /** What the last composition wrote; null until the first one, or when that field was not composed. */
  private readonly lastComposedRules = signal<string | null>(null);
  private readonly lastComposedPrompt = signal<string | null>(null);

  protected readonly rulesEdited = computed(() => {
    const last = this.lastComposedRules();
    return null === last ? '' !== this.rules().trim() : this.rules() !== last;
  });
  protected readonly promptEdited = computed(() => {
    const last = this.lastComposedPrompt();
    return null === last ? '' !== this.prompt().trim() : this.prompt() !== last;
  });

  private readonly composeRequests = new Subject<{ force: boolean }>();

  constructor() {
    this.composeRequests
      .pipe(
        debounceTime(150),
        switchMap(({ force }) => this.compose(force)),
        takeUntilDestroyed(this.destroyRef)
      )
      .subscribe();

    // A new task resets its parameters to their defaults — the previous task's values are
    // meaningless for it.
    effect(() => {
      const task = this.selectedTask();
      untracked(() => {
        const defaults: Record<string, string> = {};
        for (const parameter of task?.parameters ?? []) {
          defaults[parameter.name] = parameter.default ?? '';
        }
        this.parameterValues.set(defaults);
      });
    });
  }

  ngOnInit(): void {
    this.playbookService.list(this.appliesTo()).subscribe({
      next: playbooks => {
        this.playbooks.set(playbooks);
        this.isLoading.set(false);
        const defaults = playbooks.filter(p => 'rule' === p.kind && p.default).map(p => p.id);
        this.selectedRuleIds.set(defaults);
        if (defaults.length > 0 && !this.rulesEdited()) {
          this.requestCompose();
        }
      },
      error: () => {
        this.isLoading.set(false);
        this.loadError.set('Failed to load playbooks.');
      },
    });
  }

  isRuleSelected(id: string): boolean {
    return this.selectedRuleIds().includes(id);
  }

  toggleRule(id: string): void {
    this.selectedRuleIds.update(ids =>
      ids.includes(id) ? ids.filter(x => x !== id) : [...ids, id]
    );
    this.requestCompose();
  }

  selectTask(id: string): void {
    this.selectedTaskId.set(id);
    this.requestCompose();
  }

  updateParameter(name: string, value: string): void {
    this.parameterValues.update(values => ({ ...values, [name]: value }));
    this.requestCompose();
  }

  /** Explicitly overwrite a hand-edited field with the playbook text. */
  replaceEdited(): void {
    this.requestCompose(true);
  }

  private requestCompose(force = false): void {
    this.composeRequests.next({ force });
  }

  private compose(force: boolean): Observable<void> {
    const task = this.selectedTask();
    const ruleIds = this.ruleOptions()
      .map(rule => rule.id)
      .filter(id => this.selectedRuleIds().includes(id));

    this.isComposing.set(true);
    this.composeError.set(null);

    return this.playbookService
      .compose({
        appliesTo: this.appliesTo(),
        ruleIds,
        ...(task ? { taskId: task.id } : {}),
        parameters: task ? this.parameterValues() : {},
      })
      .pipe(
        map(composed => {
          this.isComposing.set(false);
          this.apply(composed, force);
        }),
        catchError((error: HttpErrorResponse) => {
          this.isComposing.set(false);
          this.composeError.set(
            (error.error as { message?: string } | null)?.message ?? 'Failed to compose the prompt.'
          );
          return of(undefined);
        })
      );
  }

  private apply(composed: ComposedPrompt, force: boolean): void {
    const application: PlaybookApplication = {
      engine: composed.engine,
      model: composed.model,
      sources: composed.sources,
    };

    if (force || !this.rulesEdited()) {
      application.rules = composed.rules ?? '';
      this.lastComposedRules.set(application.rules);
    }
    // Only a task produces prompt text; without one the user's own prompt stays untouched.
    if (null !== composed.prompt && (force || !this.promptEdited())) {
      application.prompt = composed.prompt;
      this.lastComposedPrompt.set(composed.prompt);
    }

    this.applied.emit(application);
  }
}
