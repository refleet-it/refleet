import { Component, inject, OnInit, OnDestroy, DestroyRef, PLATFORM_ID } from '@angular/core';
import { takeUntilDestroyed } from '@angular/core/rxjs-interop';
import { FormBuilder, FormGroup, Validators, ReactiveFormsModule } from '@angular/forms';
import { isPlatformBrowser } from '@angular/common';
import { RouterLink } from '@angular/router';
import { NgIcon, provideIcons } from '@ng-icons/core';
import {
  lucideMail,
  lucideTriangleAlert,
  lucideLoader2,
  lucideSend,
  lucideClock,
  lucideRefreshCw,
} from '@ng-icons/lucide';
import { HlmButton } from '@spartan-ng/helm/button';
import { HlmFieldImports } from '@spartan-ng/helm/field';
import { HlmInput } from '@spartan-ng/helm/input';
import { AuthService } from '../../../../core/services/auth.service';

@Component({
  selector: 'app-forgot-password-page',
  standalone: true,
  imports: [ReactiveFormsModule, RouterLink, NgIcon, HlmButton, HlmFieldImports, HlmInput],
  providers: [
    provideIcons({
      lucideMail,
      lucideTriangleAlert,
      lucideLoader2,
      lucideSend,
      lucideClock,
      lucideRefreshCw,
    }),
  ],
  templateUrl: './forgot-password-page.component.html',
  styleUrl: './forgot-password-page.component.scss',
})
export class ForgotPasswordPageComponent implements OnInit, OnDestroy {
  private static readonly COOLDOWN_SECONDS = 60;
  private static readonly STORAGE_KEY = 'pwdResetCooldown';

  forgotPasswordForm: FormGroup;
  isSubmitting = false;
  emailSent = false;
  cooldownRemaining = 0;

  private fb = inject(FormBuilder);
  private authService = inject(AuthService);
  private destroyRef = inject(DestroyRef);
  private platformId = inject(PLATFORM_ID);

  private cooldownInterval: ReturnType<typeof setInterval> | null = null;

  constructor() {
    this.forgotPasswordForm = this.fb.group({
      email: ['', [Validators.required, Validators.email]],
    });
  }

  ngOnInit(): void {
    if (isPlatformBrowser(this.platformId)) {
      this.restoreCooldown();
    }
  }

  ngOnDestroy(): void {
    this.clearCooldownInterval();
  }

  get isCoolingDown(): boolean {
    return this.cooldownRemaining > 0;
  }

  onSubmit(): void {
    if (this.forgotPasswordForm.invalid || this.isCoolingDown) return;

    this.isSubmitting = true;
    const email = this.forgotPasswordForm.value.email;

    this.authService
      .requestPasswordReset(email)
      .pipe(takeUntilDestroyed(this.destroyRef))
      .subscribe({
        next: () => {
          this.isSubmitting = false;
          this.emailSent = true;
          this.startCooldown();
        },
        error: (error: { error?: { details?: { retry_after?: number } } }) => {
          this.isSubmitting = false;
          const retryAfter = error?.error?.details?.retry_after;
          if (retryAfter) {
            this.startCooldown(retryAfter);
          }
        },
      });
  }

  private startCooldown(seconds = ForgotPasswordPageComponent.COOLDOWN_SECONDS): void {
    const expiresAt = Date.now() + seconds * 1000;
    localStorage.setItem(ForgotPasswordPageComponent.STORAGE_KEY, expiresAt.toString());
    this.cooldownRemaining = seconds;
    this.runCooldownTick();
  }

  private restoreCooldown(): void {
    const stored = localStorage.getItem(ForgotPasswordPageComponent.STORAGE_KEY);
    if (!stored) return;

    const remaining = Math.ceil((parseInt(stored, 10) - Date.now()) / 1000);
    if (remaining > 0) {
      this.cooldownRemaining = remaining;
      this.runCooldownTick();
    } else {
      localStorage.removeItem(ForgotPasswordPageComponent.STORAGE_KEY);
    }
  }

  private runCooldownTick(): void {
    this.clearCooldownInterval();
    this.cooldownInterval = setInterval(() => {
      this.cooldownRemaining--;
      if (this.cooldownRemaining <= 0) {
        this.cooldownRemaining = 0;
        this.emailSent = false;
        localStorage.removeItem(ForgotPasswordPageComponent.STORAGE_KEY);
        this.clearCooldownInterval();
      }
    }, 1000);
  }

  private clearCooldownInterval(): void {
    if (this.cooldownInterval !== null) {
      clearInterval(this.cooldownInterval);
      this.cooldownInterval = null;
    }
  }
}
