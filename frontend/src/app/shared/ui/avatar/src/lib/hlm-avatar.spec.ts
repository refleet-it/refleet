import { Component, signal } from '@angular/core';
import { ComponentFixture, TestBed } from '@angular/core/testing';
import { beforeEach, describe, expect, it } from 'vitest';
import { HlmAvatar } from './hlm-avatar';
import { HlmAvatarFallback } from './hlm-avatar-fallback';
import { HlmAvatarImage } from './hlm-avatar-image';

@Component({
  imports: [HlmAvatar, HlmAvatarFallback, HlmAvatarImage],
  template: `
    <hlm-avatar size="sm">
      @if (withImage()) {
        <img hlmAvatarImage src="/avatar.png" alt="" />
      }
      <span hlmAvatarFallback>DC</span>
    </hlm-avatar>
  `,
})
class AvatarHost {
  readonly withImage = signal(false);
}

describe('HlmAvatar', () => {
  let fixture: ComponentFixture<AvatarHost>;

  const fallback = (): HTMLElement => fixture.nativeElement.querySelector('[hlmAvatarFallback]');
  const image = (): HTMLImageElement => fixture.nativeElement.querySelector('img');

  beforeEach(() => {
    fixture = TestBed.createComponent(AvatarHost);
  });

  it('renders the fallback when there is no image, which is how the nav uses it', () => {
    fixture.detectChanges();

    expect(fallback().textContent).toBe('DC');
    expect(fallback().style.visibility).toBe('');
    expect(fixture.nativeElement.querySelector('hlm-avatar').getAttribute('data-size')).toBe('sm');
  });

  it('swaps the fallback for the image once the image has loaded', () => {
    fixture.componentInstance.withImage.set(true);
    fixture.detectChanges();
    expect(image().style.visibility).toBe('hidden');

    image().dispatchEvent(new Event('load'));
    fixture.detectChanges();

    expect(image().style.visibility).toBe('');
    expect(fallback().style.visibility).toBe('hidden');
  });

  it('keeps the fallback when the image fails to load', () => {
    fixture.componentInstance.withImage.set(true);
    fixture.detectChanges();

    image().dispatchEvent(new Event('error'));
    fixture.detectChanges();

    expect(fallback().style.visibility).toBe('');
    expect(image().style.visibility).toBe('hidden');
  });
});
