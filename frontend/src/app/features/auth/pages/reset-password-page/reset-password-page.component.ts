import { Component, inject, OnInit } from '@angular/core';
import { FormBuilder, FormGroup, Validators, ReactiveFormsModule } from '@angular/forms';
import { CommonModule } from '@angular/common';
import { Router, ActivatedRoute, RouterLink } from '@angular/router';
import { NgIcon, provideIcons } from '@ng-icons/core';
import { lucideLock, lucideTriangleAlert, lucideLoader2, lucideCheck } from '@ng-icons/lucide';
import { HlmButton } from '@spartan-ng/helm/button';
import { HlmFieldImports } from '@spartan-ng/helm/field';
import { AuthService } from '../../../../core/services/auth.service';
import { ToastService } from '../../../../shared/services/toast.service';
import { PasswordInputComponent } from '../../../../shared/components/password-input/password-input.component';

@Component({
  selector: 'app-reset-password-page',
  standalone: true,
  imports: [
    ReactiveFormsModule,
    CommonModule,
    RouterLink,
    NgIcon,
    HlmButton,
    HlmFieldImports,
    PasswordInputComponent,
  ],
  providers: [provideIcons({ lucideLock, lucideTriangleAlert, lucideLoader2, lucideCheck })],
  templateUrl: './reset-password-page.component.html',
  styleUrl: './reset-password-page.component.scss',
})
export class ResetPasswordPageComponent implements OnInit {
  resetPasswordForm: FormGroup;

  isSubmitting = false;

  token: string | null = null;

  private fb = inject(FormBuilder);
  private authService = inject(AuthService);
  private router = inject(Router);
  private route = inject(ActivatedRoute);
  private toastService = inject(ToastService);

  constructor() {
    this.resetPasswordForm = this.fb.group(
      {
        password: ['', [Validators.required, Validators.minLength(8)]],
        confirmPassword: ['', [Validators.required]],
      },
      {
        validators: this.passwordMatchValidator,
      }
    );
  }

  ngOnInit(): void {
    this.token = this.route.snapshot.queryParamMap.get('token');

    if (!this.token) {
      this.toastService.add({
        severity: 'error',
        summary: 'Invalid password reset link',
        detail: 'Request a new one below.',
      });
      this.router.navigate(['/auth/forgot-password']);
    }
  }

  private passwordMatchValidator(form: FormGroup) {
    const password = form.get('password');
    const confirmPassword = form.get('confirmPassword');

    if (!password || !confirmPassword) {
      return null;
    }

    return password.value === confirmPassword.value ? null : { passwordMismatch: true };
  }

  onSubmit(): void {
    if (this.resetPasswordForm.invalid || !this.token) return;

    this.isSubmitting = true;
    const password = this.resetPasswordForm.value.password;

    this.authService.resetPassword(this.token, password).subscribe({
      next: () => {
        this.toastService.add({
          severity: 'success',
          summary: 'Password changed',
          detail: 'You can now log in.',
        });
        this.isSubmitting = false;

        setTimeout(() => {
          this.router.navigate(['/auth']);
        }, 1000);
      },
      error: () => {
        this.isSubmitting = false;
      },
    });
  }
}
