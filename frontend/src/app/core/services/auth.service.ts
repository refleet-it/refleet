import { Injectable, inject, PLATFORM_ID, NgZone } from '@angular/core';
import { isPlatformBrowser } from '@angular/common';
import { HttpClient } from '@angular/common/http';
import { Router } from '@angular/router';
import {
  BehaviorSubject,
  Observable,
  tap,
  catchError,
  throwError,
  map,
  of,
  filter,
  take,
  switchMap,
  share,
} from 'rxjs';
import { environment } from '../../../environments/environment';

export interface AuthTokens {
  jwtToken: string;
}

export interface User {
  id: string;
  email: string;
  role: string;
}

export interface AccountMe {
  id: string;
  email: string;
  role: string;
  status: string;
}

@Injectable({
  providedIn: 'root',
})
export class AuthService {
  private static readonly JWT_PARTS_COUNT = 3;

  private http = inject(HttpClient);
  private router = inject(Router);
  private platformId = inject(PLATFORM_ID);
  private ngZone = inject(NgZone);

  private currentUserSubject = new BehaviorSubject<User | null>(null);
  public currentUser$ = this.currentUserSubject.asObservable();

  public isRefreshing = false;
  public refreshTokenSubject = new BehaviorSubject<string | null>(null);
  private refreshTokenRequest$: Observable<AuthTokens> | null = null;

  private jwtToken: string | null = null;

  private refreshTimer: ReturnType<typeof setTimeout> | null = null;
  private readonly REFRESH_THRESHOLD = 5 * 60 * 1000;
  private readonly VISIBILITY_CHANGE_REFRESH_THRESHOLD = 10 * 60 * 1000;

  constructor() {
    if (isPlatformBrowser(this.platformId)) {
      this.setupVisibilityChangeListener();
    }
  }

  initializeAuth(): Promise<void> {
    if (!isPlatformBrowser(this.platformId)) {
      return Promise.resolve();
    }
    return new Promise(resolve => {
      this.http
        .post<{ token: AuthTokens }>(`${environment.apiUrl}/identity/refresh`, null, {
          withCredentials: true,
        })
        .pipe(
          map(res => res.token),
          catchError(() => {
            this.clearAuthHint();
            return of(null);
          })
        )
        .subscribe(tokens => {
          if (tokens?.jwtToken) {
            this.jwtToken = tokens.jwtToken;
            this.decodeAndSetUser(tokens.jwtToken);
            this.startTokenRefreshTimer();
            this.setAuthHint();
          }
          resolve();
        });
    });
  }

  register(
    email: string,
    password: string,
    termsAccepted: boolean,
    marketingConsent: boolean
  ): Observable<{ message: string }> {
    const payload = {
      email,
      password,
      termsAccepted,
      marketingConsent,
    };

    return this.http
      .post<{ message: string }>(`${environment.apiUrl}/identity/register`, payload)
      .pipe(catchError(err => throwError(() => err)));
  }

  login(email: string, password: string): Observable<AuthTokens> {
    return this.http
      .post<{ token: AuthTokens }>(
        `${environment.apiUrl}/identity/login`,
        {
          email,
          password,
        },
        { withCredentials: true }
      )
      .pipe(
        catchError(err => throwError(() => err)),
        map(res => res.token),
        tap(tokens => {
          this.setTokens(tokens);
          this.decodeAndSetUser(tokens.jwtToken);
          this.startTokenRefreshTimer();
          this.setAuthHint();
        }),
        switchMap(tokens =>
          this.currentUser$.pipe(
            filter((user): user is User => user !== null),
            take(1),
            map(() => tokens)
          )
        )
      );
  }

  loginWithGoogle(idToken: string, marketingConsent = false): Observable<AuthTokens> {
    return this.http
      .post<{ token: AuthTokens }>(
        `${environment.apiUrl}/identity/auth/google/callback`,
        {
          idToken,
          marketingConsent,
        },
        { withCredentials: true }
      )
      .pipe(
        catchError(err => throwError(() => err)),
        map(res => res.token),
        tap(tokens => {
          this.setTokens(tokens);
          this.decodeAndSetUser(tokens.jwtToken);
          this.startTokenRefreshTimer();
          this.setAuthHint();
        }),
        switchMap(tokens =>
          this.currentUser$.pipe(
            filter((user): user is User => user !== null),
            take(1),
            map(() => tokens)
          )
        )
      );
  }

  requestPasswordReset(email: string): Observable<{ message: string }> {
    return this.http
      .post<{ message: string }>(`${environment.apiUrl}/identity/request-password-reset`, {
        email,
      })
      .pipe(catchError(err => throwError(() => err)));
  }

  resetPassword(token: string, newPassword: string): Observable<{ message: string }> {
    return this.http
      .post<{ message: string }>(`${environment.apiUrl}/identity/reset-password`, {
        token,
        newPassword,
      })
      .pipe(catchError(err => throwError(() => err)));
  }

  changePassword(currentPassword: string, newPassword: string): Observable<{ message: string }> {
    return this.http
      .post<{ message: string }>(`${environment.apiUrl}/identity/change-password`, {
        currentPassword,
        newPassword,
      })
      .pipe(catchError(err => throwError(() => err)));
  }

  verifyEmail(token: string): Observable<AuthTokens> {
    return this.http
      .post<{ token: AuthTokens }>(
        `${environment.apiUrl}/identity/verify-email/${token}`,
        {},
        {
          withCredentials: true,
        }
      )
      .pipe(
        catchError(err => throwError(() => err)),
        map(res => res.token),
        tap(tokens => {
          this.setTokens(tokens);
          this.decodeAndSetUser(tokens.jwtToken);
          this.startTokenRefreshTimer();
          this.setAuthHint();
        }),
        switchMap(tokens =>
          this.currentUser$.pipe(
            filter((user): user is User => user !== null),
            take(1),
            map(() => tokens)
          )
        )
      );
  }

  logout(): void {
    this.clearRefreshTimer();
    this.removeVisibilityChangeListeners();
    this.jwtToken = null;
    this.currentUserSubject.next(null);
    this.refreshTokenSubject.next(null);
    this.clearAuthHint();
    this.http
      .post(`${environment.apiUrl}/identity/logout`, null, { withCredentials: true })
      .pipe(catchError(() => of(null)))
      .subscribe();
    this.router.navigate(['/auth']);
  }

  isAuthenticated(): boolean {
    return !!this.jwtToken && !this.isTokenExpired();
  }

  isTokenExpired(): boolean {
    if (!this.jwtToken) {
      return true;
    }

    try {
      const parts = this.jwtToken.split('.');
      if (parts.length !== AuthService.JWT_PARTS_COUNT) {
        return true;
      }

      const payload = JSON.parse(atob(parts[1]));
      // A token that never says when it expires is not a token that never expires: without this
      // the comparison below is NaN < now, which is false, and the session would be treated as
      // good forever. Every other failure here answers "expired", so this one does too.
      if ('number' !== typeof payload.exp) {
        return true;
      }

      return payload.exp * 1000 < Date.now();
    } catch {
      return true;
    }
  }

  getTokenExpirationTime(): number | null {
    if (!this.jwtToken) {
      return null;
    }

    try {
      const parts = this.jwtToken.split('.');
      if (parts.length !== AuthService.JWT_PARTS_COUNT) {
        return null;
      }

      const payload = JSON.parse(atob(parts[1]));

      return 'number' === typeof payload.exp ? payload.exp * 1000 : null;
    } catch {
      return null;
    }
  }

  getJwtToken(): string | null {
    return this.jwtToken;
  }

  getCurrentUser(): User | null {
    return this.currentUserSubject.value;
  }

  getMe(): Observable<AccountMe> {
    return this.http.get<AccountMe>(`${environment.apiUrl}/account/me`, {
      withCredentials: true,
    });
  }

  private setTokens(tokens: AuthTokens): void {
    if (!tokens.jwtToken) {
      throw new Error('Invalid tokens received');
    }
    this.jwtToken = tokens.jwtToken;
  }

  private decodeAndSetUser(token: string): void {
    if (!token || typeof token !== 'string' || !token.includes('.')) {
      this.logout();
      return;
    }

    try {
      const parts = token.split('.');
      if (parts.length !== AuthService.JWT_PARTS_COUNT) {
        this.logout();
        return;
      }

      const payload = JSON.parse(atob(parts[1]));
      const user: User = {
        id: payload.sub,
        email: payload.email,
        role: payload.role,
      };
      this.currentUserSubject.next(user);
    } catch {
      this.logout();
    }
  }

  private startTokenRefreshTimer(): void {
    this.clearRefreshTimer();

    if (this.isRefreshing) {
      return;
    }

    if (!this.jwtToken) {
      return;
    }

    const expirationTime = this.getTokenExpirationTime();
    const now = Date.now();

    if (!expirationTime) {
      this.refreshToken().subscribe({
        next: () => {
          this.startTokenRefreshTimer();
        },
        error: error => {
          console.error('Token refresh failed (no expiration time):', error);
        },
      });
      return;
    }

    const timeUntilExpiration = expirationTime - now;

    if (timeUntilExpiration <= 0) {
      return;
    }

    const timeUntilRefresh = timeUntilExpiration - this.REFRESH_THRESHOLD;

    const scheduleRefresh = (delayMs: number) => {
      this.ngZone.runOutsideAngular(() => {
        this.refreshTimer = setTimeout(() => {
          this.ngZone.run(() => {
            this.refreshToken().subscribe({
              next: () => {
                this.startTokenRefreshTimer();
              },
              error: error => {
                console.error('Token auto refresh failed:', error);
              },
            });
          });
        }, delayMs);
      });
    };

    if (timeUntilRefresh > 0) {
      scheduleRefresh(timeUntilRefresh);
    } else {
      const bufferMs = Math.min(2000, timeUntilExpiration * 0.2);
      const scheduleMs = Math.max(timeUntilExpiration - bufferMs, 0);
      scheduleRefresh(scheduleMs);
    }
  }

  private clearRefreshTimer(): void {
    if (this.refreshTimer) {
      clearTimeout(this.refreshTimer);
      this.refreshTimer = null;
    }
  }

  refreshToken(): Observable<AuthTokens> {
    if (this.refreshTokenRequest$) {
      return this.refreshTokenRequest$;
    }

    this.isRefreshing = true;

    this.refreshTokenRequest$ = this.http
      .post<{
        token: AuthTokens;
      }>(`${environment.apiUrl}/identity/refresh`, null, {
        withCredentials: true,
      })
      .pipe(
        map(res => res.token),
        tap(tokens => {
          this.setTokens(tokens);
          this.decodeAndSetUser(tokens.jwtToken);
          this.isRefreshing = false;
          this.refreshTokenRequest$ = null;
          this.refreshTokenSubject.next(tokens.jwtToken);
          this.startTokenRefreshTimer();
        }),
        catchError(error => {
          this.isRefreshing = false;
          this.refreshTokenRequest$ = null;
          this.refreshTokenSubject.next(null);
          this.logout();
          this.router.navigate(['/auth']);
          return throwError(() => error);
        }),
        share()
      );

    return this.refreshTokenRequest$;
  }

  public redirectAfterAuth(router: Router): void {
    const user = this.getCurrentUser();
    const targetRoute: string[] = user ? ['/'] : ['/auth'];

    router.navigate(targetRoute).then(success => {
      if (!success) {
        console.error('Navigation failed to:', targetRoute);
        setTimeout(() => {
          router.navigate(targetRoute);
        }, 100);
      }
    });
  }

  public updateToken(newToken: string): void {
    this.jwtToken = newToken;
    this.decodeAndSetUser(newToken);
    this.startTokenRefreshTimer();
  }

  private removeVisibilityChangeListeners(): void {
    document.removeEventListener('visibilitychange', this.handleVisibilityChange);
    window.removeEventListener('focus', this.handleWindowFocus);
  }

  private handleVisibilityChange = (): void => {
    if (document.hidden || this.isRefreshing) {
      return;
    }

    if (!this.jwtToken) {
      return;
    }

    const expirationTime = this.getTokenExpirationTime();
    const now = Date.now();

    const shouldRefresh =
      !expirationTime || expirationTime - now < this.VISIBILITY_CHANGE_REFRESH_THRESHOLD;

    if (shouldRefresh) {
      this.refreshToken().subscribe({
        error: error => {
          console.error('Token refresh on visibility change failed:', error);
        },
      });
    }
  };

  private handleWindowFocus = (): void => {
    if (this.isRefreshing) {
      return;
    }

    if (!this.jwtToken) {
      return;
    }

    const expirationTime = this.getTokenExpirationTime();
    const now = Date.now();

    const shouldRefresh = !expirationTime || expirationTime - now < this.REFRESH_THRESHOLD;

    if (shouldRefresh) {
      this.refreshToken().subscribe({
        error: error => {
          console.error('Token refresh on window focus failed:', error);
        },
      });
    }
  };

  private setupVisibilityChangeListener(): void {
    document.addEventListener('visibilitychange', this.handleVisibilityChange);
    window.addEventListener('focus', this.handleWindowFocus);
  }

  // Read by the inline script in index.html, which hides app-root until Angular has resolved
  // the session — otherwise a returning user sees the signed-out shell flash first. It was
  // also mirrored into a cookie, for an SSR shortcut on `/` that no longer exists now that
  // the route renders nothing.
  private setAuthHint(): void {
    if (!isPlatformBrowser(this.platformId)) return;
    localStorage.setItem('auth_hint', '1');
  }

  private clearAuthHint(): void {
    if (!isPlatformBrowser(this.platformId)) return;
    localStorage.removeItem('auth_hint');
  }
}
