import { HttpClient } from '@angular/common/http';
import { inject, Injectable } from '@angular/core';
import { Observable } from 'rxjs';
import { environment } from '../../../environments/environment';
import { ApiKey, CreatedApiKey } from '../models/api-key.model';

interface ListApiKeysResponse {
  apiKeys: ApiKey[];
  count: number;
}

@Injectable({
  providedIn: 'root',
})
export class ApiKeyService {
  private readonly http = inject(HttpClient);

  list(): Observable<ListApiKeysResponse> {
    return this.http.get<ListApiKeysResponse>(`${environment.apiUrl}/identity/api-keys`);
  }

  create(name: string): Observable<CreatedApiKey> {
    return this.http.post<CreatedApiKey>(`${environment.apiUrl}/identity/api-keys`, { name });
  }

  revoke(apiKeyId: string): Observable<void> {
    return this.http.delete<void>(`${environment.apiUrl}/identity/api-keys/${apiKeyId}`);
  }
}
