import { PLATFORM_ID } from '@angular/core';
import { TestBed } from '@angular/core/testing';
import { describe, expect, it, vi } from 'vitest';
import { ToastService } from '../../shared/services/toast.service';
import { ApiError, ValidationError } from '../models/error.model';
import { ErrorHandlerService } from './error-handler.service';

/**
 * This service decides what a user is told when a request fails, so the cases that matter are the
 * ones where the API says something the front end has never heard of.
 */
function setUp(platform: 'browser' | 'server' = 'browser') {
  const add = vi.fn();
  const clear = vi.fn();

  TestBed.resetTestingModule();
  TestBed.configureTestingModule({
    providers: [
      { provide: ToastService, useValue: { add, clear } },
      { provide: PLATFORM_ID, useValue: platform },
    ],
  });

  return { service: TestBed.inject(ErrorHandlerService), add, clear };
}

function apiError(code: string): ApiError {
  return { error: code, message: 'raw message from the API', details: {} };
}

describe('ErrorHandlerService', () => {
  it('shows the wording that belongs to a known code', () => {
    const { service, add } = setUp();

    service.handleError(apiError('invalid_credentials'));

    expect(add).toHaveBeenCalledOnce();
    expect(add.mock.calls[0][0]).toMatchObject({
      severity: 'error',
      summary: 'Invalid credentials',
      detail: 'Email or password is incorrect.',
    });
  });

  // A code the front end has never seen still has to produce a readable toast: falling through to
  // an empty summary would leave the user with a blank box and no idea what failed.
  it.each([
    ['a code with no translation', 'some_code_added_later'],
    ['an empty code', ''],
  ])('falls back to the unknown-error wording for %s', (_label, code) => {
    const { service, add } = setUp();

    service.handleError(apiError(code));

    expect(add.mock.calls[0][0]).toMatchObject({
      summary: 'Unknown error',
      detail: 'An unexpected problem occurred. Please contact the administrator.',
    });
  });

  // Rendering happens on the server too, where there is no one to show a toast to and no toast
  // container to hold it.
  it('says nothing while rendering on the server', () => {
    const { service, add } = setUp('server');

    service.handleError(apiError('internal_error'));
    service.handleValidationErrors({ email: { field: 'email', message: 'Taken', code: 'x' } });

    expect(add).not.toHaveBeenCalled();
  });

  it('raises one toast per validation error', () => {
    const { service, add } = setUp();
    const errors: Record<string, ValidationError | ValidationError[]> = {
      email: [
        { field: 'email', message: 'Already taken', code: 'unique' },
        { field: 'email', message: 'Too long', code: 'length' },
      ],
      name: { field: 'name', message: 'Required', code: 'required' },
    };

    service.handleValidationErrors(errors);

    expect(add).toHaveBeenCalledTimes(3);
    expect(add.mock.calls.map(call => call[0].detail)).toEqual([
      'Already taken',
      'Too long',
      'Required',
    ]);
  });

  it('labels a known field and passes an unknown one through unchanged', () => {
    const { service, add } = setUp();

    service.handleValidationErrors({
      email: { field: 'email', message: 'Taken', code: 'unique' },
      someUnknownField: { field: 'someUnknownField', message: 'Invalid', code: 'shape' },
    });

    expect(add.mock.calls[0][0].summary).toBe('Error: Email');
    expect(add.mock.calls[1][0].summary).toBe('Error: someUnknownField');
  });

  it('clears through the toast service', () => {
    const { service, clear } = setUp();

    service.clearErrors();

    expect(clear).toHaveBeenCalledOnce();
  });
});
