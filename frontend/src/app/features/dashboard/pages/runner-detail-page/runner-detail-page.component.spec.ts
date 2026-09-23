import { TestBed } from '@angular/core/testing';
import { ActivatedRoute } from '@angular/router';
import { of, Subject } from 'rxjs';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import { HlmDialogService } from '@spartan-ng/helm/dialog';
import { RunnerService } from '../../../../core/services/runner.service';
import { RunnerDetailPageComponent } from './runner-detail-page.component';

/**
 * Archiving revokes the runner's API key for good. It used to be confirmed by an inline panel of
 * its own; these cases keep the confirmation step in place whatever the mechanism looks like.
 */
describe('RunnerDetailPageComponent archiving', () => {
  let archive: ReturnType<typeof vi.fn>;
  let requestUpdate: ReturnType<typeof vi.fn>;
  let closed$: Subject<boolean>;
  let component: RunnerDetailPageComponent;

  beforeEach(() => {
    archive = vi.fn(() => of({ id: 'runner-1', status: 'archived' }));
    requestUpdate = vi.fn(() => of({ id: 'runner-1', status: 'idle', updateRequestedAt: 'now' }));
    closed$ = new Subject<boolean>();

    TestBed.configureTestingModule({
      providers: [
        {
          provide: RunnerService,
          useValue: {
            archive,
            requestUpdate,
            get: () => of({ id: 'runner-1', status: 'active' }),
            listJobs: () => of({ items: [], pagination: {} }),
          },
        },
        { provide: HlmDialogService, useValue: { open: () => ({ closed$ }) } },
        {
          provide: ActivatedRoute,
          useValue: { snapshot: { paramMap: { get: () => 'runner-1' } } },
        },
      ],
    });

    component = TestBed.runInInjectionContext(() => new RunnerDetailPageComponent());
    component.ngOnInit();
  });

  it('asks before archiving', () => {
    component.archiveRunner();

    expect(archive).not.toHaveBeenCalled();
  });

  it('archives once the dialog is confirmed', () => {
    component.archiveRunner();
    closed$.next(true);

    expect(archive).toHaveBeenCalledWith('runner-1');
  });

  it('leaves the runner alone when the dialog is dismissed', () => {
    component.archiveRunner();
    closed$.next(false);

    expect(archive).not.toHaveBeenCalled();
  });

  it('requests an update straight away — nothing irreversible happens, the runner just restarts', () => {
    component.requestUpdate();

    expect(requestUpdate).toHaveBeenCalledWith('runner-1');
    expect(component['runner']()?.updateRequestedAt).toBe('now');
  });
});
