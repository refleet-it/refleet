import { HttpClient } from '@angular/common/http';
import { inject, Injectable } from '@angular/core';
import { Observable } from 'rxjs';
import { environment } from '../../../environments/environment';
import {
  CompleteGitLabOAuthPayload,
  ConnectedGitLabConnection,
  ConnectGitLabPayload,
  GitLabConnectionOverview,
  StartGitLabOAuthPayload,
  StartGitLabOAuthResult,
  SyncGitLabResult,
} from '../models/gitlab-connection.model';

@Injectable({
  providedIn: 'root',
})
export class GitLabConnectionService {
  private readonly http = inject(HttpClient);

  get(): Observable<GitLabConnectionOverview> {
    return this.http.get<GitLabConnectionOverview>(`${environment.apiUrl}/gitlab/connection`);
  }

  connect(payload: ConnectGitLabPayload): Observable<ConnectedGitLabConnection> {
    return this.http.post<ConnectedGitLabConnection>(
      `${environment.apiUrl}/gitlab/connection`,
      payload
    );
  }

  startOAuth(payload: StartGitLabOAuthPayload): Observable<StartGitLabOAuthResult> {
    return this.http.post<StartGitLabOAuthResult>(
      `${environment.apiUrl}/gitlab/connection/oauth/start`,
      payload
    );
  }

  completeOAuth(payload: CompleteGitLabOAuthPayload): Observable<ConnectedGitLabConnection> {
    return this.http.post<ConnectedGitLabConnection>(
      `${environment.apiUrl}/gitlab/connection/oauth/complete`,
      payload
    );
  }

  disconnect(): Observable<void> {
    return this.http.delete<void>(`${environment.apiUrl}/gitlab/connection`);
  }

  sync(): Observable<SyncGitLabResult> {
    return this.http.post<SyncGitLabResult>(`${environment.apiUrl}/gitlab/connection/sync`, {});
  }
}
