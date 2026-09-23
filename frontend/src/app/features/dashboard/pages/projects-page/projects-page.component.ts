import { Component, inject, OnInit, signal } from '@angular/core';
import { RouterLink } from '@angular/router';
import { HlmButton } from '@spartan-ng/helm/button';
import { HlmCardImports } from '@spartan-ng/helm/card';
import { HlmNumberedPagination } from '@spartan-ng/helm/pagination';
import { ProjectOverview } from '../../../../core/models/project.model';
import { GitLabConnectionService } from '../../../../core/services/gitlab-connection.service';
import { ProjectService } from '../../../../core/services/project.service';
import { ToastService } from '../../../../shared/services/toast.service';
import { ListErrorComponent } from '../../../../shared/components/list-error/list-error.component';
import {
  TableSkeletonColumn,
  TableSkeletonRowsComponent,
} from '../../../../shared/components/table-skeleton-rows/table-skeleton-rows.component';
import { injectPagedList } from '../../../../shared/utils/paged-list';

@Component({
  selector: 'app-projects-page',
  standalone: true,
  imports: [
    RouterLink,
    HlmButton,
    HlmCardImports,
    TableSkeletonRowsComponent,
    ListErrorComponent,
    HlmNumberedPagination,
  ],
  templateUrl: './projects-page.component.html',
})
export class ProjectsPageComponent implements OnInit {
  private readonly projectService = inject(ProjectService);
  private readonly gitLabConnectionService = inject(GitLabConnectionService);
  private readonly toastService = inject(ToastService);

  protected readonly list = injectPagedList<ProjectOverview>((page, limit) =>
    this.projectService.getProjects(page, limit)
  );

  protected readonly skeletonColumns: TableSkeletonColumn[] = [
    { width: 'w-32' },
    { width: 'w-40' },
    { width: 'w-16' },
    { width: 'w-24' },
  ];

  /** Syncing is only offered once GitLab is connected — without it the call has nothing to sync. */
  protected readonly isConnected = signal(false);
  protected readonly isSyncing = signal(false);

  ngOnInit(): void {
    this.gitLabConnectionService.get().subscribe({
      next: connection => this.isConnected.set(connection.connected),
      error: () => this.isConnected.set(false),
    });
  }

  protected syncNow(): void {
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
        this.list.refresh();
      },
      error: () => {
        this.isSyncing.set(false);
        this.toastService.add({
          severity: 'error',
          summary: 'Could not sync from GitLab',
          detail: 'Please try again.',
        });
      },
    });
  }
}
