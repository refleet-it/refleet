import { TestBed } from '@angular/core/testing';
import { NavigationEnd, Router } from '@angular/router';
import { Subject } from 'rxjs';
import { beforeEach, describe, expect, it } from 'vitest';
import { MenuItemInterface } from '../../../interfaces/menu-item.interface';
import { NavMainComponent } from './nav-main.component';

const settings: MenuItemInterface = {
  label: 'Settings',
  icon: 'lucideSettings',
  children: [{ label: 'Team', routerLink: 'settings/team' }],
} as MenuItemInterface;

describe('NavMainComponent', () => {
  let events: Subject<NavigationEnd>;
  let component: NavMainComponent;

  beforeEach(() => {
    events = new Subject<NavigationEnd>();

    TestBed.configureTestingModule({
      providers: [
        {
          provide: Router,
          useValue: { events: events.asObservable(), url: '/dashboard' },
        },
      ],
    });

    component = TestBed.runInInjectionContext(() => new NavMainComponent());
  });

  it('reports no active child for a leaf item', () => {
    expect(component.isChildActive({ label: 'Dashboard' } as MenuItemInterface)).toBe(false);
  });

  it('starts from the URL the router is already on', () => {
    TestBed.resetTestingModule();
    TestBed.configureTestingModule({
      providers: [
        {
          provide: Router,
          useValue: { events: new Subject<NavigationEnd>().asObservable(), url: '/settings/team' },
        },
      ],
    });

    const onSettings = TestBed.runInInjectionContext(() => new NavMainComponent());

    expect(onSettings.isChildActive(settings)).toBe(true);
  });

  // The component is OnPush and this value is read from a template binding. Reading router.url
  // directly would leave it stale unless something else marked the component dirty.
  it('follows navigation instead of freezing on the first URL', () => {
    expect(component.isChildActive(settings)).toBe(false);

    events.next(new NavigationEnd(1, '/settings/team', '/settings/team'));

    expect(component.isChildActive(settings)).toBe(true);
  });

  it('stops reporting the child as active once navigation leaves it', () => {
    events.next(new NavigationEnd(1, '/settings/team', '/settings/team'));
    expect(component.isChildActive(settings)).toBe(true);

    events.next(new NavigationEnd(2, '/dashboard', '/dashboard'));

    expect(component.isChildActive(settings)).toBe(false);
  });
});
