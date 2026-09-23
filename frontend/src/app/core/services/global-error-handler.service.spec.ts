import { PLATFORM_ID } from '@angular/core';
import { TestBed } from '@angular/core/testing';
import { HttpErrorResponse } from '@angular/common/http';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { GlobalErrorHandler } from './global-error-handler.service';

describe('GlobalErrorHandler', () => {
  let handler: GlobalErrorHandler;
  let sendBeaconSpy: ReturnType<typeof vi.fn>;
  let fetchSpy: ReturnType<typeof vi.fn>;
  let consoleErrorSpy: ReturnType<typeof vi.spyOn>;

  beforeEach(() => {
    TestBed.configureTestingModule({
      providers: [GlobalErrorHandler, { provide: PLATFORM_ID, useValue: 'browser' }],
    });
    handler = TestBed.inject(GlobalErrorHandler);

    sendBeaconSpy = vi.fn().mockReturnValue(true);
    Object.defineProperty(navigator, 'sendBeacon', {
      value: sendBeaconSpy,
      configurable: true,
    });

    fetchSpy = vi.fn().mockResolvedValue(new Response());
    vi.stubGlobal('fetch', fetchSpy);

    consoleErrorSpy = vi.spyOn(console, 'error').mockImplementation(() => undefined);
  });

  afterEach(() => {
    vi.unstubAllGlobals();
    vi.restoreAllMocks();
  });

  it('logs and reports a plain runtime error via sendBeacon', () => {
    handler.handleError(new TypeError('boom'));

    expect(consoleErrorSpy).toHaveBeenCalled();
    expect(sendBeaconSpy).toHaveBeenCalledTimes(1);
    expect(fetchSpy).not.toHaveBeenCalled();

    const [url, blob] = sendBeaconSpy.mock.calls[0];
    expect(url).toContain('/client-errors');
    expect(blob).toBeInstanceOf(Blob);
  });

  it('does not report HttpErrorResponse - already visible via the API error pipeline', () => {
    handler.handleError(new HttpErrorResponse({ status: 500 }));

    expect(consoleErrorSpy).toHaveBeenCalled();
    expect(sendBeaconSpy).not.toHaveBeenCalled();
    expect(fetchSpy).not.toHaveBeenCalled();
  });

  it('falls back to fetch when sendBeacon is unavailable', () => {
    Object.defineProperty(navigator, 'sendBeacon', {
      value: undefined,
      configurable: true,
    });

    handler.handleError(new Error('no beacon here'));

    expect(fetchSpy).toHaveBeenCalledTimes(1);
    const [url, init] = fetchSpy.mock.calls[0];
    expect(url).toContain('/client-errors');
    expect(init.method).toBe('POST');
    expect(init.keepalive).toBe(true);
  });
});
