import { TestBed } from '@angular/core/testing';
import {
  ActivatedRouteSnapshot,
  CanActivateFn,
  Router,
  RouterStateSnapshot,
  UrlTree,
} from '@angular/router';
import { BehaviorSubject, Observable, of, throwError } from 'rxjs';
import { describe, expect, it, vi } from 'vitest';
import { AuthService, User } from '../services/auth.service';
import { OrganizationService } from '../services/organization.service';
import { AuthGuard } from './auth.guard';
import { GuestGuard } from './guest.guard';
import { NoOrganizationGuard } from './no-organization.guard';
import { OrganizationRequiredGuard } from './organization-required.guard';
import { RoleGuard } from './role.guard';
import { RootRedirectGuard } from './root-redirect.guard';

const anAdmin: User = { id: '1', email: 'admin@refleet.test', role: 'administrator' };
const aUser: User = { id: '2', email: 'user@refleet.test', role: 'user' };

interface AuthStub {
  isTokenExpired: () => boolean;
  isAuthenticated: () => boolean;
  getJwtToken: () => string | null;
  getCurrentUser: () => User | null;
  refreshToken: () => Observable<unknown>;
  currentUser$: Observable<User | null>;
}

function setUp(
  auth: Partial<AuthStub> = {},
  organization: { organization: unknown } | Error = { organization: null }
) {
  const navigate = vi.fn();
  const createUrlTree = vi.fn((commands: unknown[]) => commands.join('/') as unknown as UrlTree);

  const authStub: AuthStub = {
    isTokenExpired: () => true,
    isAuthenticated: () => false,
    getJwtToken: () => null,
    getCurrentUser: () => null,
    refreshToken: () => of({}),
    currentUser$: new BehaviorSubject<User | null>(null).asObservable(),
    ...auth,
  };

  TestBed.configureTestingModule({
    providers: [
      { provide: AuthService, useValue: authStub },
      { provide: Router, useValue: { navigate, createUrlTree } },
      {
        provide: OrganizationService,
        useValue: {
          getMyOrganization: () =>
            organization instanceof Error ? throwError(() => organization) : of(organization),
        },
      },
    ],
  });

  return { navigate, createUrlTree };
}

function run<T>(guard: CanActivateFn, route?: ActivatedRouteSnapshot, url = '/dashboard'): T {
  return TestBed.runInInjectionContext(() =>
    guard(route ?? ({ data: {} } as unknown as ActivatedRouteSnapshot), {
      url,
    } as RouterStateSnapshot)
  ) as T;
}

function routeRequiring(role: string): ActivatedRouteSnapshot {
  return { data: { role } } as unknown as ActivatedRouteSnapshot;
}

describe('AuthGuard', () => {
  it('lets a request through on a valid token without asking for a refresh', async () => {
    const refreshToken = vi.fn(() => of({}));
    setUp({ isTokenExpired: () => false, refreshToken });

    await expect(run<Observable<boolean>>(AuthGuard).toPromise()).resolves.toBe(true);
    expect(refreshToken).not.toHaveBeenCalled();
  });

  it('refreshes an expired token rather than bouncing straight to login', async () => {
    setUp({ isTokenExpired: () => true, refreshToken: () => of({ token: 'new' }) });

    await expect(run<Observable<boolean>>(AuthGuard).toPromise()).resolves.toBe(true);
  });

  it('sends the visitor to /auth, remembering where they were going, when the refresh fails', async () => {
    const { navigate } = setUp({
      isTokenExpired: () => true,
      refreshToken: () => throwError(() => new Error('401')),
    });

    await expect(
      run<Observable<boolean>>(AuthGuard, undefined, '/cli/authorize/abc').toPromise()
    ).resolves.toBe(false);
    expect(navigate).toHaveBeenCalledWith(['/auth'], {
      queryParams: { returnUrl: '/cli/authorize/abc' },
    });
  });
});

describe('GuestGuard', () => {
  it('admits a visitor with no token', () => {
    setUp({ getJwtToken: () => null });

    expect(run<boolean>(GuestGuard)).toBe(true);
  });

  it('admits a visitor whose token has expired', () => {
    setUp({ getJwtToken: () => 'stale', isTokenExpired: () => true });

    expect(run<boolean>(GuestGuard)).toBe(true);
  });

  it('turns a signed-in visitor away from guest-only routes', () => {
    const { navigate } = setUp({ getJwtToken: () => 'valid', isTokenExpired: () => false });

    expect(run<boolean>(GuestGuard)).toBe(false);
    expect(navigate).toHaveBeenCalledWith(['/']);
  });
});

describe('RootRedirectGuard', () => {
  it('sends a guest to sign in, since `/` renders nothing', () => {
    const { createUrlTree } = setUp({ isAuthenticated: () => false });

    run<UrlTree>(RootRedirectGuard);
    expect(createUrlTree).toHaveBeenCalledWith(['/auth']);
  });

  it('sends an administrator to the admin panel', () => {
    const { createUrlTree } = setUp({
      isAuthenticated: () => true,
      getCurrentUser: () => anAdmin,
    });

    run<UrlTree>(RootRedirectGuard);
    expect(createUrlTree).toHaveBeenCalledWith(['/admin']);
  });

  it('sends everyone else to the dashboard', () => {
    const { createUrlTree } = setUp({
      isAuthenticated: () => true,
      getCurrentUser: () => aUser,
    });

    run<UrlTree>(RootRedirectGuard);
    expect(createUrlTree).toHaveBeenCalledWith(['/dashboard']);
  });
});

describe('RoleGuard', () => {
  it('admits a user whose role matches', () => {
    setUp({ getCurrentUser: () => anAdmin });

    expect(run<boolean>(RoleGuard, routeRequiring('administrator'))).toBe(true);
  });

  it('redirects a user whose role does not match', () => {
    const { navigate } = setUp({ getCurrentUser: () => aUser });

    expect(run<boolean>(RoleGuard, routeRequiring('administrator'))).toBe(false);
    expect(navigate).toHaveBeenCalledWith(['/']);
  });

  it('waits for the user to arrive when auth has not resolved yet', async () => {
    const user$ = new BehaviorSubject<User | null>(null);
    setUp({ getCurrentUser: () => null, currentUser$: user$.asObservable() });

    const result = run<Observable<boolean>>(RoleGuard, routeRequiring('administrator')).toPromise();
    user$.next(anAdmin);

    await expect(result).resolves.toBe(true);
  });
});

describe('OrganizationRequiredGuard', () => {
  it('admits an account that belongs to an organization', async () => {
    setUp({}, { organization: { id: 'org-1' } });

    await expect(
      run<Observable<boolean | UrlTree>>(OrganizationRequiredGuard).toPromise()
    ).resolves.toBe(true);
  });

  it('sends an account without one to the creation screen', async () => {
    const { createUrlTree } = setUp({}, { organization: null });

    await run<Observable<boolean | UrlTree>>(OrganizationRequiredGuard).toPromise();
    expect(createUrlTree).toHaveBeenCalledWith(['/organization/create']);
  });

  it('fails open when the lookup errors, so an outage cannot lock everyone out', async () => {
    setUp({}, new Error('500'));

    await expect(
      run<Observable<boolean | UrlTree>>(OrganizationRequiredGuard).toPromise()
    ).resolves.toBe(true);
  });
});

describe('NoOrganizationGuard', () => {
  it('admits an account that has no organization yet', async () => {
    setUp({}, { organization: null });

    await expect(run<Observable<boolean | UrlTree>>(NoOrganizationGuard).toPromise()).resolves.toBe(
      true
    );
  });

  it('sends an account that already has one to the dashboard', async () => {
    const { createUrlTree } = setUp({}, { organization: { id: 'org-1' } });

    await run<Observable<boolean | UrlTree>>(NoOrganizationGuard).toPromise();
    expect(createUrlTree).toHaveBeenCalledWith(['/dashboard']);
  });
});
