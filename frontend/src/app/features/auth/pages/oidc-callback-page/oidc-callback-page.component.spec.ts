import { HttpErrorResponse, provideHttpClient } from '@angular/common/http';
import { provideHttpClientTesting } from '@angular/common/http/testing';
import { ComponentFixture, TestBed } from '@angular/core/testing';
import { ActivatedRoute, convertToParamMap, Router } from '@angular/router';
import { Observable, of, throwError } from 'rxjs';
import { describe, expect, it, vi } from 'vitest';
import { AuthService } from '../../../../core/services/auth.service';
import { OidcCallbackPageComponent } from './oidc-callback-page.component';

/**
 * This page is the last step of a sign-in, so the cases that matter are the ones where it must
 * *not* send the code on, and the ones where it must say why nothing happened. Showing a spinner
 * forever is the failure mode worth guarding against.
 */
function routeWith(params: Record<string, string>): { snapshot: { queryParamMap: unknown } } {
  return { snapshot: { queryParamMap: convertToParamMap(params) } };
}

describe('OidcCallbackPageComponent', () => {
  let fixture: ComponentFixture<OidcCallbackPageComponent>;
  const loginWithOidc = vi.fn();
  const redirectAfterAuth = vi.fn();

  async function render(
    params: Record<string, string>,
    outcome: Observable<{ jwtToken: string }> = of({ jwtToken: 'a-token' })
  ): Promise<void> {
    loginWithOidc.mockReset();
    redirectAfterAuth.mockReset();
    loginWithOidc.mockReturnValue(outcome);

    TestBed.resetTestingModule();
    await TestBed.configureTestingModule({
      imports: [OidcCallbackPageComponent],
      providers: [
        provideHttpClient(),
        provideHttpClientTesting(),
        { provide: Router, useValue: { navigate: vi.fn() } },
        { provide: ActivatedRoute, useValue: routeWith(params) },
        { provide: AuthService, useValue: { loginWithOidc, redirectAfterAuth } },
      ],
    }).compileComponents();

    fixture = TestBed.createComponent(OidcCallbackPageComponent);
    fixture.detectChanges();
  }

  function errorText(): string | undefined {
    const element: HTMLElement | null = fixture.nativeElement.querySelector(
      '[data-testid="oidc-callback-error"]'
    );

    return element?.textContent?.trim();
  }

  it('sends the code and state on, then lets the auth service decide where to go', async () => {
    await render({ code: 'the-code', state: 'the-state' });

    expect(loginWithOidc).toHaveBeenCalledWith('the-code', 'the-state');
    expect(redirectAfterAuth).toHaveBeenCalled();
    expect(errorText()).toBeUndefined();
  });

  it('says the sign-in was refused rather than trying to complete it', async () => {
    await render({ error: 'access_denied' });

    expect(loginWithOidc).not.toHaveBeenCalled();
    expect(errorText()).toContain('cancelled the sign-in');
  });

  it("passes on the provider's own description of any other error", async () => {
    await render({ error: 'temporarily_unavailable', error_description: 'Dex is restarting' });

    expect(loginWithOidc).not.toHaveBeenCalled();
    expect(errorText()).toContain('Dex is restarting');
  });

  it('names the error code when the provider sends no description', async () => {
    await render({ error: 'server_error' });

    expect(errorText()).toContain('server_error');
  });

  it.each([
    ['no state', { code: 'the-code' }],
    ['no code', { state: 'the-state' }],
    ['neither', {}],
  ])('refuses to call the API with %s', async (_description, params) => {
    await render(params);

    expect(loginWithOidc).not.toHaveBeenCalled();
    expect(errorText()).toContain('incomplete');
  });

  it("shows the API's message, which is the side that knows what went wrong", async () => {
    await render(
      { code: 'the-code', state: 'the-state' },
      throwError(
        () =>
          new HttpErrorResponse({
            status: 403,
            error: {
              error: 'self_registration_not_allowed',
              message: 'This instance does not accept self-registration.',
              details: {},
              field: null,
            },
          })
      )
    );

    expect(errorText()).toBe('This instance does not accept self-registration.');
    expect(redirectAfterAuth).not.toHaveBeenCalled();
  });

  it('falls back to its own wording when the failure carries no message', async () => {
    await render(
      { code: 'the-code', state: 'the-state' },
      throwError(() => new HttpErrorResponse({ status: 0, error: null }))
    );

    expect(errorText()).toContain('Could not complete the sign-in');
  });

  it('offers a way back to the login screen instead of a dead end', async () => {
    await render({ error: 'access_denied' });

    const back: HTMLElement | null = fixture.nativeElement.querySelector(
      '[data-testid="oidc-callback-back"]'
    );
    expect(back).not.toBeNull();

    back?.click();

    expect(TestBed.inject(Router).navigate).toHaveBeenCalledWith(['/auth']);
  });
});
