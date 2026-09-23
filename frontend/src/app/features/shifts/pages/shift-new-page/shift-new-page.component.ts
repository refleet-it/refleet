import { Component, computed, inject, OnInit, signal } from '@angular/core';
import { FormsModule } from '@angular/forms';
import { ActivatedRoute, Router, RouterLink } from '@angular/router';
import { HttpErrorResponse } from '@angular/common/http';
import { HlmButton } from '@spartan-ng/helm/button';
import { HlmCardImports } from '@spartan-ng/helm/card';
import { HlmFieldImports } from '@spartan-ng/helm/field';
import { HlmInput } from '@spartan-ng/helm/input';
import { HlmLabel } from '@spartan-ng/helm/label';
import { HlmRadioGroupImports } from '@spartan-ng/helm/radio-group';
import { CreateShiftPayload } from '../../../../core/models/shift.model';
import {
  QualificationOverview,
  QualificationTargetOverview,
} from '../../../../core/models/qualification.model';
import { ProjectOverview } from '../../../../core/models/project.model';
import { ShiftService } from '../../../../core/services/shift.service';
import { QualificationService } from '../../../../core/services/qualification.service';
import { ProjectService } from '../../../../core/services/project.service';
import { ProjectTreePickerComponent } from '../../../../shared/components/project-tree-picker/project-tree-picker.component';
import { ProjectTreeItem } from '../../../../shared/components/project-tree-picker/project-tree';

type ShiftSourceMode = 'qualification' | 'manual';

interface FormFieldErrors {
  title?: string;
  qualificationId?: string;
  projects?: string;
}

@Component({
  selector: 'app-shift-new-page',
  standalone: true,
  imports: [
    FormsModule,
    RouterLink,
    HlmButton,
    HlmCardImports,
    HlmFieldImports,
    HlmInput,
    HlmLabel,
    HlmRadioGroupImports,
    ProjectTreePickerComponent,
  ],
  templateUrl: './shift-new-page.component.html',
})
export class ShiftNewPageComponent implements OnInit {
  private readonly shiftService = inject(ShiftService);
  private readonly qualificationService = inject(QualificationService);
  private readonly projectService = inject(ProjectService);
  private readonly router = inject(Router);
  private readonly route = inject(ActivatedRoute);

  protected readonly title = signal('');
  protected readonly description = signal('');
  protected readonly sourceMode = signal<ShiftSourceMode>('qualification');

  protected readonly completedQualifications = signal<QualificationOverview[]>([]);
  protected readonly isLoadingQualifications = signal(true);
  protected readonly qualificationsFailed = signal(false);
  protected readonly selectedQualificationId = signal<string>('');

  protected readonly useAllQualifiedTargets = signal(true);
  protected readonly qualificationTargets = signal<QualificationTargetOverview[]>([]);
  protected readonly isLoadingQualificationTargets = signal(false);
  protected readonly qualificationTargetsFailed = signal(false);
  protected readonly selectedQualificationTargetProjectIds = signal<string[]>([]);
  protected readonly qualificationTargetItems = computed<ProjectTreeItem[]>(() =>
    this.qualificationTargets().map(target => ({
      id: target.projectId,
      name: target.projectSnapshot.name,
      path: target.projectSnapshot.path,
      hint: target.status,
    }))
  );

  protected readonly projects = signal<ProjectOverview[]>([]);
  protected readonly isLoadingProjects = signal(true);
  // See qualification-new-page: an empty picker after a failed load reads as "you have none".
  protected readonly projectsFailed = signal(false);
  protected readonly selectedProjectIds = signal<string[]>([]);

  protected readonly isCreating = signal(false);
  protected readonly createError = signal<string | null>(null);
  protected readonly submitAttempted = signal(false);

  protected readonly fieldErrors = computed<FormFieldErrors>(() => {
    const errors: FormFieldErrors = {};

    if (!this.title().trim()) {
      errors.title = 'Title is required.';
    }

    if ('qualification' === this.sourceMode()) {
      if (!this.selectedQualificationId()) {
        errors.qualificationId = 'Select a qualification.';
      } else if (
        !this.useAllQualifiedTargets() &&
        0 === this.selectedQualificationTargetProjectIds().length
      ) {
        errors.projects = 'Select at least one target.';
      }
    } else if (0 === this.selectedProjectIds().length) {
      errors.projects = 'Select at least one project for a manual shift.';
    }

    return errors;
  });

  ngOnInit(): void {
    this.loadCompletedQualifications();
    this.loadProjects();
  }

  setSourceMode(mode: ShiftSourceMode): void {
    this.sourceMode.set(mode);
  }

  selectQualification(qualificationId: string): void {
    this.selectedQualificationId.set(qualificationId);
    this.useAllQualifiedTargets.set(true);
    this.qualificationTargets.set([]);
    this.selectedQualificationTargetProjectIds.set([]);

    if (qualificationId) {
      this.loadQualificationTargets(qualificationId);
    }
  }

  setUseAllQualifiedTargets(useAll: boolean): void {
    this.useAllQualifiedTargets.set(useAll);
    if (!useAll) {
      this.selectedQualificationTargetProjectIds.set(
        this.qualificationTargets()
          .filter(target => 'qualified' === target.status)
          .map(target => target.projectId)
      );
    }
  }

  /** Whether a field's error should currently be rendered — only once a submit has been attempted. */
  showFieldError(field: keyof FormFieldErrors): boolean {
    return this.submitAttempted() && !!this.fieldErrors()[field];
  }

  createShift(): void {
    if (this.isCreating()) {
      return;
    }

    this.submitAttempted.set(true);
    if (Object.keys(this.fieldErrors()).length > 0) {
      return;
    }

    this.isCreating.set(true);
    this.createError.set(null);

    this.shiftService.create(this.toPayload()).subscribe({
      next: createdShift => {
        this.isCreating.set(false);
        void this.router.navigate(['/dashboard/shifts', createdShift.id]);
      },
      error: (error: HttpErrorResponse) => {
        this.isCreating.set(false);
        this.createError.set(this.resolveCreateError(error));
      },
    });
  }

  private toPayload(): CreateShiftPayload {
    const payload: CreateShiftPayload = {
      title: this.title().trim(),
      description: this.description().trim() || undefined,
    };

    if ('qualification' === this.sourceMode()) {
      payload.qualificationId = this.selectedQualificationId();
      if (!this.useAllQualifiedTargets()) {
        payload.projectIds = this.selectedQualificationTargetProjectIds();
      }
    } else {
      payload.projectIds = this.selectedProjectIds();
    }

    return payload;
  }

  /** Picks up ?qualificationId= from the qualification detail page's "Create Shift" link. */
  private applyQualificationIdFromQueryParams(): void {
    const qualificationId = this.route.snapshot.queryParamMap.get('qualificationId');
    if (qualificationId) {
      this.selectQualification(qualificationId);
    }
  }

  private loadCompletedQualifications(): void {
    this.isLoadingQualifications.set(true);
    this.qualificationsFailed.set(false);
    this.qualificationService.list(1, 100).subscribe({
      next: response => {
        this.completedQualifications.set(response.items.filter(q => 'completed' === q.status));
        this.isLoadingQualifications.set(false);
        this.applyQualificationIdFromQueryParams();
      },
      error: () => {
        this.qualificationsFailed.set(true);
        this.isLoadingQualifications.set(false);
      },
    });
  }

  private loadQualificationTargets(qualificationId: string): void {
    this.isLoadingQualificationTargets.set(true);
    this.qualificationTargetsFailed.set(false);
    this.qualificationService.listTargets(qualificationId, 1, 100).subscribe({
      next: response => {
        this.qualificationTargets.set(response.items);
        this.isLoadingQualificationTargets.set(false);
      },
      error: () => {
        this.qualificationTargetsFailed.set(true);
        this.isLoadingQualificationTargets.set(false);
      },
    });
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

  private resolveCreateError(error: HttpErrorResponse): string {
    if (409 === error.status) {
      return 'Your account does not belong to an organization yet.';
    }
    if (422 === error.status) {
      return 'Check the shift data — no target projects could be resolved.';
    }
    return 'Failed to create the shift. Please try again.';
  }
}
