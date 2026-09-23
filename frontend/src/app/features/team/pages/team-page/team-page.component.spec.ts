import { TestBed } from '@angular/core/testing';
import { of, Subject } from 'rxjs';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import { HlmDialogService } from '@spartan-ng/helm/dialog';
import { AuthService } from '../../../../core/services/auth.service';
import { OrganizationService } from '../../../../core/services/organization.service';
import { ToastService } from '../../../../shared/services/toast.service';
import { TeamPageComponent } from './team-page.component';

/**
 * Removing an employee is irreversible and used to fire straight at the API. These cases exist so
 * that a confirmation step cannot be dropped again without something going red.
 */
describe('TeamPageComponent removal', () => {
  let removeEmployee: ReturnType<typeof vi.fn>;
  let closed$: Subject<boolean>;
  let component: TeamPageComponent;

  beforeEach(() => {
    removeEmployee = vi.fn(() => of(void 0));
    closed$ = new Subject<boolean>();

    TestBed.configureTestingModule({
      providers: [
        {
          provide: OrganizationService,
          useValue: {
            removeEmployee,
            getMyOrganization: () => of({ organization: null }),
            listEmployees: () => of({ items: [], pagination: {} }),
            listInvitations: () => of({ items: [], pagination: {} }),
          },
        },
        { provide: HlmDialogService, useValue: { open: () => ({ closed$ }) } },
        { provide: AuthService, useValue: { getCurrentUser: () => ({ id: '1' }) } },
        { provide: ToastService, useValue: { add: vi.fn() } },
      ],
    });

    component = TestBed.runInInjectionContext(() => new TeamPageComponent());
  });

  it('asks before removing anyone', () => {
    component.removeEmployee('account-1', 'someone@refleet.it');

    expect(removeEmployee).not.toHaveBeenCalled();
  });

  it('removes once the dialog is confirmed', () => {
    component.removeEmployee('account-1', 'someone@refleet.it');
    closed$.next(true);

    expect(removeEmployee).toHaveBeenCalledWith('account-1');
  });

  it('leaves the employee alone when the dialog is dismissed', () => {
    component.removeEmployee('account-1', 'someone@refleet.it');
    closed$.next(false);

    expect(removeEmployee).not.toHaveBeenCalled();
  });
});
