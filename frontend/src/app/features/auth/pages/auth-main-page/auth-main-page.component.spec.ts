import { provideHttpClient } from '@angular/common/http';
import { provideHttpClientTesting } from '@angular/common/http/testing';
import { ComponentFixture, TestBed } from '@angular/core/testing';
import { provideRouter } from '@angular/router';
import { EMPTY, of, throwError } from 'rxjs';
import { describe, expect, it, vi } from 'vitest';
import { AuthService } from '../../../../core/services/auth.service';
import {
  AuthProvider,
  InstanceConfigService,
} from '../../../../core/services/instance-config.service';
import { AuthMainPageComponent } from './auth-main-page.component';

/**
 * One browser bundle serves every deployment, so what this screen offers is decided by
 * /instance rather than by the build. A button for a provider the instance has not configured
 * fails for everybody who presses it, which is why the cases below are mostly about what is
 * *not* rendered.
 *
 * A successful handoff is `EMPTY`, matching the service: on success the browser has already left
 * for the provider, so there is nothing for this screen to do afterwards.
 */
describe('AuthMainPageComponent sign-in providers', () => {
  let fixture: ComponentFixture<AuthMainPageComponent>;
  const startOidcLogin = vi.fn();

  async function render(providers: AuthProvider[]): Promise<void> {
    startOidcLogin.mockReset();
    startOidcLogin.mockReturnValue(EMPTY);

    TestBed.resetTestingModule();
    await TestBed.configureTestingModule({
      imports: [AuthMainPageComponent],
      providers: [
        provideHttpClient(),
        provideHttpClientTesting(),
        provideRouter([]),
        { provide: AuthService, useValue: { login: vi.fn(), startOidcLogin } },
        {
          provide: InstanceConfigService,
          useValue: {
            allowsSignup: () => of(false),
            authProviders: () => of(providers),
          },
        },
      ],
    }).compileComponents();

    fixture = TestBed.createComponent(AuthMainPageComponent);
    fixture.detectChanges();
  }

  function button(provider: string): HTMLButtonElement | null {
    return fixture.nativeElement.querySelector(`[data-testid="provider-button-${provider}"]`);
  }

  function providerError(): string | undefined {
    const element: HTMLElement | null = fixture.nativeElement.querySelector(
      '[data-testid="provider-error"]'
    );

    return element?.textContent?.trim();
  }

  it('offers nothing extra on an instance with local accounts only', async () => {
    await render([]);

    expect(fixture.nativeElement.querySelector('[data-testid="auth-providers"]')).toBeNull();
  });

  it('offers the provider under the name its operator gave it', async () => {
    await render([{ provider: 'oidc', label: 'MyBenefit SSO' }]);

    expect(button('oidc')?.textContent).toContain('Continue with MyBenefit SSO');
  });

  it('names an unlabelled provider itself rather than calling it OIDC', async () => {
    await render([{ provider: 'oidc', label: '' }]);

    expect(button('oidc')?.textContent).toContain('Continue with single sign-on');
  });

  it('hides Google, which has no browser flow to start', async () => {
    // /instance reports it when an operator configured it, but nothing here can begin that
    // flow — the API only accepts a finished Google id_token. Rendering the button would
    // promise a sign-in that cannot happen.
    await render([
      { provider: 'google', label: '' },
      { provider: 'oidc', label: 'Dex' },
    ]);

    expect(button('google')).toBeNull();
    expect(button('oidc')).not.toBeNull();
  });

  it('asks the service to hand the browser over', async () => {
    await render([{ provider: 'oidc', label: 'Dex' }]);

    button('oidc')?.click();
    fixture.detectChanges();

    expect(startOidcLogin).toHaveBeenCalled();
    expect(providerError()).toBeUndefined();
  });

  it('starts one flow however often the button is pressed', async () => {
    // Each start mints a new state and overwrites the flow cookie, so a double click would
    // leave the browser carrying a state the issuer was never told about.
    await render([{ provider: 'oidc', label: 'Dex' }]);

    button('oidc')?.click();
    button('oidc')?.click();
    fixture.detectChanges();

    expect(startOidcLogin).toHaveBeenCalledTimes(1);
  });

  it('says so in place when the handoff cannot be started', async () => {
    // ErrorInterceptor stays quiet on failed GETs, so without this the button would simply
    // stop working with no explanation.
    await render([{ provider: 'oidc', label: 'Dex' }]);
    startOidcLogin.mockReturnValue(throwError(() => new Error('nope')));

    button('oidc')?.click();
    fixture.detectChanges();

    expect(providerError()).toContain('Could not start the sign-in with Dex');
    expect(button('oidc')?.disabled).toBe(false);
  });
});
