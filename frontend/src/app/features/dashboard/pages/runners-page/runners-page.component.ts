import { Component, inject } from '@angular/core';
import { ActivatedRoute, RouterLink } from '@angular/router';
import { HlmBadgeImports } from '@spartan-ng/helm/badge';
import { HlmButton } from '@spartan-ng/helm/button';
import { HlmCardImports } from '@spartan-ng/helm/card';
import { HlmNumberedPagination } from '@spartan-ng/helm/pagination';
import {
  AGENT_WORKING_RUNNER_STATUSES,
  RUNNER_STATUS_BADGE_VARIANTS,
  RUNNER_STATUS_LABELS,
  RunnerStatus,
} from '../../../../core/models/runner.model';
import { StatusBadgeVariant } from '../../../../core/models/status-badge.model';
import { RunnerService } from '../../../../core/services/runner.service';
import { ListErrorComponent } from '../../../../shared/components/list-error/list-error.component';
import { StatusBadgeComponent } from '../../../../shared/components/status-badge/status-badge.component';
import {
  TableSkeletonColumn,
  TableSkeletonRowsComponent,
} from '../../../../shared/components/table-skeleton-rows/table-skeleton-rows.component';
import { formatDateTime } from '../../../../shared/utils/format-date';
import { injectPagedList } from '../../../../shared/utils/paged-list';

@Component({
  selector: 'app-runners-page',
  standalone: true,
  imports: [
    RouterLink,
    HlmBadgeImports,
    HlmButton,
    HlmCardImports,
    StatusBadgeComponent,
    TableSkeletonRowsComponent,
    ListErrorComponent,
    HlmNumberedPagination,
  ],
  templateUrl: './runners-page.component.html',
})
export class RunnersPageComponent {
  private readonly runnerService = inject(RunnerService);
  private readonly route = inject(ActivatedRoute);

  /** The /archive route renders this same page over the archived side of the fleet. */
  protected readonly archived: boolean = true === this.route.snapshot.data['archived'];

  protected readonly statusLabels = RUNNER_STATUS_LABELS;

  protected readonly list = injectPagedList((page, limit) =>
    this.runnerService.list(page, limit, this.archived)
  );

  protected readonly skeletonColumns: TableSkeletonColumn[] = [
    { width: 'w-32' },
    { width: 'w-16' },
    { width: 'w-20' },
    { width: 'w-28' },
    { width: 'w-28' },
  ];

  protected readonly formatDate = formatDateTime;

  statusBadgeVariant(status: RunnerStatus): StatusBadgeVariant {
    return RUNNER_STATUS_BADGE_VARIANTS[status];
  }

  isAgentWorking(status: RunnerStatus): boolean {
    return AGENT_WORKING_RUNNER_STATUSES.has(status);
  }
}
