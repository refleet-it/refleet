import { Component, inject, OnInit } from '@angular/core';
import { toSignal } from '@angular/core/rxjs-interop';
import { FormBuilder, FormGroup, Validators, ReactiveFormsModule } from '@angular/forms';
import { CommonModule } from '@angular/common';
import { ActivatedRoute, Router, RouterLink } from '@angular/router';
import { NgIcon, provideIcons } from '@ng-icons/core';
import {
  lucideMail,
  lucideLock,
  lucideTriangleAlert,
  lucideLoader2,
  lucideLogIn,
} from '@ng-icons/lucide';
import { HlmButton } from '@spartan-ng/helm/button';
import { HlmInput } from '@spartan-ng/helm/input';
import { HlmCheckbox } from '@spartan-ng/helm/checkbox';
import { HlmFieldImports } from '@spartan-ng/helm/field';

import { AuthService } from '../../../../core/services/auth.service';
import { InstanceConfigService } from '../../../../core/services/instance-config.service';
import { PasswordInputComponent } from '../../../../shared/components/password-input/password-input.component';

@Component({
  selector: 'app-auth-main-page',
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
    provideIcons({ lucideMail, lucideLock, lucideTriangleAlert, lucideLoader2, lucideLogIn }),
  ],
  templateUrl: './auth-main-page.component.html',
  styleUrl: './auth-main-page.component.scss',
})
export class AuthMainPageComponent implements OnInit {
  protected readonly allowsSignup = toSignal(inject(InstanceConfigService).allowsSignup(), {
    initialValue: false,
  });

  loginForm: FormGroup;
  isSubmitting = false;
  private returnUrl: string | null = null;

  private fb = inject(FormBuilder);
  private authService = inject(AuthService);
  private router = inject(Router);
  private route = inject(ActivatedRoute);

  constructor() {
    this.loginForm = this.fb.group({
      email: ['', [Validators.required, Validators.email]],
      password: ['', [Validators.required]],
      rememberMe: [true],
    });
  }

  ngOnInit(): void {
    this.returnUrl = this.route.snapshot.queryParams['returnUrl'] || null;
  }

  onSubmit() {
    if (this.loginForm.invalid) {
      return;
    }

    this.isSubmitting = true;
    const { email, password } = this.loginForm.value;

    this.authService.login(email, password).subscribe({
      next: () => {
        this.isSubmitting = false;
        if (this.returnUrl) {
          this.router.navigateByUrl(this.returnUrl);
        } else {
          this.authService.redirectAfterAuth(this.router);
        }
      },
      error: () => {
        this.isSubmitting = false;
      },
    });
  }

  get isLoginButtonDisabled(): boolean {
    return this.loginForm.invalid || this.isSubmitting;
  }
}
