import { isPlatformBrowser } from '@angular/common';
import { HttpErrorResponse } from '@angular/common/http';
import { Component, inject, OnInit, PLATFORM_ID, signal } from '@angular/core';
import { ActivatedRoute } from '@angular/router';
import { NgIcon, provideIcons } from '@ng-icons/core';
import {
  lucideCircleCheck,
  lucideCircleX,
  lucideLoader2,
  lucideTerminal,
  lucideTriangleAlert,
} from '@ng-icons/lucide';
import { HlmButton } from '@spartan-ng/helm/button';
import { HlmCardImports } from '@spartan-ng/helm/card';
import { AuthService } from '../../../../core/services/auth.service';
import {
  CliAuthorization,
  CliAuthorizationService,
} from '../../../../core/services/cli-authorization.service';

type PageState = 'loading' | 'ready' | 'approved' | 'denied' | 'error';

@Component({
  selector: 'app-cli-authorize-page',
  standalone: true,
  imports: [NgIcon, HlmButton, HlmCardImports],
  providers: [
    provideIcons({
      lucideCircleCheck,
      lucideCircleX,
      lucideLoader2,
      lucideTerminal,
      lucideTriangleAlert,
    }),
  ],
  templateUrl: './cli-authorize-page.component.html',
})
export class CliAuthorizePageComponent implements OnInit {
  private readonly route = inject(ActivatedRoute);
  private readonly cliAuthorizations = inject(CliAuthorizationService);
  private readonly authService = inject(AuthService);
  private readonly platformId = inject(PLATFORM_ID);

  protected readonly state = signal<PageState>('loading');
  protected readonly authorization = signal<CliAuthorization | null>(null);
  protected readonly errorMessage = signal<string | null>(null);
  protected readonly isSubmitting = signal(false);
  protected readonly accountEmail = signal<string | null>(null);

  private userCode = '';

  ngOnInit(): void {
    this.userCode = this.route.snapshot.paramMap.get('code') ?? '';
    this.accountEmail.set(this.authService.getCurrentUser()?.email ?? null);

    if (!isPlatformBrowser(this.platformId)) {
      return;
    }

    this.cliAuthorizations.get(this.userCode).subscribe({
      next: authorization => {
        this.authorization.set(authorization);
        if (authorization.status === 'pending') {
          this.state.set('ready');
        } else {
          this.fail('This login request has already been decided. Run `refleet login` again.');
        }
      },
      error: (error: unknown) => this.fail(this.describe(error)),
    });
  }

  approve(): void {
    this.decide(this.cliAuthorizations.approve(this.userCode), 'approved');
  }

  deny(): void {
    this.decide(this.cliAuthorizations.deny(this.userCode), 'denied');
  }

  private decide(
    request: ReturnType<CliAuthorizationService['approve']>,
    outcome: 'approved' | 'denied'
  ): void {
    if (this.isSubmitting()) {
      return;
    }
    this.isSubmitting.set(true);

    request.subscribe({
      next: () => {
        this.isSubmitting.set(false);
        this.state.set(outcome);
      },
      error: (error: unknown) => {
        this.isSubmitting.set(false);
        this.fail(this.describe(error));
      },
    });
  }

  private fail(message: string): void {
    this.errorMessage.set(message);
    this.state.set('error');
  }

  private describe(error: unknown): string {
    if (error instanceof HttpErrorResponse) {
      if (error.status === 404) {
        return 'This login link is not valid. Run `refleet login` again to get a new one.';
      }
      if (error.status === 410) {
        return 'This login request has expired. Run `refleet login` again.';
      }
      if (error.status === 409) {
        return 'This login request has already been decided.';
      }
    }
    return 'Something went wrong. Run `refleet login` again to get a new link.';
  }
}
