import { Component, inject, signal } from '@angular/core';
import { FormsModule } from '@angular/forms';
import { Router } from '@angular/router';
import { HlmButton } from '@spartan-ng/helm/button';
import { HlmCardImports } from '@spartan-ng/helm/card';
import { HlmFieldImports } from '@spartan-ng/helm/field';
import { HlmInput } from '@spartan-ng/helm/input';
import { HlmLabel } from '@spartan-ng/helm/label';
import { OrganizationService } from '../../../../core/services/organization.service';

@Component({
  selector: 'app-create-organization-page',
  standalone: true,
  imports: [FormsModule, HlmButton, HlmCardImports, HlmFieldImports, HlmInput, HlmLabel],
  templateUrl: './create-organization-page.component.html',
})
export class CreateOrganizationPageComponent {
  private readonly organizationService = inject(OrganizationService);
  private readonly router = inject(Router);

  protected readonly name = signal('');
  protected readonly isSubmitting = signal(false);
  protected readonly errorMessage = signal<string | null>(null);

  onSubmit(): void {
    const name = this.name().trim();
    if (!name || this.isSubmitting()) {
      return;
    }

    this.isSubmitting.set(true);
    this.errorMessage.set(null);

    this.organizationService.create(name).subscribe({
      next: () => {
        void this.router.navigate(['/dashboard']);
      },
      error: () => {
        this.isSubmitting.set(false);
        this.errorMessage.set('Could not create the organization. Please try again.');
      },
    });
  }
}
