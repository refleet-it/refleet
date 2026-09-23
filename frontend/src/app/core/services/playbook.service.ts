import { HttpClient, HttpParams } from '@angular/common/http';
import { inject, Injectable } from '@angular/core';
import { map, Observable } from 'rxjs';
import { environment } from '../../../environments/environment';
import {
  ComposedPrompt,
  ComposePromptPayload,
  Playbook,
  PlaybookList,
  PlaybookPayload,
  PlaybookUsage,
} from '../models/playbook.model';

@Injectable({
  providedIn: 'root',
})
export class PlaybookService {
  private readonly http = inject(HttpClient);

  /** Built-ins first, then the organization's own; `appliesTo` narrows to one prompt kind. */
  list(appliesTo?: PlaybookUsage): Observable<Playbook[]> {
    let params = new HttpParams();
    if (appliesTo) {
      params = params.set('appliesTo', appliesTo);
    }

    return this.http
      .get<PlaybookList>(`${environment.apiUrl}/playbooks`, { params })
      .pipe(map(res => res.playbooks));
  }

  get(id: string): Observable<Playbook> {
    return this.http.get<Playbook>(`${environment.apiUrl}/playbooks/${encodeURIComponent(id)}`);
  }

  create(payload: PlaybookPayload): Observable<Playbook> {
    return this.http.post<Playbook>(`${environment.apiUrl}/playbooks`, payload);
  }

  update(id: string, payload: PlaybookPayload): Observable<Playbook> {
    return this.http.put<Playbook>(
      `${environment.apiUrl}/playbooks/${encodeURIComponent(id)}`,
      payload
    );
  }

  delete(id: string): Observable<void> {
    return this.http.delete<void>(`${environment.apiUrl}/playbooks/${encodeURIComponent(id)}`);
  }

  /** Nothing is stored: the result is text the caller pastes into the editable prompt fields. */
  compose(payload: ComposePromptPayload): Observable<ComposedPrompt> {
    return this.http.post<ComposedPrompt>(`${environment.apiUrl}/playbooks/compose`, payload);
  }
}
