export type NotificationChannel = 'email' | 'in_app' | 'push';

export interface NotificationPreference {
  notificationType: string;
  label: string;
  enabledChannels: NotificationChannel[];
}
