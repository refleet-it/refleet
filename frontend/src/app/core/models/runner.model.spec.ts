import { describe, expect, it } from 'vitest';
import { rateLimitWindowLabel } from './runner.model';

describe('rateLimitWindowLabel', () => {
  it('names the windows the claude CLI reports', () => {
    expect(rateLimitWindowLabel('five_hour')).toBe('5-hour window');
    expect(rateLimitWindowLabel('seven_day')).toBe('7-day window');
    expect(rateLimitWindowLabel('extra_usage')).toBe('Extra usage');
  });

  it('keeps the model of a per-model weekly window', () => {
    expect(rateLimitWindowLabel('seven_day:claude-opus-5')).toBe('7-day window (claude-opus-5)');
  });

  it('shows an unknown window verbatim rather than hiding it', () => {
    expect(rateLimitWindowLabel('monthly')).toBe('monthly');
  });
});
