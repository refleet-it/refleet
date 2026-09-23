import { HttpClient, HttpParams } from '@angular/common/http';
import { inject, Injectable } from '@angular/core';
import { map, Observable } from 'rxjs';
import { environment } from '../../../environments/environment';
import { Page } from '../models/pagination.model';
import { ProjectList, ProjectOverview } from '../models/project.model';

@Injectable({
  providedIn: 'root',
})
export class ProjectService {
  private readonly http = inject(HttpClient);

  getProjects(page: number, limit: number): Observable<Page<ProjectOverview>> {
    const params = new HttpParams().set('page', page).set('limit', limit);

    return this.http
      .get<ProjectList>(`${environment.apiUrl}/projects`, { params })
      .pipe(map(res => ({ items: res.projects, pagination: res.pagination })));
  }
}
