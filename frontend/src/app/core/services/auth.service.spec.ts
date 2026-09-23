import { provideHttpClient } from '@angular/common/http';
import { provideHttpClientTesting } from '@angular/common/http/testing';
import { PLATFORM_ID } from '@angular/core';
import { TestBed } from '@angular/core/testing';
import { Router } from '@angular/router';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import { AuthService } from './auth.service';

/**
 * Every guard in the app asks this service whether the token is still good, so a wrong answer here
 * either locks everyone out or lets anyone in. The cases below are about that question and the
 * identity the token carries; the HTTP calls around them are covered where they are used.
 */
function jwt(payload: Record<string, unknown>): string {
  return `header.${btoa(JSON.stringify(payload))}.signature`;
}

const inAnHour = () => Math.floor(Date.now() / 1000) + 3600;
const anHourAgo = () => Math.floor(Date.now() / 1000) - 3600;

describe('AuthService token handling', () => {
  let service: AuthService;

  beforeEach(() => {
    TestBed.configureTestingModule({
      providers: [
        provideHttpClient(),
        provideHttpClientTesting(),
        { provide: Router, useValue: { navigate: vi.fn() } },
        // 'server' keeps the constructor from attaching visibilitychange listeners to a document
        // these cases never touch.
        { provide: PLATFORM_ID, useValue: 'server' },
      ],
    });

    service = TestBed.inject(AuthService);
  });

  it('treats the absence of a token as expired rather than valid', () => {
    expect(service.isTokenExpired()).toBe(true);
    expect(service.isAuthenticated()).toBe(false);
  });

  it('accepts a token that has not run out yet', () => {
    service.updateToken(jwt({ sub: '1', email: 'a@refleet.it', role: 'user', exp: inAnHour() }));

    expect(service.isTokenExpired()).toBe(false);
    expect(service.isAuthenticated()).toBe(true);
  });

  it('rejects a token whose exp has passed', () => {
    service.updateToken(jwt({ sub: '1', email: 'a@refleet.it', role: 'user', exp: anHourAgo() }));

    expect(service.isTokenExpired()).toBe(true);
    expect(service.isAuthenticated()).toBe(false);
  });

  // These end the session outright rather than being stored and judged later: updateToken hands
  // the string to the decoder, which logs out when it cannot read it.
  it.each([
    ['no dots', 'not-a-jwt'],
    ['too few segments', 'header.payload'],
    ['payload that is not base64 JSON', 'header.@@@.signature'],
  ])('discards a token with %s instead of keeping it', (_label, token) => {
    service.updateToken(jwt({ sub: '1', email: 'a@refleet.it', role: 'user', exp: inAnHour() }));

    service.updateToken(token);

    expect(service.getJwtToken()).toBeNull();
    expect(service.isTokenExpired()).toBe(true);
    expect(service.isAuthenticated()).toBe(false);
  });

  // Readable, so it survives decoding and is kept — but it claims no expiry. Multiplying an absent
  // exp gives NaN, and NaN < now is false, which read as a session that never ends.
  it('treats a token with no exp claim as expired', () => {
    service.updateToken(jwt({ sub: '1', email: 'a@refleet.it', role: 'user' }));

    expect(service.getJwtToken()).not.toBeNull();
    expect(service.isTokenExpired()).toBe(true);
    expect(service.isAuthenticated()).toBe(false);
    expect(service.getTokenExpirationTime()).toBeNull();
  });

  it('reads the identity out of the token, role included', () => {
    service.updateToken(
      jwt({ sub: 'account-7', email: 'admin@refleet.test', role: 'administrator', exp: inAnHour() })
    );

    expect(service.getCurrentUser()).toEqual({
      id: 'account-7',
      email: 'admin@refleet.test',
      role: 'administrator',
    });
  });

  it('drops the session when handed a token it cannot read', () => {
    service.updateToken(jwt({ sub: '1', email: 'a@refleet.it', role: 'user', exp: inAnHour() }));
    expect(service.getCurrentUser()).not.toBeNull();

    service.updateToken('rubbish');

    expect(service.getCurrentUser()).toBeNull();
    expect(service.getJwtToken()).toBeNull();
  });

  it('reports the expiry as milliseconds, and nothing without a token', () => {
    expect(service.getTokenExpirationTime()).toBeNull();

    const exp = inAnHour();
    service.updateToken(jwt({ sub: '1', email: 'a@refleet.it', role: 'user', exp }));

    expect(service.getTokenExpirationTime()).toBe(exp * 1000);
  });
});
