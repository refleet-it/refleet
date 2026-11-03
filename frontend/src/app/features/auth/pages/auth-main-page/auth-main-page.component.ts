import { Component, computed, inject, OnInit, signal } from '@angular/core';
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
  lucideKeyRound,
} from '@ng-icons/lucide';
import { HlmButton } from '@spartan-ng/helm/button';
import { HlmInput } from '@spartan-ng/helm/input';
import { HlmCheckbox } from '@spartan-ng/helm/checkbox';
import { HlmFieldImports } from '@spartan-ng/helm/field';

import { AuthService } from '../../../../core/services/auth.service';
import {
  AuthProvider,
  InstanceConfigService,
} from '../../../../core/services/instance-config.service';
import { PasswordInputComponent } from '../../../../shared/components/password-input/password-input.component';

/**
 * Providers this screen can actually start a flow for.
 *
 * `/instance` also reports `google` when an operator configured it, and it is left out on
 * purpose: there is no browser flow for it. `AuthService.loginWithGoogle()` wants a finished
 * `idToken` from a Google SDK this application does not load, and the API has no endpoint that
 * would take an authorization code. A button for it would be a button that cannot work.
 */
const STARTABLE_PROVIDERS = ['oidc'];

const DEFAULT_PROVIDER_LABEL = 'single sign-on';

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
    provideIcons({
      lucideMail,
      lucideLock,
      lucideTriangleAlert,
      lucideLoader2,
      lucideLogIn,
      lucideKeyRound,
    }),
  ],
  templateUrl: './auth-main-page.component.html',
  styleUrl: './auth-main-page.component.scss',
})
export class AuthMainPageComponent implements OnInit {
  protected readonly allowsSignup = toSignal(inject(InstanceConfigService).allowsSignup(), {
    initialValue: false,
  });

  private readonly configuredProviders = toSignal(inject(InstanceConfigService).authProviders(), {
    initialValue: [] as AuthProvider[],
  });

  protected readonly providers = computed(() =>
    this.configuredProviders().filter(provider => STARTABLE_PROVIDERS.includes(provider.provider))
  );

  /** Which provider is being handed off to, so its button alone shows the wait. */
  protected readonly startingProvider = signal<string | null>(null);

  /**
   * ErrorInterceptor stays quiet on failed GETs because a screen that cannot load says so in
   * place, and this is that in-place message.
   */
  protected readonly providerError = signal<string | null>(null);

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

  protected labelFor(provider: AuthProvider): string {
    return provider.label || DEFAULT_PROVIDER_LABEL;
  }

  /**
   * Hands the browser to the provider. The URL is the service's to fetch and follow — it carries
   * a signed `state`, and the request that produces it is also what sets the cookie the callback
   * is checked against, so neither half can be built here.
   */
  protected startProviderLogin(provider: AuthProvider): void {
    if (this.startingProvider()) {
      return;
    }

    this.providerError.set(null);
    this.startingProvider.set(provider.provider);

    this.authService.startOidcLogin().subscribe({
      error: () => {
        this.startingProvider.set(null);
        this.providerError.set(
          `Could not start the sign-in with ${this.labelFor(provider)}. Try again, or log in with your email address.`
        );
      },
    });
  }
}
