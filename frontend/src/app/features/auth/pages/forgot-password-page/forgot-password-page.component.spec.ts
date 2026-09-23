import { provideHttpClient } from '@angular/common/http';
import { provideHttpClientTesting } from '@angular/common/http/testing';
import { ComponentFixture, TestBed } from '@angular/core/testing';
import { provideRouter } from '@angular/router';
import { beforeEach, describe, expect, it } from 'vitest';
import { AuthService } from '../../../../core/services/auth.service';
import { ForgotPasswordPageComponent } from './forgot-password-page.component';

describe('ForgotPasswordPageComponent', () => {
  let fixture: ComponentFixture<ForgotPasswordPageComponent>;
  let component: ForgotPasswordPageComponent;

  beforeEach(async () => {
    await TestBed.configureTestingModule({
      imports: [ForgotPasswordPageComponent],
      providers: [
        provideHttpClient(),
        provideHttpClientTesting(),
        provideRouter([]),
        { provide: AuthService, useValue: {} },
      ],
    }).compileComponents();

    fixture = TestBed.createComponent(ForgotPasswordPageComponent);
    component = fixture.componentInstance;
    fixture.detectChanges();
  });

  function fieldError(): HTMLElement | null {
    return fixture.nativeElement.querySelector('[data-testid="email-error"]');
  }

  it('says nothing until the field has been touched', () => {
    component.forgotPasswordForm.get('email')?.setValue('not-an-email');
    fixture.detectChanges();

    expect(fieldError()).toBeNull();
  });

  it('renders the invalid address message inside the field once touched', () => {
    const email = component.forgotPasswordForm.get('email');
    email?.setValue('not-an-email');
    email?.markAsTouched();
    fixture.detectChanges();

    expect(fieldError()?.textContent).toContain('valid email address');
    expect(fixture.nativeElement.querySelector('small.p-error')).toBeNull();
  });

  it('drops the message once the address is valid', () => {
    const email = component.forgotPasswordForm.get('email');
    email?.setValue('not-an-email');
    email?.markAsTouched();
    fixture.detectChanges();
    expect(fieldError()).not.toBeNull();

    email?.setValue('someone@example.test');
    fixture.detectChanges();

    expect(fieldError()).toBeNull();
  });
});
