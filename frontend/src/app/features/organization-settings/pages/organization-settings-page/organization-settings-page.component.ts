import { Component, DOCUMENT, inject, OnInit, signal } from '@angular/core';
import { FormsModule } from '@angular/forms';
import { HttpErrorResponse } from '@angular/common/http';
import { HlmButton } from '@spartan-ng/helm/button';
import { HlmCardImports } from '@spartan-ng/helm/card';
import { HlmDialogService } from '@spartan-ng/helm/dialog';
import { HlmFieldImports } from '@spartan-ng/helm/field';
import { HlmInput } from '@spartan-ng/helm/input';
import { HlmLabel } from '@spartan-ng/helm/label';
import { HlmSkeletonImports } from '@spartan-ng/helm/skeleton';
import { OrganizationOverview } from '../../../../core/models/organization.model';
import { OrganizationService } from '../../../../core/services/organization.service';
import {
  ConnectGitLabPayload,
  GITLAB_SYNC_STATUS_BADGE_VARIANTS,
  GITLAB_SYNC_STATUS_LABELS,
  GitLabConnectionOverview,
  GitLabSyncStatus,
} from '../../../../core/models/gitlab-connection.model';
import { StatusBadgeVariant } from '../../../../core/models/status-badge.model';
import { GitLabConnectionService } from '../../../../core/services/gitlab-connection.service';
import { ToastService } from '../../../../shared/services/toast.service';
import { StatusBadgeComponent } from '../../../../shared/components/status-badge/status-badge.component';
import { formatDateTime } from '../../../../shared/utils/format-date';
import { PasswordInputComponent } from '../../../../shared/components/password-input/password-input.component';
import {
  ConfirmDialogComponent,
  ConfirmDialogContext,
} from '../../../../shared/modals/confirm-dialog/confirm-dialog.component';

@Component({
  selector: 'app-organization-settings-page',
  standalone: true,
  imports: [
    FormsModule,
    StatusBadgeComponent,
    HlmButton,
    HlmCardImports,
    HlmFieldImports,
    HlmInput,
    HlmLabel,
    HlmSkeletonImports,
    PasswordInputComponent,
  ],
  templateUrl: './organization-settings-page.component.html',
})
export class OrganizationSettingsPageComponent implements OnInit {
  private readonly organizationService = inject(OrganizationService);
  private readonly gitLabConnectionService = inject(GitLabConnectionService);
  private readonly toastService = inject(ToastService);
  private readonly dialogService = inject(HlmDialogService);
  private readonly document = inject(DOCUMENT);

  protected readonly statusLabels = GITLAB_SYNC_STATUS_LABELS;

  protected readonly overview = signal<OrganizationOverview | null>(null);
  protected readonly isLoadingOverview = signal(true);
  protected readonly overviewSkeletonFields = [0, 1];

  protected readonly connection = signal<GitLabConnectionOverview | null>(null);
  protected readonly isLoadingConnection = signal(true);
  protected readonly connectionSkeletonFields = [0, 1, 2, 3];

  protected readonly connectForm = signal<ConnectGitLabPayload>({
    groupPath: '',
    accessToken: '',
    baseUrl: 'https://gitlab.com',
  });
  protected readonly isConnecting = signal(false);
  protected readonly connectError = signal<string | null>(null);
  protected readonly useAccessToken = signal(false);
  protected readonly isStartingOAuth = signal(false);
  protected readonly isSyncing = signal(false);
  protected readonly isDisconnecting = signal(false);

  ngOnInit(): void {
    this.loadOverview();
    this.loadConnection();
  }

  updateConnectForm(field: keyof ConnectGitLabPayload, value: string): void {
    this.connectForm.update(current => ({ ...current, [field]: value }));
  }

  toggleAccessTokenForm(): void {
    this.useAccessToken.update(current => !current);
    this.connectError.set(null);
  }

  connectWithGitLab(): void {
    const groupPath = this.connectForm().groupPath.trim();
    if (!groupPath || this.isStartingOAuth()) {
      return;
    }

    this.isStartingOAuth.set(true);
    this.connectError.set(null);

    this.gitLabConnectionService.startOAuth({ groupPath }).subscribe({
      next: ({ url }) => {
        // Leaving the app for gitlab.com; the callback page brings the owner back here.
        this.document.location.assign(url);
      },
      error: (error: HttpErrorResponse) => {
        this.isStartingOAuth.set(false);
        this.connectError.set(this.resolveConnectError(error));
      },
    });
  }

  connectGitLab(): void {
    const payload = this.connectForm();
    if (!payload.groupPath.trim() || !payload.accessToken.trim() || this.isConnecting()) {
      return;
    }

    this.isConnecting.set(true);
    this.connectError.set(null);

    this.gitLabConnectionService
      .connect({
        groupPath: payload.groupPath.trim(),
        accessToken: payload.accessToken.trim(),
        baseUrl: payload.baseUrl?.trim() || undefined,
      })
      .subscribe({
        next: () => {
          this.isConnecting.set(false);
          this.connectForm.set({ groupPath: '', accessToken: '', baseUrl: 'https://gitlab.com' });
          this.toastService.add({ severity: 'success', summary: 'Connected to GitLab' });
          this.loadConnection();
        },
        error: (error: HttpErrorResponse) => {
          this.isConnecting.set(false);
          this.connectError.set(this.resolveConnectError(error));
        },
      });
  }

  syncNow(): void {
    if (this.isSyncing()) {
      return;
    }

    this.isSyncing.set(true);

    this.gitLabConnectionService.sync().subscribe({
      next: result => {
        this.isSyncing.set(false);
        const parts = [`Synced ${result.syncedCount} project(s)`];
        if (result.failedCount > 0) {
          parts.push(`${result.failedCount} failed`);
        }
        if (result.archivedCount > 0) {
          parts.push(`archived ${result.archivedCount} no longer in GitLab`);
        }
        this.toastService.add({
          severity: result.failedCount > 0 ? 'warn' : 'success',
          summary: parts.join(', '),
        });
        this.loadConnection();
      },
      error: () => {
        this.isSyncing.set(false);
        this.loadConnection();
      },
    });
  }

  disconnectGitLab(): void {
    if (this.isDisconnecting()) {
      return;
    }

    this.dialogService
      .open<boolean, ConfirmDialogContext>(ConfirmDialogComponent, {
        context: {
          title: 'Disconnect GitLab?',
          description:
            'Projects already synced stay in your fleet but stop updating, and nothing new syncs until you connect again — which needs a fresh access token.',
          confirmLabel: 'Disconnect',
          destructive: true,
        },
      })
      .closed$.subscribe(confirmed => {
        if (confirmed) {
          this.performDisconnect();
        }
      });
  }

  private performDisconnect(): void {
    this.isDisconnecting.set(true);

    this.gitLabConnectionService.disconnect().subscribe({
      next: () => {
        this.isDisconnecting.set(false);
        this.toastService.add({ severity: 'success', summary: 'Disconnected from GitLab' });
        this.loadConnection();
      },
      error: () => {
        this.isDisconnecting.set(false);
        this.toastService.add({
          severity: 'error',
          summary: 'Could not disconnect from GitLab',
          detail: 'Please try again.',
        });
      },
    });
  }

  statusBadgeVariant(status: GitLabSyncStatus): StatusBadgeVariant {
    return GITLAB_SYNC_STATUS_BADGE_VARIANTS[status];
  }

  protected readonly formatDate = formatDateTime;

  private loadOverview(): void {
    this.isLoadingOverview.set(true);
    this.organizationService.getMyOrganization().subscribe({
      next: overview => {
        this.overview.set(overview);
        this.isLoadingOverview.set(false);
      },
      error: () => {
        this.isLoadingOverview.set(false);
      },
    });
  }

  private loadConnection(): void {
    this.isLoadingConnection.set(true);
    this.gitLabConnectionService.get().subscribe({
      next: connection => {
        this.connection.set(connection);
        this.isLoadingConnection.set(false);
      },
      error: () => {
        this.isLoadingConnection.set(false);
      },
    });
  }

  private resolveConnectError(error: HttpErrorResponse): string {
    if (403 === error.status) {
      return 'Only the organization owner can connect GitLab.';
    }
    if (409 === error.status) {
      return 'Your account does not belong to an organization yet.';
    }
    if (422 === error.status) {
      const body = error.error as { message?: string } | null;
      return body?.message || 'GitLab rejected the provided credentials.';
    }
    return 'Could not connect to GitLab. Please try again.';
  }
}
