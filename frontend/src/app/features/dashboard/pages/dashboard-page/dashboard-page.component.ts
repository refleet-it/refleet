import { HttpErrorResponse } from '@angular/common/http';
import { Component, computed, inject, OnInit, signal } from '@angular/core';
import { RouterLink } from '@angular/router';
import { HlmCardImports } from '@spartan-ng/helm/card';
import { HlmButton } from '@spartan-ng/helm/button';
import { HlmSkeletonImports } from '@spartan-ng/helm/skeleton';
import { forkJoin } from 'rxjs';
import {
  AGENT_WORKING_SHIFT_STATUSES,
  SHIFT_STATUS_BADGE_VARIANTS,
  SHIFT_STATUS_LABELS,
  ShiftOverview,
  ShiftStatus,
  TERMINAL_SHIFT_STATUSES,
} from '../../../../core/models/shift.model';
import { StatusBadgeVariant } from '../../../../core/models/status-badge.model';
import { ShiftService } from '../../../../core/services/shift.service';
import { ProjectService } from '../../../../core/services/project.service';
import { RunnerService } from '../../../../core/services/runner.service';
import { AuthService } from '../../../../core/services/auth.service';
import { GitLabConnectionService } from '../../../../core/services/gitlab-connection.service';
import { RunnerStatus } from '../../../../core/models/runner.model';
import { StatusBadgeComponent } from '../../../../shared/components/status-badge/status-badge.component';
import {
  TableSkeletonColumn,
  TableSkeletonRowsComponent,
} from '../../../../shared/components/table-skeleton-rows/table-skeleton-rows.component';
import { formatDateTime } from '../../../../shared/utils/format-date';

const WEEK_IN_MS = 7 * 24 * 60 * 60 * 1000;
const RECENT_SHIFTS_LIMIT = 5;

interface RunnerStatusCounts {
  working: number;
  idle: number;
  offline: number;
}

interface CompletedThisWeek {
  completed: number;
  total: number;
}

@Component({
  selector: 'app-dashboard-page',
  standalone: true,
  imports: [
    HlmCardImports,
    StatusBadgeComponent,
    HlmButton,
    HlmSkeletonImports,
    RouterLink,
    TableSkeletonRowsComponent,
  ],
  templateUrl: './dashboard-page.component.html',
})
export class DashboardPageComponent implements OnInit {
  private readonly authService = inject(AuthService);
  private readonly projectService = inject(ProjectService);
  private readonly runnerService = inject(RunnerService);
  private readonly shiftService = inject(ShiftService);
  private readonly gitLabConnectionService = inject(GitLabConnectionService);

  protected readonly currentUser = this.authService.getCurrentUser();
  protected readonly statusLabels = SHIFT_STATUS_LABELS;

  protected readonly isLoading = signal(true);
  protected readonly loadError = signal<string | null>(null);
  protected readonly gitlabConnected = signal<boolean | null>(null);
  protected readonly projectsCount = signal<number | null>(null);
  protected readonly runnersOnlineCount = signal<number | null>(null);
  protected readonly activeShiftsCount = signal<number | null>(null);
  protected readonly completedThisWeek = signal<CompletedThisWeek | null>(null);
  protected readonly recentShifts = signal<ShiftOverview[]>([]);
  protected readonly runnerStatusCounts = signal<RunnerStatusCounts | null>(null);

  protected readonly recentShiftsSkeletonColumns: TableSkeletonColumn[] = [
    { width: 'w-40' },
    { width: 'w-20' },
    { width: 'w-28' },
  ];
  protected readonly runnerFleetSkeletonRows = [0, 1, 2];

  protected readonly completedThisWeekLabel = computed(() => {
    const week = this.completedThisWeek();
    return null === week ? '—' : `${Math.round((week.completed / week.total) * 100)}%`;
  });

  /** A bare percentage hides its denominator: 0% of one shift reads the same as 0% of fifty. */
  protected readonly completedThisWeekDetail = computed(() => {
    const week = this.completedThisWeek();
    if (null === week) {
      return 'No shifts created in the last 7 days';
    }
    return `${week.completed} of ${week.total} shift${1 === week.total ? '' : 's'} created in the last 7 days`;
  });

  protected readonly zeroState = computed<'no-gitlab' | 'no-projects' | null>(() => {
    if (this.isLoading()) {
      return null;
    }
    if (false === this.gitlabConnected()) {
      return 'no-gitlab';
    }
    if (true === this.gitlabConnected() && 0 === this.projectsCount()) {
      return 'no-projects';
    }
    return null;
  });

  ngOnInit(): void {
    this.loadOverview();
  }

  protected retry(): void {
    this.loadOverview();
  }

  private loadOverview(): void {
    this.isLoading.set(true);
    this.loadError.set(null);

    forkJoin({
      projects: this.projectService.getProjects(1, 100),
      runners: this.runnerService.list(1, 100),
      shifts: this.shiftService.list(1, 100),
      gitlabConnection: this.gitLabConnectionService.get(),
    }).subscribe({
      next: ({ projects, runners, shifts, gitlabConnection }) => {
        this.gitlabConnected.set(gitlabConnection.connected);
        this.projectsCount.set(projects.pagination.total);
        this.runnersOnlineCount.set(runners.items.filter(r => 'offline' !== r.status).length);
        this.runnerStatusCounts.set(this.countRunnersByStatus(runners.items.map(r => r.status)));
        this.activeShiftsCount.set(
          shifts.items.filter(shift => !TERMINAL_SHIFT_STATUSES.includes(shift.status)).length
        );
        this.completedThisWeek.set(this.calculateCompletedThisWeek(shifts.items));
        this.recentShifts.set(this.selectRecentShifts(shifts.items));
        this.isLoading.set(false);
      },
      error: (error: HttpErrorResponse) => {
        this.isLoading.set(false);
        this.loadError.set(
          0 === error.status
            ? 'Could not reach the server. Check your connection and try again.'
            : 'Could not load dashboard data. Please try again.'
        );
      },
    });
  }

  protected readonly formatDate = formatDateTime;

  protected statusBadgeVariant(status: ShiftStatus): StatusBadgeVariant {
    return SHIFT_STATUS_BADGE_VARIANTS[status];
  }

  protected isAgentWorking(status: ShiftStatus): boolean {
    return AGENT_WORKING_SHIFT_STATUSES.has(status);
  }

  private selectRecentShifts(shifts: ShiftOverview[]): ShiftOverview[] {
    return [...shifts]
      .sort((a, b) => new Date(b.createdAt).getTime() - new Date(a.createdAt).getTime())
      .slice(0, RECENT_SHIFTS_LIMIT);
  }

  private countRunnersByStatus(statuses: RunnerStatus[]): RunnerStatusCounts {
    return {
      working: statuses.filter(status => 'working' === status).length,
      idle: statuses.filter(status => 'idle' === status).length,
      offline: statuses.filter(status => 'offline' === status).length,
    };
  }

  private calculateCompletedThisWeek(shifts: ShiftOverview[]): CompletedThisWeek | null {
    const cutoff = Date.now() - WEEK_IN_MS;
    const createdThisWeek = shifts.filter(shift => new Date(shift.createdAt).getTime() >= cutoff);

    if (0 === createdThisWeek.length) {
      return null;
    }

    return {
      completed: createdThisWeek.filter(shift => 'completed' === shift.status).length,
      total: createdThisWeek.length,
    };
  }
}
