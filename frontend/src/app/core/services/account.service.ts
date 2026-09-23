import { HttpClient, HttpParams } from '@angular/common/http';
import { inject, Injectable } from '@angular/core';
import { Observable } from 'rxjs';
import { environment } from '../../../environments/environment';
import { AccountListPage } from '../models/account.model';

@Injectable({
  providedIn: 'root',
})
export class AccountService {
  private readonly http = inject(HttpClient);

  list(cursor: string | null = null): Observable<AccountListPage> {
    let params = new HttpParams().set('sortBy', 'email').set('sortDirection', 'asc');
    if (cursor) {
      params = params.set('cursor', cursor);
    }

    return this.http.get<AccountListPage>(`${environment.apiUrl}/identity/accounts`, { params });
  }
}
