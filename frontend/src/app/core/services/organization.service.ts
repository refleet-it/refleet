import { HttpClient, HttpParams } from '@angular/common/http';
import { inject, Injectable } from '@angular/core';
import { map, Observable } from 'rxjs';
import { environment } from '../../../environments/environment';
import { Page } from '../models/pagination.model';
import {
  CreatedOrganization,
  Employee,
  EmployeeList,
  OrganizationOverview,
  PendingInvitation,
  PendingInvitationList,
  SentInvitation,
} from '../models/organization.model';

@Injectable({
  providedIn: 'root',
})
export class OrganizationService {
  private readonly http = inject(HttpClient);

  getMyOrganization(): Observable<OrganizationOverview> {
    return this.http.get<OrganizationOverview>(`${environment.apiUrl}/organizations/me`);
  }

  create(name: string): Observable<CreatedOrganization> {
    return this.http.post<CreatedOrganization>(`${environment.apiUrl}/organizations`, { name });
  }

  sendInvitation(email: string): Observable<SentInvitation> {
    return this.http.post<SentInvitation>(`${environment.apiUrl}/organizations/invitations`, {
      email,
    });
  }

  listEmployees(page: number, limit: number): Observable<Page<Employee>> {
    const params = new HttpParams().set('page', page).set('limit', limit);

    return this.http
      .get<EmployeeList>(`${environment.apiUrl}/organizations/employees`, { params })
      .pipe(map(res => ({ items: res.employees, pagination: res.pagination })));
  }

  listPendingInvitations(page: number, limit: number): Observable<Page<PendingInvitation>> {
    const params = new HttpParams().set('page', page).set('limit', limit);

    return this.http
      .get<PendingInvitationList>(`${environment.apiUrl}/organizations/invitations`, { params })
      .pipe(map(res => ({ items: res.invitations, pagination: res.pagination })));
  }

  cancelInvitation(invitationId: string): Observable<void> {
    return this.http.delete<void>(
      `${environment.apiUrl}/organizations/invitations/${invitationId}`
    );
  }

  removeEmployee(accountId: string): Observable<void> {
    return this.http.delete<void>(`${environment.apiUrl}/organizations/employees/${accountId}`);
  }

  transferOwnership(accountId: string): Observable<void> {
    return this.http.put<void>(
      `${environment.apiUrl}/organizations/employees/${accountId}/owner`,
      {}
    );
  }
}
