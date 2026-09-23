import { Component, inject, OnInit, PLATFORM_ID, signal } from '@angular/core';
import { isPlatformBrowser } from '@angular/common';
import { HttpErrorResponse } from '@angular/common/http';
import { ActivatedRoute, Router } from '@angular/router';
import { NgIcon, provideIcons } from '@ng-icons/core';
import { lucideArrowLeft, lucideCircleX, lucideLoader2 } from '@ng-icons/lucide';
import { HlmButton } from '@spartan-ng/helm/button';
import { GitLabConnectionService } from '../../../../core/services/gitlab-connection.service';
import { ToastService } from '../../../../shared/services/toast.service';

const SETTINGS_ROUTE = ['/dashboard', 'settings'];

/**
 * Where gitlab.com sends the owner back after authorizing Refleet. Nothing here is
 * trusted on its own: the code and state go straight to the API, which checks the
 * signature and that the same owner started the flow.
 */
@Component({
  selector: 'app-gitlab-oauth-callback-page',
  standalone: true,
  imports: [NgIcon, HlmButton],
  providers: [provideIcons({ lucideLoader2, lucideCircleX, lucideArrowLeft })],
  templateUrl: './gitlab-oauth-callback-page.component.html',
})
export class GitLabOAuthCallbackPageComponent implements OnInit {
  protected readonly error = signal<string | null>(null);

  private readonly gitLabConnectionService = inject(GitLabConnectionService);
  private readonly toastService = inject(ToastService);
  private readonly router = inject(Router);
  private readonly route = inject(ActivatedRoute);
  private readonly platformId = inject(PLATFORM_ID);

  ngOnInit(): void {
    if (!isPlatformBrowser(this.platformId)) {
      return;
    }

    const params = this.route.snapshot.queryParamMap;
    const denied = params.get('error');
    if (denied) {
      this.error.set(
        denied === 'access_denied'
          ? 'You cancelled the authorization in GitLab.'
          : (params.get('error_description') ?? `GitLab reported an error: ${denied}`)
      );
      return;
    }

    const code = params.get('code');
    const state = params.get('state');
    if (!code || !state) {
      this.error.set('The link from GitLab is incomplete. Start the connection again.');
      return;
    }

    this.gitLabConnectionService.completeOAuth({ code, state }).subscribe({
      next: connected => {
        this.toastService.add({
          severity: 'success',
          summary: `Connected to GitLab group ${connected.groupName}`,
        });
        this.router.navigate(SETTINGS_ROUTE);
      },
      error: (error: HttpErrorResponse) => {
        const message: unknown = error.error?.message;
        this.error.set(
          typeof message === 'string' && message
            ? message
            : 'Could not complete the GitLab authorization. Start the connection again.'
        );
      },
    });
  }

  backToSettings(): void {
    this.router.navigate(SETTINGS_ROUTE);
  }
}
