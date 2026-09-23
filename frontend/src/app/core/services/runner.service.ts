import { HttpClient, HttpParams } from '@angular/common/http';
import { inject, Injectable } from '@angular/core';
import { map, Observable } from 'rxjs';
import { environment } from '../../../environments/environment';
import { Page } from '../models/pagination.model';
import {
  AvailableModels,
  RunnerJobList,
  RunnerJobOverview,
  RunnerList,
  RunnerOverview,
} from '../models/runner.model';

@Injectable({
  providedIn: 'root',
})
export class RunnerService {
  private readonly http = inject(HttpClient);

  list(page: number, limit: number, archived = false): Observable<Page<RunnerOverview>> {
    const params = new HttpParams().set('page', page).set('limit', limit).set('archived', archived);

    return this.http
      .get<RunnerList>(`${environment.apiUrl}/runners`, { params })
      .pipe(map(res => ({ items: res.runners, pagination: res.pagination })));
  }

  get(id: string): Observable<RunnerOverview> {
    return this.http.get<RunnerOverview>(`${environment.apiUrl}/runners/${id}`);
  }

  /** Irreversible: the runner leaves the runner fleet and its API key is revoked for good. */
  archive(id: string): Observable<RunnerOverview> {
    return this.http.post<RunnerOverview>(`${environment.apiUrl}/runners/${id}/archive`, {});
  }

  /** Asks the runner to stop after its current jobs so its supervisor restarts it on the published version; delivered on its next heartbeat. */
  requestUpdate(id: string): Observable<RunnerOverview> {
    return this.http.post<RunnerOverview>(`${environment.apiUrl}/runners/${id}/update`, {});
  }

  /** Model ids currently offered across the organization's runner fleet, grouped by engine — sourced from what each runner reported on heartbeat, not a fixed list. */
  availableModels(): Observable<AvailableModels> {
    return this.http.get<AvailableModels>(`${environment.apiUrl}/runners/available-models`);
  }

  listJobs(id: string, page: number, limit: number): Observable<Page<RunnerJobOverview>> {
    const params = new HttpParams().set('page', page).set('limit', limit);

    return this.http
      .get<RunnerJobList>(`${environment.apiUrl}/runners/${id}/jobs`, { params })
      .pipe(map(res => ({ items: res.jobs, pagination: res.pagination })));
  }
}
