import { HttpClient, HttpParams } from '@angular/common/http';
import { inject, Injectable } from '@angular/core';
import { map, Observable } from 'rxjs';
import { environment } from '../../../environments/environment';
import { Page } from '../models/pagination.model';
import {
  CancelQualificationPayload,
  CreatedQualification,
  CreateQualificationPayload,
  OverrideQualificationTargetPayload,
  QualificationDetails,
  QualificationList,
  QualificationOverview,
  QualificationPromptPreviewPayload,
  QualificationStatus,
  QualificationTargetDetails,
  QualificationTargetList,
  QualificationTargetOverview,
  QualificationTargetStatus,
} from '../models/qualification.model';

@Injectable({
  providedIn: 'root',
})
export class QualificationService {
  private readonly http = inject(HttpClient);

  list(
    page: number,
    limit: number,
    search?: string,
    status?: QualificationStatus,
    archived = false
  ): Observable<Page<QualificationOverview>> {
    let params = new HttpParams().set('page', page).set('limit', limit).set('archived', archived);
    if (search) {
      params = params.set('search', search);
    }
    if (status) {
      params = params.set('status', status);
    }

    return this.http
      .get<QualificationList>(`${environment.apiUrl}/qualifications`, { params })
      .pipe(map(res => ({ items: res.qualifications, pagination: res.pagination })));
  }

  /** The exact text a qualification job would carry — rendered by the backend's own job factory, never a copy. */
  previewPrompt(payload: QualificationPromptPreviewPayload): Observable<string> {
    return this.http
      .post<{ prompt: string }>(`${environment.apiUrl}/qualifications/prompt-preview`, payload)
      .pipe(map(res => res.prompt));
  }

  get(id: string): Observable<QualificationDetails> {
    return this.http.get<QualificationDetails>(`${environment.apiUrl}/qualifications/${id}`);
  }

  create(payload: CreateQualificationPayload): Observable<CreatedQualification> {
    return this.http.post<CreatedQualification>(`${environment.apiUrl}/qualifications`, payload);
  }

  start(id: string): Observable<QualificationDetails> {
    return this.http.post<QualificationDetails>(
      `${environment.apiUrl}/qualifications/${id}/start`,
      {}
    );
  }

  cancel(id: string, payload: CancelQualificationPayload = {}): Observable<QualificationDetails> {
    return this.http.post<QualificationDetails>(
      `${environment.apiUrl}/qualifications/${id}/cancel`,
      payload
    );
  }

  archive(id: string): Observable<QualificationDetails> {
    return this.http.post<QualificationDetails>(
      `${environment.apiUrl}/qualifications/${id}/archive`,
      {}
    );
  }

  /** Re-enqueues every failed target; a completed qualification goes back to running. */
  retryFailedTargets(id: string): Observable<QualificationDetails> {
    return this.http.post<QualificationDetails>(
      `${environment.apiUrl}/qualifications/${id}/retry`,
      {}
    );
  }

  retryTarget(id: string, targetId: string): Observable<QualificationTargetDetails> {
    return this.http.post<QualificationTargetDetails>(
      `${environment.apiUrl}/qualifications/${id}/targets/${targetId}/retry`,
      {}
    );
  }

  overrideTarget(
    id: string,
    targetId: string,
    payload: OverrideQualificationTargetPayload
  ): Observable<QualificationTargetDetails> {
    return this.http.post<QualificationTargetDetails>(
      `${environment.apiUrl}/qualifications/${id}/targets/${targetId}/override`,
      payload
    );
  }

  listTargets(
    id: string,
    page: number,
    limit: number,
    status?: QualificationTargetStatus
  ): Observable<Page<QualificationTargetOverview>> {
    let params = new HttpParams().set('page', page).set('limit', limit);
    if (status) {
      params = params.set('status', status);
    }
    return this.http
      .get<QualificationTargetList>(`${environment.apiUrl}/qualifications/${id}/targets`, {
        params,
      })
      .pipe(map(res => ({ items: res.targets, pagination: res.pagination })));
  }

  getTarget(id: string, targetId: string): Observable<QualificationTargetDetails> {
    return this.http.get<QualificationTargetDetails>(
      `${environment.apiUrl}/qualifications/${id}/targets/${targetId}`
    );
  }
}
