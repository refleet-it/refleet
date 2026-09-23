import { Component, inject, signal } from '@angular/core';
import { toSignal } from '@angular/core/rxjs-interop';
import { FormBuilder, FormGroup, Validators, ReactiveFormsModule } from '@angular/forms';
import { CommonModule } from '@angular/common';
import { HttpErrorResponse } from '@angular/common/http';
import { map } from 'rxjs';
import { Router, RouterLink } from '@angular/router';
import { NgIcon, provideIcons } from '@ng-icons/core';
import {
  lucideMail,
  lucideLock,
  lucideTriangleAlert,
  lucideLoader2,
  lucideUserPlus,
} from '@ng-icons/lucide';
import { HlmButton } from '@spartan-ng/helm/button';
import { HlmInput } from '@spartan-ng/helm/input';
import { HlmCheckbox } from '@spartan-ng/helm/checkbox';
import { HlmFieldImports } from '@spartan-ng/helm/field';
import { AuthService } from '../../../../core/services/auth.service';
import { InstanceConfigService } from '../../../../core/services/instance-config.service';
import { PasswordInputComponent } from '../../../../shared/components/password-input/password-input.component';

@Component({
  selector: 'app-register-page',
  standalone: true,
  imports: [
    ReactiveFormsModule,
    CommonModule,
    RouterLink,
    NgIcon,
    HlmButton,
    HlmInput,
    HlmCheckbox,
    HlmFieldImports,
    PasswordInputComponent,
  ],
  providers: [
    provideIcons({ lucideMail, lucideLock, lucideTriangleAlert, lucideLoader2, lucideUserPlus }),
  ],
  templateUrl: './register-page.component.html',
  styleUrl: './register-page.component.scss',
})
export class RegisterPageComponent {
  registerForm: FormGroup;
  isSubmitting = false;
  protected readonly termsUrl = toSignal(
    inject(InstanceConfigService)
      .config()
      .pipe(map(config => config.termsUrl)),
    { initialValue: '' }
  );

  /**
   * Backend validation that names a field belongs next to that field, not only in a toast the
   * user has to remember while retyping. Cleared on edit so a stale message cannot outlive it.
   */
  emailServerError = signal<string | null>(null);

  private fb = inject(FormBuilder);
  private authService = inject(AuthService);
  private router = inject(Router);

  constructor() {
    this.registerForm = this.fb.group({
      email: ['', [Validators.required, Validators.email]],
      password: ['', [Validators.required, Validators.minLength(8)]],
      termsAccepted: [false, [Validators.requiredTrue]],
      marketingConsent: [false],
    });
  }

  onSubmit(): void {
    if (this.registerForm.invalid) return;

    this.isSubmitting = true;
    this.emailServerError.set(null);
    const { email, password, termsAccepted, marketingConsent } = this.registerForm.value;

    this.authService.register(email, password, termsAccepted, marketingConsent).subscribe({
      next: () => {
        this.router.navigate(['/auth/registration-pending']);
      },
      error: (error: HttpErrorResponse) => {
        this.isSubmitting = false;
        const body = error.error as { field?: string; message?: string } | null;
        if ('email' === body?.field) {
          this.emailServerError.set(body.message || 'This email is already registered.');
        }
      },
    });
  }
}
