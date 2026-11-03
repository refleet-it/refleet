import { provideHttpClient } from '@angular/common/http';
import { HttpTestingController, provideHttpClientTesting } from '@angular/common/http/testing';
import { TestBed } from '@angular/core/testing';
import { beforeEach, describe, expect, it } from 'vitest';
import { environment } from '../../../environments/environment';
import { InstanceConfig, InstanceConfigService } from './instance-config.service';

/**
 * The sign-in screen iterates `authProviders`, so a shape this service lets through unchanged is
 * a shape that renders. Every case below is about what reaches a template.
 */
describe('InstanceConfigService', () => {
  let service: InstanceConfigService;
  let httpMock: HttpTestingController;

  beforeEach(() => {
    TestBed.configureTestingModule({
      providers: [provideHttpClient(), provideHttpClientTesting(), InstanceConfigService],
    });

    service = TestBed.inject(InstanceConfigService);
    httpMock = TestBed.inject(HttpTestingController);
  });

  function respondWith(body: Record<string, unknown>): void {
    httpMock.expectOne(`${environment.apiUrl}/instance`).flush(body);
  }

  it('passes the configured providers through in the order the instance listed them', () => {
    let providers: InstanceConfig['authProviders'] = [];
    service.authProviders().subscribe(value => (providers = value));

    respondWith({
      allowSignup: true,
      supportEmail: '',
      termsUrl: '',
      authProviders: [
        { provider: 'google', label: '' },
        { provider: 'oidc', label: 'MyBenefit SSO' },
      ],
    });

    expect(providers.map(p => p.provider)).toEqual(['google', 'oidc']);
    expect(providers[1].label).toBe('MyBenefit SSO');
  });

  it('reports no providers when the backend predates the field', () => {
    let providers: InstanceConfig['authProviders'] = [{ provider: 'stale', label: '' }];
    service.authProviders().subscribe(value => (providers = value));

    respondWith({ allowSignup: false, supportEmail: '', termsUrl: '' });

    expect(providers).toEqual([]);
  });

  it('treats a provider list that is not a list as none at all', () => {
    // Rather than letting an @for loop throw inside the sign-in screen.
    let providers: InstanceConfig['authProviders'] = [{ provider: 'stale', label: '' }];
    service.authProviders().subscribe(value => (providers = value));

    respondWith({ allowSignup: false, supportEmail: '', termsUrl: '', authProviders: 'oidc' });

    expect(providers).toEqual([]);
  });

  it('offers nothing when the instance cannot be reached', () => {
    // Offering a sign-in that cannot work is worse than hiding one that could.
    let config: InstanceConfig | null = null;
    service.config().subscribe(value => (config = value));

    httpMock.expectOne(`${environment.apiUrl}/instance`).error(new ProgressEvent('offline'));

    expect(config).toEqual({
      allowSignup: false,
      supportEmail: '',
      termsUrl: '',
      authProviders: [],
    });
  });

  it('asks the instance once and replays the answer', () => {
    service.config().subscribe();
    service.authProviders().subscribe();

    respondWith({ allowSignup: false, supportEmail: '', termsUrl: '', authProviders: [] });

    httpMock.expectNone(`${environment.apiUrl}/instance`);
  });
});
