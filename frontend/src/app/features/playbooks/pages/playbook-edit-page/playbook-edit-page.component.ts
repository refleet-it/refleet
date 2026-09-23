import { Component, computed, inject, OnInit, signal } from '@angular/core';
import { HttpErrorResponse } from '@angular/common/http';
import { FormsModule } from '@angular/forms';
import { ActivatedRoute, Router, RouterLink } from '@angular/router';
import { HlmBadgeImports } from '@spartan-ng/helm/badge';
import { HlmButton } from '@spartan-ng/helm/button';
import { HlmCardImports } from '@spartan-ng/helm/card';
import { HlmCheckboxImports } from '@spartan-ng/helm/checkbox';
import { HlmFieldImports } from '@spartan-ng/helm/field';
import { HlmInput } from '@spartan-ng/helm/input';
import { HlmLabel } from '@spartan-ng/helm/label';
import { HlmTextarea } from '@spartan-ng/helm/textarea';
import { AiAgentEngine, AiModel } from '../../../../core/models/criteria.model';
import {
  Playbook,
  PLAYBOOK_APPLIES_TO_LABELS,
  PlaybookAppliesTo,
  PlaybookKind,
  PlaybookParameter,
  PlaybookPayload,
} from '../../../../core/models/playbook.model';
import { AvailableModels } from '../../../../core/models/runner.model';
import { PlaybookService } from '../../../../core/services/playbook.service';
import { RunnerService } from '../../../../core/services/runner.service';

interface PlaybookForm {
  name: string;
  description: string;
  kind: PlaybookKind;
  appliesTo: PlaybookAppliesTo;
  body: string;
  default: boolean;
  parameters: PlaybookParameter[];
  engine: AiAgentEngine | '';
  model: AiModel | '';
}

interface FormFieldErrors {
  name?: string;
  body?: string;
  parameters?: string;
}

const EMPTY_FORM: PlaybookForm = {
  name: '',
  description: '',
  kind: 'rule',
  appliesTo: 'change',
  body: '',
  default: false,
  parameters: [],
  engine: '',
  model: '',
};

const EMPTY_AVAILABLE_MODELS: AvailableModels = { claude: [], kiro: [] };

const PARAMETER_NAME = /^[a-zA-Z][a-zA-Z0-9_]{0,39}$/;

/**
 * One page for three cases: a new playbook, editing the organization's own, and reading a
 * built-in one (read-only, with "Duplicate" as the way to make it editable). `?from=<id>`
 * starts a new playbook pre-filled from an existing one.
 */
@Component({
  selector: 'app-playbook-edit-page',
  standalone: true,
  imports: [
    FormsModule,
    RouterLink,
    HlmBadgeImports,
    HlmButton,
    HlmCardImports,
    HlmCheckboxImports,
    HlmFieldImports,
    HlmInput,
    HlmLabel,
    HlmTextarea,
  ],
  templateUrl: './playbook-edit-page.component.html',
})
export class PlaybookEditPageComponent implements OnInit {
  private readonly playbookService = inject(PlaybookService);
  private readonly runnerService = inject(RunnerService);
  private readonly route = inject(ActivatedRoute);
  private readonly router = inject(Router);

  protected readonly appliesToOptions = Object.entries(PLAYBOOK_APPLIES_TO_LABELS) as [
    PlaybookAppliesTo,
    string,
  ][];

  protected readonly playbookId = signal<string | null>(null);
  protected readonly source = signal<Playbook | null>(null);
  protected readonly form = signal<PlaybookForm>({ ...EMPTY_FORM });
  protected readonly availableModels = signal<AvailableModels>(EMPTY_AVAILABLE_MODELS);

  protected readonly isLoading = signal(false);
  protected readonly loadError = signal<string | null>(null);
  protected readonly isSaving = signal(false);
  protected readonly saveError = signal<string | null>(null);
  protected readonly submitAttempted = signal(false);

  protected readonly isNew = computed(() => null === this.playbookId());
  protected readonly isReadOnly = computed(() => this.source()?.builtIn ?? false);
  protected readonly fieldErrors = computed(() => this.validate(this.form()));

  /** `{{name}}` placeholders used in the body that have no declared parameter — a hint, not an error. */
  protected readonly undeclaredPlaceholders = computed(() => {
    const declared = new Set(this.form().parameters.map(parameter => parameter.name));
    const used = new Set<string>();
    for (const match of this.form().body.matchAll(/\{\{\s*([a-zA-Z][a-zA-Z0-9_]*)\s*\}\}/g)) {
      const name = match[1];
      if (name && !declared.has(name)) {
        used.add(name);
      }
    }
    return [...used];
  });

  ngOnInit(): void {
    this.loadAvailableModels();

    const id = this.route.snapshot.paramMap.get('id');
    const from = this.route.snapshot.queryParamMap.get('from');
    if (id) {
      this.playbookId.set(id);
      this.load(id, false);
    } else if (from) {
      this.load(from, true);
    }
  }

  updateForm<K extends keyof PlaybookForm>(field: K, value: PlaybookForm[K]): void {
    this.form.update(current => ({ ...current, [field]: value }));
  }

  /** Rules cannot have parameters, an engine or a default-less model; switching kind drops what no longer applies. */
  updateKind(kind: PlaybookKind): void {
    this.form.update(current => ({
      ...current,
      kind,
      ...('rule' === kind
        ? { parameters: [], engine: '' as const, model: '' as const }
        : { default: false }),
    }));
  }

  updateEngine(engine: AiAgentEngine | ''): void {
    this.form.update(current => ({ ...current, engine, model: '' }));
  }

  modelsForEngine(engine: AiAgentEngine | ''): string[] {
    return '' === engine ? [] : this.availableModels()[engine];
  }

  addParameter(): void {
    this.form.update(current => ({
      ...current,
      parameters: [...current.parameters, { name: '', label: '', default: null, required: true }],
    }));
  }

  updateParameter<K extends keyof PlaybookParameter>(
    index: number,
    field: K,
    value: PlaybookParameter[K]
  ): void {
    this.form.update(current => ({
      ...current,
      parameters: current.parameters.map((parameter, i) =>
        i === index ? { ...parameter, [field]: value } : parameter
      ),
    }));
  }

  removeParameter(index: number): void {
    this.form.update(current => ({
      ...current,
      parameters: current.parameters.filter((_, i) => i !== index),
    }));
  }

  insertPlaceholder(name: string): void {
    this.form.update(current => ({ ...current, body: `${current.body}{{${name}}}` }));
  }

  showFieldError(field: keyof FormFieldErrors): boolean {
    return this.submitAttempted() && !!this.fieldErrors()[field];
  }

  save(): void {
    if (this.isSaving() || this.isReadOnly()) {
      return;
    }
    this.submitAttempted.set(true);
    if (Object.keys(this.fieldErrors()).length > 0) {
      return;
    }

    this.isSaving.set(true);
    this.saveError.set(null);
    const payload = this.toPayload(this.form());
    const id = this.playbookId();
    const request =
      null === id ? this.playbookService.create(payload) : this.playbookService.update(id, payload);

    request.subscribe({
      next: () => {
        this.isSaving.set(false);
        void this.router.navigate(['/dashboard/playbooks']);
      },
      error: (error: HttpErrorResponse) => {
        this.isSaving.set(false);
        this.saveError.set(
          (error.error as { message?: string } | null)?.message ?? 'Failed to save the playbook.'
        );
      },
    });
  }

  private load(id: string, asCopy: boolean): void {
    this.isLoading.set(true);
    this.loadError.set(null);
    this.playbookService.get(id).subscribe({
      next: playbook => {
        this.source.set(asCopy ? null : playbook);
        this.form.set({
          name: asCopy ? `${playbook.name} (copy)` : playbook.name,
          description: playbook.description ?? '',
          kind: playbook.kind,
          appliesTo: playbook.appliesTo,
          body: playbook.body,
          default: playbook.default,
          parameters: playbook.parameters.map(parameter => ({ ...parameter })),
          engine: playbook.engine ?? '',
          model: playbook.model ?? '',
        });
        this.isLoading.set(false);
      },
      error: () => {
        this.isLoading.set(false);
        this.loadError.set('Could not load the playbook.');
      },
    });
  }

  private validate(form: PlaybookForm): FormFieldErrors {
    const errors: FormFieldErrors = {};
    if (!form.name.trim()) {
      errors.name = 'Name is required.';
    }
    if (!form.body.trim()) {
      errors.body = 'The prompt text is required.';
    }
    const names = form.parameters.map(parameter => parameter.name);
    if (names.some(name => !PARAMETER_NAME.test(name))) {
      errors.parameters =
        'Parameter names must start with a letter and contain only letters, digits or underscores.';
    } else if (new Set(names).size !== names.length) {
      errors.parameters = 'Parameter names must be unique.';
    }
    return errors;
  }

  private toPayload(form: PlaybookForm): PlaybookPayload {
    const isTask = 'task' === form.kind;
    return {
      name: form.name.trim(),
      ...(form.description.trim() ? { description: form.description.trim() } : {}),
      kind: form.kind,
      appliesTo: form.appliesTo,
      body: form.body,
      default: !isTask && form.default,
      parameters: isTask
        ? form.parameters.map(parameter => ({
            name: parameter.name.trim(),
            label: parameter.label.trim() || parameter.name.trim(),
            default: parameter.default?.trim() ? parameter.default.trim() : null,
            required: parameter.required,
          }))
        : [],
      ...(isTask && form.engine ? { engine: form.engine } : {}),
      ...(isTask && form.engine && form.model ? { model: form.model } : {}),
    };
  }

  private loadAvailableModels(): void {
    this.runnerService.availableModels().subscribe({
      next: models => this.availableModels.set(models),
      error: () => this.availableModels.set(EMPTY_AVAILABLE_MODELS),
    });
  }
}
