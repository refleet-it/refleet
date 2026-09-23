import { Component, signal } from '@angular/core';
import { ComponentFixture, TestBed } from '@angular/core/testing';
import { beforeEach, describe, expect, it } from 'vitest';
import { StatusBadgeComponent } from './status-badge.component';

@Component({
  standalone: true,
  imports: [StatusBadgeComponent],
  template: `
    <app-status-badge [variant]="variant()" [working]="working()">Running</app-status-badge>
  `,
})
class HostComponent {
  readonly variant = signal<'default' | 'outline'>('default');
  readonly working = signal(false);
}

/**
 * Every status in the app goes through this badge, so the shimmer that tells "an agent is on
 * it right now" apart from "nothing is happening" must follow the `working` flag alone.
 */
describe('StatusBadgeComponent', () => {
  let fixture: ComponentFixture<HostComponent>;
  let host: HostComponent;

  beforeEach(async () => {
    await TestBed.configureTestingModule({ imports: [HostComponent] }).compileComponents();

    fixture = TestBed.createComponent(HostComponent);
    host = fixture.componentInstance;
    fixture.detectChanges();
  });

  function badge(): HTMLElement {
    return fixture.nativeElement.querySelector('app-status-badge');
  }

  function label(): HTMLElement {
    return badge().querySelector('span') as HTMLElement;
  }

  it('is a badge of the requested variant', () => {
    expect(badge().getAttribute('data-slot')).toBe('badge');
    expect(badge().getAttribute('data-variant')).toBe('default');

    host.variant.set('outline');
    fixture.detectChanges();

    expect(badge().getAttribute('data-variant')).toBe('outline');
  });

  it('shows the projected label without a shimmer by default', () => {
    expect(badge().textContent?.trim()).toBe('Running');
    expect(label().classList.contains('shimmer')).toBe(false);
  });

  it('shimmers the label only while working', () => {
    host.working.set(true);
    fixture.detectChanges();

    expect(label().classList.contains('shimmer')).toBe(true);

    host.working.set(false);
    fixture.detectChanges();

    expect(label().classList.contains('shimmer')).toBe(false);
  });
});
