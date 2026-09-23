import { HttpErrorResponse, provideHttpClient } from '@angular/common/http';
import { provideHttpClientTesting } from '@angular/common/http/testing';
import { ComponentFixture, TestBed } from '@angular/core/testing';
import { provideRouter } from '@angular/router';
import { throwError } from 'rxjs';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import { AuthService } from '../../../../core/services/auth.service';
import { RegisterPageComponent } from './register-page.component';

function emailErrorResponse(): HttpErrorResponse {
  return new HttpErrorResponse({
    status: 409,
    error: {
      error: 'email_already_used',
      message: 'This email address is already registered.',
      details: {},
      field: 'email',
    },
  });
}

describe('RegisterPageComponent', () => {
  let fixture: ComponentFixture<RegisterPageComponent>;
  let component: RegisterPageComponent;
  const register = vi.fn();

  beforeEach(async () => {
    register.mockReset();

    await TestBed.configureTestingModule({
      imports: [RegisterPageComponent],
      providers: [
        provideHttpClient(),
        provideHttpClientTesting(),
        provideRouter([]),
        { provide: AuthService, useValue: { register } },
      ],
    }).compileComponents();

    fixture = TestBed.createComponent(RegisterPageComponent);
    component = fixture.componentInstance;
    fixture.detectChanges();

    component.registerForm.setValue({
      email: 'taken@example.test',
      password: 'a-password-long-enough',
      termsAccepted: true,
      marketingConsent: false,
    });
  });

  function serverErrorElement(): HTMLElement | null {
    return fixture.nativeElement.querySelector('[data-testid="email-server-error"]');
  }

  it('calls the sign-in action "Log in", the wording the rest of the app uses', () => {
    const link: HTMLElement = fixture.nativeElement.querySelector('.register-link');

    expect(link.textContent?.trim()).toBe('Log in');
  });

  it('shows a field-named backend error under the email input', () => {
    register.mockReturnValue(throwError(() => emailErrorResponse()));

    component.onSubmit();
    fixture.detectChanges();

    expect(component.emailServerError()).toBe('This email address is already registered.');
    expect(serverErrorElement()?.textContent).toContain('already registered');
  });

  it('leaves the field alone when the backend names no field', () => {
    register.mockReturnValue(
      throwError(
        () =>
          new HttpErrorResponse({
            status: 500,
            error: { error: 'server_error', message: 'Boom', details: {}, field: null },
          })
      )
    );

    component.onSubmit();
    fixture.detectChanges();

    expect(component.emailServerError()).toBeNull();
    expect(serverErrorElement()).toBeNull();
  });

  it('clears the backend error when the email is edited', () => {
    register.mockReturnValue(throwError(() => emailErrorResponse()));
    component.onSubmit();
    fixture.detectChanges();
    expect(serverErrorElement()).not.toBeNull();

    const input: HTMLInputElement = fixture.nativeElement.querySelector(
      '[data-testid="email-input"]'
    );
    input.value = 'free@example.test';
    input.dispatchEvent(new Event('input'));
    fixture.detectChanges();

    expect(component.emailServerError()).toBeNull();
    expect(serverErrorElement()).toBeNull();
  });
});
