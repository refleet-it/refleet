import { Component, computed, inject, OnInit, signal } from '@angular/core';
import { FormsModule } from '@angular/forms';
import { Router, RouterLink } from '@angular/router';
import { HttpErrorResponse } from '@angular/common/http';
import { HlmButton } from '@spartan-ng/helm/button';
import { HlmCardImports } from '@spartan-ng/helm/card';
import { HlmFieldImports } from '@spartan-ng/helm/field';
import { HlmInput } from '@spartan-ng/helm/input';
import { HlmLabel } from '@spartan-ng/helm/label';
import { HlmSkeletonImports } from '@spartan-ng/helm/skeleton';
import { OrganizationOverview } from '../../../../core/models/organization.model';
import { OrganizationService } from '../../../../core/services/organization.service';
import { ToastService } from '../../../../shared/services/toast.service';

const EMAIL_PATTERN = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;

@Component({
  selector: 'app-team-new-page',
  standalone: true,
  imports: [
    FormsModule,
    RouterLink,
    HlmButton,
    HlmCardImports,
    HlmFieldImports,
    HlmInput,
    HlmLabel,
    HlmSkeletonImports,
  ],
  templateUrl: './team-new-page.component.html',
})
export class TeamNewPageComponent implements OnInit {
  private readonly organizationService = inject(OrganizationService);
  private readonly router = inject(Router);
  private readonly toastService = inject(ToastService);

  protected readonly overview = signal<OrganizationOverview | null>(null);
  protected readonly isLoading = signal(true);

  protected readonly inviteEmail = signal('');
  protected readonly isSendingInvitation = signal(false);
  protected readonly inviteError = signal<string | null>(null);
  protected readonly submitAttempted = signal(false);

  /** Shown only after a submit attempt, so typing an address does not flash an error at every key. */
  protected readonly showEmailError = computed(
    () => this.submitAttempted() && !EMAIL_PATTERN.test(this.inviteEmail().trim())
  );

  ngOnInit(): void {
    this.loadOverview();
  }

  get isOwner(): boolean {
    return 'owner' === this.overview()?.role;
  }

  sendInvitation(): void {
    const email = this.inviteEmail().trim();
    this.submitAttempted.set(true);

    if (this.isSendingInvitation() || !EMAIL_PATTERN.test(email)) {
      return;
    }

    this.isSendingInvitation.set(true);
    this.inviteError.set(null);

    this.organizationService.sendInvitation(email).subscribe({
      next: () => {
        this.isSendingInvitation.set(false);
        this.toastService.add({
          severity: 'success',
          summary: 'Invitation sent',
          detail: `An invitation has been emailed to ${email}.`,
        });
        void this.router.navigate(['/dashboard/team']);
      },
      error: (error: HttpErrorResponse) => {
        this.isSendingInvitation.set(false);
        this.inviteError.set(this.resolveInviteError(error));
      },
    });
  }

  private loadOverview(): void {
    this.isLoading.set(true);
    this.organizationService.getMyOrganization().subscribe({
      next: overview => {
        this.overview.set(overview);
        this.isLoading.set(false);
      },
      error: () => {
        this.isLoading.set(false);
      },
    });
  }

  private resolveInviteError(error: HttpErrorResponse): string {
    if (409 === error.status) {
      return 'This person already belongs to an organization, or already has a pending invitation.';
    }
    if (403 === error.status) {
      return 'Only the organization owner can invite teammates.';
    }
    return 'Could not send the invitation. Please try again.';
  }
}
