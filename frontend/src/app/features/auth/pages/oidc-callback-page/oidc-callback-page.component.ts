import { Component, inject, OnInit, PLATFORM_ID, signal } from '@angular/core';
import { isPlatformBrowser } from '@angular/common';
import { HttpErrorResponse } from '@angular/common/http';
import { ActivatedRoute, Router } from '@angular/router';
import { NgIcon, provideIcons } from '@ng-icons/core';
import { lucideArrowLeft, lucideCircleX, lucideLoader2 } from '@ng-icons/lucide';
import { HlmButton } from '@spartan-ng/helm/button';

import { AuthService } from '../../../../core/services/auth.service';

const LOGIN_ROUTE = ['/auth'];

/**
 * Where the identity provider sends the browser back after somebody signs in.
 *
 * Nothing here is trusted on its own. The `code` and `state` go straight to the API, which
 * verifies the state against the cookie it set when the flow started, exchanges the code, and
 * checks the signature and claims on the token the provider returns. This page's only job is to
 * carry two query parameters into one POST and say what happened.
 *
 * Browser-only on purpose, and the early return is what makes that true: the cookie the API
 * needs is in the visitor's browser, not in the server-side renderer, so a POST from the server
 * would be refused. The route is also deliberately outside the `/auth` subtree — that subtree is
 * GuestGuard'ed, and a browser arriving here may already hold valid refresh cookies from an
 * earlier session. The guard would then bounce it to `/` with the authorization code unspent,
 * leaving whoever was signed in before signed in instead of whoever just authenticated.
 */
@Component({
  selector: 'app-oidc-callback-page',
  standalone: true,
  imports: [NgIcon, HlmButton],
  providers: [provideIcons({ lucideLoader2, lucideCircleX, lucideArrowLeft })],
  templateUrl: './oidc-callback-page.component.html',
  styleUrl: './oidc-callback-page.component.scss',
})
export class OidcCallbackPageComponent implements OnInit {
  protected readonly error = signal<string | null>(null);

  private readonly authService = inject(AuthService);
  private readonly router = inject(Router);
  private readonly route = inject(ActivatedRoute);
  private readonly platformId = inject(PLATFORM_ID);

  ngOnInit(): void {
    if (!isPlatformBrowser(this.platformId)) {
      return;
    }

    const params = this.route.snapshot.queryParamMap;

    // The provider answers a refused or cancelled sign-in with `error` instead of `code`, and
    // says so in OAuth's vocabulary rather than in words anybody wants to read.
    const refused = params.get('error');
    if (refused) {
      this.error.set(
        'access_denied' === refused
          ? 'You cancelled the sign-in, or your provider would not allow it.'
          : (params.get('error_description') ??
              `Your identity provider reported an error: ${refused}`)
      );
      return;
    }

    const code = params.get('code');
    const state = params.get('state');
    if (!code || !state) {
      this.error.set('The link from your identity provider is incomplete. Sign in again.');
      return;
    }

    this.authService.loginWithOidc(code, state).subscribe({
      next: () => this.authService.redirectAfterAuth(this.router),
      error: (error: HttpErrorResponse) => {
        // The API's own wording is better than anything this page could guess — it is the side
        // that knows whether the code was stale, the instance is closed to new accounts, or the
        // account needs an administrator. The fallback is for the cases with no body at all.
        const message: unknown = error.error?.message;
        this.error.set(
          typeof message === 'string' && message
            ? message
            : 'Could not complete the sign-in. Please try again.'
        );
      },
    });
  }

  backToLogin(): void {
    this.router.navigate(LOGIN_ROUTE);
  }
}
