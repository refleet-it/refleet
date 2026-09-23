import { Component, computed, inject, OnInit, signal } from '@angular/core';
import { FormsModule } from '@angular/forms';
import { Router, RouterLink } from '@angular/router';
import { HttpErrorResponse } from '@angular/common/http';
import { HlmButton } from '@spartan-ng/helm/button';
import { HlmCardImports } from '@spartan-ng/helm/card';
import { HlmFieldImports } from '@spartan-ng/helm/field';
import { HlmInput } from '@spartan-ng/helm/input';
import { HlmLabel } from '@spartan-ng/helm/label';
import { HlmTextarea } from '@spartan-ng/helm/textarea';
import { AiAgentEngine, AiModel } from '../../../../core/models/criteria.model';
import {
  CreateQualificationPayload,
  QualificationRerunPrefill,
} from '../../../../core/models/qualification.model';
import { AvailableModels } from '../../../../core/models/runner.model';
import { ProjectOverview } from '../../../../core/models/project.model';
import { QualificationService } from '../../../../core/services/qualification.service';
import { ProjectService } from '../../../../core/services/project.service';
import { RunnerService } from '../../../../core/services/runner.service';
import { ProjectTreePickerComponent } from '../../../../shared/components/project-tree-picker/project-tree-picker.component';
import {
  PlaybookApplication,
  PlaybookPickerComponent,
} from '../../../../shared/components/playbook-picker/playbook-picker.component';
import { PromptPreviewComponent } from '../../../../shared/components/prompt-preview/prompt-preview.component';
import { PromptSource } from '../../../../core/models/playbook.model';
import { Observable } from 'rxjs';

interface CreateQualificationForm {
  title: string;
  description: string;
  qualificationEngine: AiAgentEngine;
  qualificationPrompt: string;
  qualificationModel: AiModel | '';
  qualificationRules: string;
  qualificationSources: PromptSource[];
}

interface FormFieldErrors {
  title?: string;
  qualificationPrompt?: string;
}

const EMPTY_FORM: CreateQualificationForm = {
  title: '',
  description: '',
  qualificationEngine: 'claude',
  qualificationPrompt: '',
  qualificationModel: '',
  qualificationRules: '',
  qualificationSources: [],
};

const EMPTY_AVAILABLE_MODELS: AvailableModels = { claude: [], kiro: [] };

@Component({
  selector: 'app-qualification-new-page',
  standalone: true,
  imports: [
    FormsModule,
    RouterLink,
    HlmButton,
    HlmCardImports,
    HlmFieldImports,
    HlmInput,
    HlmLabel,
    HlmTextarea,
    ProjectTreePickerComponent,
    PlaybookPickerComponent,
    PromptPreviewComponent,
  ],
  templateUrl: './qualification-new-page.component.html',
})
export class QualificationNewPageComponent implements OnInit {
  private readonly qualificationService = inject(QualificationService);
  private readonly projectService = inject(ProjectService);
  private readonly runnerService = inject(RunnerService);
  private readonly router = inject(Router);

  protected readonly projects = signal<ProjectOverview[]>([]);
  protected readonly isLoadingProjects = signal(true);
  // Distinguishes "the list came back empty" from "the list never arrived": without it a failed
  // load renders the empty state, which tells the user to go and connect GitLab.
  protected readonly projectsFailed = signal(false);
  protected readonly selectedProjectIds = signal<string[]>([]);

  protected readonly form = signal<CreateQualificationForm>({ ...EMPTY_FORM });
  protected readonly availableModels = signal<AvailableModels>(EMPTY_AVAILABLE_MODELS);
  protected readonly isCreating = signal(false);
  protected readonly createError = signal<string | null>(null);

  protected readonly submitAttempted = signal(false);
  protected readonly fieldErrors = computed(() => this.validate(this.form()));

  ngOnInit(): void {
    this.applyRerunPrefill();
    this.loadProjects();
    this.loadAvailableModels();
  }

  updateForm<K extends keyof CreateQualificationForm>(
    field: K,
    value: CreateQualificationForm[K]
  ): void {
    this.form.update(current => ({ ...current, [field]: value }));
  }

  /** Playbook text lands in the editable fields; a task's preferred agent/model is only a suggestion. */
  applyPlaybooks(application: PlaybookApplication): void {
    this.form.update(current => ({
      ...current,
      ...(undefined !== application.rules ? { qualificationRules: application.rules } : {}),
      ...(undefined !== application.prompt ? { qualificationPrompt: application.prompt } : {}),
      ...(application.engine
        ? { qualificationEngine: application.engine, qualificationModel: application.model ?? '' }
        : {}),
      qualificationSources: application.sources,
    }));
  }

  /** Bound as a value so the preview component can call it with the form as it is at that moment. */
  protected readonly previewPrompt = (): Observable<string> =>
    this.qualificationService.previewPrompt({
      qualificationPrompt: this.form().qualificationPrompt,
      qualificationRules: this.form().qualificationRules || undefined,
    });

  /** A previously selected model may not be valid for the newly selected agent. */
  updateQualificationEngine(engine: AiAgentEngine): void {
    this.form.update(current => ({
      ...current,
      qualificationEngine: engine,
      qualificationModel: '',
    }));
  }

  modelsForEngine(engine: AiAgentEngine): string[] {
    return this.availableModels()[engine];
  }

  createQualification(): void {
    if (this.isCreating()) {
      return;
    }

    this.submitAttempted.set(true);
    if (Object.keys(this.fieldErrors()).length > 0) {
      return;
    }

    const form = this.form();
    this.isCreating.set(true);
    this.createError.set(null);

    this.qualificationService.create(this.toPayload(form)).subscribe({
      next: createdQualification => {
        this.isCreating.set(false);
        void this.router.navigate(['/dashboard/qualifications', createdQualification.id]);
      },
      error: (error: HttpErrorResponse) => {
        this.isCreating.set(false);
        this.createError.set(this.resolveCreateError(error));
      },
    });
  }

  /** Whether a field's error should currently be rendered — only once a submit has been attempted. */
  showFieldError(field: keyof FormFieldErrors): boolean {
    return this.submitAttempted() && !!this.fieldErrors()[field];
  }

  private validate(form: CreateQualificationForm): FormFieldErrors {
    const errors: FormFieldErrors = {};

    if (!form.title.trim()) {
      errors.title = 'Title is required.';
    }

    if (!form.qualificationPrompt.trim()) {
      errors.qualificationPrompt = 'AI prompt is required.';
    }

    return errors;
  }

  private toPayload(form: CreateQualificationForm): CreateQualificationPayload {
    const payload: CreateQualificationPayload = {
      title: form.title.trim(),
      description: form.description.trim() || undefined,
      qualificationMode: 'ai',
      qualificationPrompt: form.qualificationPrompt.trim(),
      qualificationEngine: form.qualificationEngine,
    };

    if (form.qualificationModel) {
      payload.qualificationModel = form.qualificationModel;
    }

    if (form.qualificationRules.trim()) {
      payload.qualificationRules = form.qualificationRules;
    }
    payload.qualificationSources = form.qualificationSources;

    const selectedIds = this.selectedProjectIds();
    if (selectedIds.length > 0) {
      payload.projectIds = selectedIds;
    }

    return payload;
  }

  /**
   * Picks up the prefill state set by the qualification detail page's "Run again" link
   * ([state] router bindings land in `history.state`, not `Router.getCurrentNavigation()`,
   * by the time this component's ngOnInit runs).
   */
  private applyRerunPrefill(): void {
    const prefill = (history.state as { prefill?: QualificationRerunPrefill }).prefill;
    if (!prefill) {
      return;
    }

    this.form.set({
      title: prefill.title,
      description: prefill.description,
      qualificationEngine: prefill.qualificationEngine ?? 'claude',
      qualificationPrompt: prefill.qualificationPrompt,
      qualificationModel: prefill.qualificationModel ?? '',
      qualificationRules: prefill.qualificationRules ?? '',
      qualificationSources: prefill.qualificationSources ?? [],
    });
    this.selectedProjectIds.set(prefill.projectIds);
  }

  private loadProjects(): void {
    this.isLoadingProjects.set(true);
    this.projectsFailed.set(false);
    this.projectService.getProjects(1, 100).subscribe({
      next: response => {
        this.projects.set(response.items);
        this.isLoadingProjects.set(false);
      },
      error: () => {
        this.projectsFailed.set(true);
        this.isLoadingProjects.set(false);
      },
    });
  }

  /** Best-effort: an empty/unavailable list just leaves the Model field's options empty rather than blocking the page. */
  private loadAvailableModels(): void {
    this.runnerService.availableModels().subscribe({
      next: models => this.availableModels.set(models),
      error: () => this.availableModels.set(EMPTY_AVAILABLE_MODELS),
    });
  }

  private resolveCreateError(error: HttpErrorResponse): string {
    if (409 === error.status) {
      return 'Your account does not belong to an organization yet.';
    }
    if (422 === error.status) {
      return 'Check the qualification data — the criteria or project list are invalid.';
    }
    return 'Failed to create the qualification. Please try again.';
  }
}
