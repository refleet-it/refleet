import { HttpClient } from '@angular/common/http';
import { inject, Injectable } from '@angular/core';
import { Observable } from 'rxjs';
import { environment } from '../../../environments/environment';

export interface CliAuthorization {
  runnerName: string;
  status: 'pending' | 'approved' | 'denied';
  createdAt: string;
  expiresAt: string;
}

/**
 * The browser half of `refleet login`: the CLI created the authorization and is polling
 * for the outcome; this only ever looks at it and records the signed-in account's decision.
 */
@Injectable({
  providedIn: 'root',
})
export class CliAuthorizationService {
  private readonly http = inject(HttpClient);

  get(userCode: string): Observable<CliAuthorization> {
    return this.http.get<CliAuthorization>(this.url(userCode));
  }

  approve(userCode: string): Observable<void> {
    return this.http.post<void>(`${this.url(userCode)}/approve`, null);
  }

  deny(userCode: string): Observable<void> {
    return this.http.post<void>(`${this.url(userCode)}/deny`, null);
  }

  private url(userCode: string): string {
    return `${environment.apiUrl}/identity/cli-authorizations/${encodeURIComponent(userCode)}`;
  }
}
