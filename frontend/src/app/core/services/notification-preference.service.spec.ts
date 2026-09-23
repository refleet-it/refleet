import { HttpTestingController, provideHttpClientTesting } from '@angular/common/http/testing';
import { provideHttpClient } from '@angular/common/http';
import { TestBed } from '@angular/core/testing';
import { afterEach, beforeEach, describe, expect, it } from 'vitest';
import { environment } from '../../../environments/environment';
import { NotificationPreferenceService } from './notification-preference.service';

describe('NotificationPreferenceService', () => {
  let service: NotificationPreferenceService;
  let httpMock: HttpTestingController;

  beforeEach(() => {
    TestBed.configureTestingModule({
      providers: [provideHttpClient(), provideHttpClientTesting()],
    });

    service = TestBed.inject(NotificationPreferenceService);
    httpMock = TestBed.inject(HttpTestingController);
  });

  afterEach(() => {
    httpMock.verify();
  });

  it('lists notification preferences from the expected endpoint', () => {
    service.list().subscribe(result => {
      expect(result.preferences).toHaveLength(1);
      expect(result.preferences[0].notificationType).toBe('shift_finished');
    });

    const req = httpMock.expectOne(`${environment.apiUrl}/notifications/preferences`);
    expect(req.request.method).toBe('GET');
    req.flush({
      preferences: [
        {
          notificationType: 'shift_finished',
          label: 'Shift Finished',
          enabledChannels: ['email', 'in_app'],
        },
      ],
    });
  });

  it('updates a preference with the given channels', () => {
    service.update('shift_finished', ['email']).subscribe();

    const req = httpMock.expectOne(
      `${environment.apiUrl}/notifications/preferences/shift_finished`
    );
    expect(req.request.method).toBe('PUT');
    expect(req.request.body).toEqual({ enabledChannels: ['email'] });
    req.flush(null);
  });
});
