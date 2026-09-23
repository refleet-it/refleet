import { provideHttpClient } from '@angular/common/http';
import { HttpTestingController, provideHttpClientTesting } from '@angular/common/http/testing';
import { TestBed } from '@angular/core/testing';
import { Observable } from 'rxjs';
import { afterEach, beforeEach, describe, expect, it } from 'vitest';
import { environment } from '../../../environments/environment';
import { Page } from '../models/pagination.model';
import { OrganizationService } from './organization.service';
import { ProjectService } from './project.service';
import { QualificationService } from './qualification.service';
import { RunnerService } from './runner.service';
import { ShiftService } from './shift.service';

/**
 * Every list endpoint answers with its own collection key — shifts, targets, runners, jobs — and
 * each service renames it to `items` for injectPagedList. Reading the wrong key yields undefined,
 * which renders as an empty list rather than an error, so nothing would report the mistake.
 */
const pagination = { page: 1, limit: 20, total: 1, pages: 1 };

interface ListCase {
  name: string;
  call: () => Observable<Page<unknown>>;
  url: string;
  responseKey: string;
}

describe('list services', () => {
  let httpMock: HttpTestingController;
  let cases: ListCase[];

  beforeEach(() => {
    TestBed.configureTestingModule({
      providers: [provideHttpClient(), provideHttpClientTesting()],
    });

    httpMock = TestBed.inject(HttpTestingController);

    const shifts = TestBed.inject(ShiftService);
    const qualifications = TestBed.inject(QualificationService);
    const runners = TestBed.inject(RunnerService);
    const organizations = TestBed.inject(OrganizationService);
    const projects = TestBed.inject(ProjectService);

    cases = [
      {
        name: 'ShiftService.list',
        call: () => shifts.list(1, 20),
        url: `${environment.apiUrl}/shifts`,
        responseKey: 'shifts',
      },
      {
        name: 'ShiftService.listTargets',
        call: () => shifts.listTargets('shift-1', 1, 20),
        url: `${environment.apiUrl}/shifts/shift-1/targets`,
        responseKey: 'targets',
      },
      {
        name: 'QualificationService.list',
        call: () => qualifications.list(1, 20),
        url: `${environment.apiUrl}/qualifications`,
        responseKey: 'qualifications',
      },
      {
        name: 'QualificationService.listTargets',
        call: () => qualifications.listTargets('qualification-1', 1, 20),
        url: `${environment.apiUrl}/qualifications/qualification-1/targets`,
        responseKey: 'targets',
      },
      {
        name: 'RunnerService.list',
        call: () => runners.list(1, 20),
        url: `${environment.apiUrl}/runners`,
        responseKey: 'runners',
      },
      {
        name: 'RunnerService.listJobs',
        call: () => runners.listJobs('runner-1', 1, 20),
        url: `${environment.apiUrl}/runners/runner-1/jobs`,
        responseKey: 'jobs',
      },
      {
        name: 'OrganizationService.listEmployees',
        call: () => organizations.listEmployees(1, 20),
        url: `${environment.apiUrl}/organizations/employees`,
        responseKey: 'employees',
      },
      {
        name: 'OrganizationService.listPendingInvitations',
        call: () => organizations.listPendingInvitations(1, 20),
        url: `${environment.apiUrl}/organizations/invitations`,
        responseKey: 'invitations',
      },
      {
        name: 'ProjectService.getProjects',
        call: () => projects.getProjects(1, 20),
        url: `${environment.apiUrl}/projects`,
        responseKey: 'projects',
      },
    ];
  });

  afterEach(() => {
    httpMock.verify();
  });

  it.each([0, 1, 2, 3, 4, 5, 6, 7, 8])('reshapes the response of case %i', index => {
    const testCase = cases[index];
    let page: Page<unknown> | undefined;

    testCase.call().subscribe(result => (page = result));

    const req = httpMock.expectOne(request => request.url === testCase.url);
    expect(req.request.method).toBe('GET');
    expect(req.request.params.get('page')).toBe('1');
    expect(req.request.params.get('limit')).toBe('20');

    req.flush({ [testCase.responseKey]: [{ id: 'a' }], pagination });

    expect(page, `${testCase.name} read the wrong collection key`).toEqual({
      items: [{ id: 'a' }],
      pagination,
    });
  });
});

describe('optional list filters', () => {
  let httpMock: HttpTestingController;
  let shifts: ShiftService;
  let qualifications: QualificationService;

  beforeEach(() => {
    TestBed.configureTestingModule({
      providers: [provideHttpClient(), provideHttpClientTesting()],
    });

    httpMock = TestBed.inject(HttpTestingController);
    shifts = TestBed.inject(ShiftService);
    qualifications = TestBed.inject(QualificationService);
  });

  afterEach(() => {
    httpMock.verify();
  });

  // An unset filter has to be absent, not sent as the string "undefined" — the backend would treat
  // that as a search term and answer with nothing.
  it('omits search and status when they are not given', () => {
    shifts.list(1, 20).subscribe();

    const req = httpMock.expectOne(request => request.url === `${environment.apiUrl}/shifts`);
    expect(req.request.params.has('search')).toBe(false);
    expect(req.request.params.has('status')).toBe(false);
    req.flush({ shifts: [], pagination });
  });

  it('sends them when they are', () => {
    shifts.list(1, 20, 'auth', 'completed').subscribe();

    const req = httpMock.expectOne(request => request.url === `${environment.apiUrl}/shifts`);
    expect(req.request.params.get('search')).toBe('auth');
    expect(req.request.params.get('status')).toBe('completed');
    req.flush({ shifts: [], pagination });
  });

  it('keeps an empty search out of the query', () => {
    qualifications.list(1, 20, '').subscribe();

    const req = httpMock.expectOne(
      request => request.url === `${environment.apiUrl}/qualifications`
    );
    expect(req.request.params.has('search')).toBe(false);
    req.flush({ qualifications: [], pagination });
  });

  it('asks for archived runners only when told to', () => {
    runnersFor().list(1, 20).subscribe();
    const listed = httpMock.expectOne(request => request.url === `${environment.apiUrl}/runners`);
    expect(listed.request.params.get('archived')).toBe('false');
    listed.flush({ runners: [], pagination });

    runnersFor().list(1, 20, true).subscribe();
    const archived = httpMock.expectOne(request => request.url === `${environment.apiUrl}/runners`);
    expect(archived.request.params.get('archived')).toBe('true');
    archived.flush({ runners: [], pagination });
  });

  function runnersFor(): RunnerService {
    return TestBed.inject(RunnerService);
  }
});
