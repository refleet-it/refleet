import { HttpClient, HttpParams } from '@angular/common/http';
import { inject, Injectable } from '@angular/core';
import { map, Observable } from 'rxjs';
import { environment } from '../../../environments/environment';
import { Page } from '../models/pagination.model';
import {
  CancelShiftPayload,
  CreatedShift,
  CreateShiftPayload,
  DefineShiftChangePayload,
  ReportShiftMergeRequestStatusPayload,
  ShiftChangePromptPreviewPayload,
  ShiftDetails,
  ShiftList,
  ShiftOverview,
  ShiftStatus,
  ShiftTargetDetails,
  ShiftTargetList,
  ShiftTargetOverview,
  ShiftTargetStatus,
} from '../models/shift.model';

@Injectable({
  providedIn: 'root',
})
export class ShiftService {
  private readonly http = inject(HttpClient);

  list(
    page: number,
    limit: number,
    search?: string,
    status?: ShiftStatus,
    archived = false
  ): Observable<Page<ShiftOverview>> {
    let params = new HttpParams().set('page', page).set('limit', limit).set('archived', archived);
    if (search) {
      params = params.set('search', search);
    }
    if (status) {
      params = params.set('status', status);
    }

    return this.http
      .get<ShiftList>(`${environment.apiUrl}/shifts`, { params })
      .pipe(map(res => ({ items: res.shifts, pagination: res.pagination })));
  }

  get(id: string): Observable<ShiftDetails> {
    return this.http.get<ShiftDetails>(`${environment.apiUrl}/shifts/${id}`);
  }

  create(payload: CreateShiftPayload): Observable<CreatedShift> {
    return this.http.post<CreatedShift>(`${environment.apiUrl}/shifts`, payload);
  }

  /** The exact text a change job would carry — rendered by the backend's own job factory, never a copy. */
  previewChangePrompt(payload: ShiftChangePromptPreviewPayload): Observable<string> {
    return this.http
      .post<{ prompt: string }>(`${environment.apiUrl}/shifts/prompt-preview`, payload)
      .pipe(map(res => res.prompt));
  }

  defineChange(id: string, payload: DefineShiftChangePayload): Observable<ShiftDetails> {
    return this.http.post<ShiftDetails>(`${environment.apiUrl}/shifts/${id}/change`, payload);
  }

  startChange(id: string): Observable<ShiftDetails> {
    return this.http.post<ShiftDetails>(`${environment.apiUrl}/shifts/${id}/change/start`, {});
  }

  /** Trial-runs one target of a draft, or re-runs a settled one — see StartShiftTargetChangeController. */
  startTargetChange(id: string, targetId: string): Observable<ShiftTargetDetails> {
    return this.http.post<ShiftTargetDetails>(
      `${environment.apiUrl}/shifts/${id}/targets/${targetId}/change/start`,
      {}
    );
  }

  cancel(id: string, payload: CancelShiftPayload = {}): Observable<ShiftDetails> {
    return this.http.post<ShiftDetails>(`${environment.apiUrl}/shifts/${id}/cancel`, payload);
  }

  archive(id: string): Observable<ShiftDetails> {
    return this.http.post<ShiftDetails>(`${environment.apiUrl}/shifts/${id}/archive`, {});
  }

  listTargets(
    id: string,
    page: number,
    limit: number,
    status?: ShiftTargetStatus
  ): Observable<Page<ShiftTargetOverview>> {
    let params = new HttpParams().set('page', page).set('limit', limit);
    if (status) {
      params = params.set('status', status);
    }
    return this.http
      .get<ShiftTargetList>(`${environment.apiUrl}/shifts/${id}/targets`, { params })
      .pipe(map(res => ({ items: res.targets, pagination: res.pagination })));
  }

  getTarget(id: string, targetId: string): Observable<ShiftTargetDetails> {
    return this.http.get<ShiftTargetDetails>(
      `${environment.apiUrl}/shifts/${id}/targets/${targetId}`
    );
  }

  reportMergeRequestStatus(
    id: string,
    targetId: string,
    payload: ReportShiftMergeRequestStatusPayload
  ): Observable<ShiftTargetDetails> {
    return this.http.post<ShiftTargetDetails>(
      `${environment.apiUrl}/shifts/${id}/targets/${targetId}/merge-request`,
      payload
    );
  }
}
