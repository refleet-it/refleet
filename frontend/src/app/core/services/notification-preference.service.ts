import { HttpClient } from '@angular/common/http';
import { inject, Injectable } from '@angular/core';
import { Observable } from 'rxjs';
import { environment } from '../../../environments/environment';
import {
  NotificationChannel,
  NotificationPreference,
} from '../models/notification-preference.model';

interface ListNotificationPreferencesResponse {
  preferences: NotificationPreference[];
}

@Injectable({
  providedIn: 'root',
})
export class NotificationPreferenceService {
  private readonly http = inject(HttpClient);

  list(): Observable<ListNotificationPreferencesResponse> {
    return this.http.get<ListNotificationPreferencesResponse>(
      `${environment.apiUrl}/notifications/preferences`
    );
  }

  update(notificationType: string, enabledChannels: NotificationChannel[]): Observable<void> {
    return this.http.put<void>(
      `${environment.apiUrl}/notifications/preferences/${notificationType}`,
      {
        enabledChannels,
      }
    );
  }
}
