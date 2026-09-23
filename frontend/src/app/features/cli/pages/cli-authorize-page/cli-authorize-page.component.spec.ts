import { provideHttpClient } from '@angular/common/http';
import { HttpTestingController, provideHttpClientTesting } from '@angular/common/http/testing';
import { ComponentFixture, TestBed } from '@angular/core/testing';
import { ActivatedRoute, convertToParamMap } from '@angular/router';
import { afterEach, beforeEach, describe, expect, it } from 'vitest';
import { environment } from '../../../../../environments/environment';
import { AuthService } from '../../../../core/services/auth.service';
import { CliAuthorizePageComponent } from './cli-authorize-page.component';

const code = 'a'.repeat(24);
const url = `${environment.apiUrl}/identity/cli-authorizations/${code}`;

describe('CliAuthorizePageComponent', () => {
  let fixture: ComponentFixture<CliAuthorizePageComponent>;
  let httpMock: HttpTestingController;

  beforeEach(async () => {
    await TestBed.configureTestingModule({
      imports: [CliAuthorizePageComponent],
      providers: [
        provideHttpClient(),
        provideHttpClientTesting(),
        {
          provide: ActivatedRoute,
          useValue: { snapshot: { paramMap: convertToParamMap({ code }) } },
        },
        {
          provide: AuthService,
          useValue: {
            getCurrentUser: () => ({ id: '1', email: 'owner@refleet.it', role: 'user' }),
          },
        },
      ],
    }).compileComponents();

    httpMock = TestBed.inject(HttpTestingController);
    fixture = TestBed.createComponent(CliAuthorizePageComponent);
    fixture.detectChanges();
  });

  afterEach(() => httpMock.verify());

  function text(): string {
    return fixture.nativeElement.textContent as string;
  }

  function click(testId: string): void {
    (fixture.nativeElement.querySelector(`[data-testid="${testId}"]`) as HTMLButtonElement).click();
    fixture.detectChanges();
  }

  function respondPending(): void {
    httpMock.expectOne(url).flush({
      runnerName: 'my-laptop',
      status: 'pending',
      createdAt: '2026-09-17T09:00:00+00:00',
      expiresAt: '2026-09-17T09:10:00+00:00',
    });
    fixture.detectChanges();
  }

  it('asks the signed-in account to approve a pending request, naming the machine', () => {
    respondPending();

    expect(text()).toContain('my-laptop');
    expect(text()).toContain('owner@refleet.it');
    expect(
      fixture.nativeElement.querySelector('[data-testid="cli-approve-button"]')
    ).not.toBeNull();
  });

  it('approves and tells the person to go back to the terminal', () => {
    respondPending();

    click('cli-approve-button');
    const approve = httpMock.expectOne(`${url}/approve`);
    expect(approve.request.method).toBe('POST');
    approve.flush(null, { status: 204, statusText: 'No Content' });
    fixture.detectChanges();

    expect(text()).toContain('go back to your terminal');
    expect(fixture.nativeElement.querySelector('[data-testid="cli-approve-button"]')).toBeNull();
  });

  it('denies without granting anything', () => {
    respondPending();

    click('cli-deny-button');
    httpMock.expectOne(`${url}/deny`).flush(null, { status: 204, statusText: 'No Content' });
    fixture.detectChanges();

    expect(text()).toContain('Login denied');
  });

  it('explains an expired link instead of offering the buttons', () => {
    httpMock.expectOne(url).flush({}, { status: 410, statusText: 'Gone' });
    fixture.detectChanges();

    expect(text()).toContain('expired');
    expect(fixture.nativeElement.querySelector('[data-testid="cli-approve-button"]')).toBeNull();
  });

  it('refuses to decide a request that is no longer pending', () => {
    httpMock.expectOne(url).flush({
      runnerName: 'my-laptop',
      status: 'denied',
      createdAt: '2026-09-17T09:00:00+00:00',
      expiresAt: '2026-09-17T09:10:00+00:00',
    });
    fixture.detectChanges();

    expect(text()).toContain('already been decided');
  });
});
