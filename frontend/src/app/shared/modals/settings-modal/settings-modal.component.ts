import { Component, inject, OnInit, signal } from '@angular/core';
import { CommonModule } from '@angular/common';
import {
  FormBuilder,
  FormGroup,
  ReactiveFormsModule,
  Validators,
  FormsModule,
} from '@angular/forms';
import { NgIcon, provideIcons } from '@ng-icons/core';
import { lucideBell, lucideCode, lucideShield, lucideUser } from '@ng-icons/lucide';
import { BrnDialogRef } from '@spartan-ng/brain/dialog';
import { HlmButton } from '@spartan-ng/helm/button';
import { HlmCheckboxImports } from '@spartan-ng/helm/checkbox';
import { HlmDialogImports } from '@spartan-ng/helm/dialog';
import { HlmFieldImports } from '@spartan-ng/helm/field';
import { HlmInput } from '@spartan-ng/helm/input';
import { catchError, of } from 'rxjs';
import { ApiKey, CreatedApiKey } from '../../../core/models/api-key.model';
import {
  NotificationChannel,
  NotificationPreference,
} from '../../../core/models/notification-preference.model';
import { ApiKeyService } from '../../../core/services/api-key.service';
import { AuthService } from '../../../core/services/auth.service';
import { NotificationPreferenceService } from '../../../core/services/notification-preference.service';
import { ToastService } from '../../services/toast.service';
import { PasswordInputComponent } from '../../components/password-input/password-input.component';

type SettingsSection = 'security' | 'profile' | 'notifications' | 'developer';

@Component({
  selector: 'app-settings-modal',
  standalone: true,
  imports: [
    CommonModule,
    ReactiveFormsModule,
    FormsModule,
    NgIcon,
    HlmButton,
    HlmCheckboxImports,
    HlmDialogImports,
    HlmFieldImports,
    HlmInput,
    PasswordInputComponent,
  ],
  providers: [provideIcons({ lucideShield, lucideUser, lucideBell, lucideCode })],
  templateUrl: './settings-modal.component.html',
  styleUrls: ['./settings-modal.component.scss'],
})
export class SettingsModalComponent implements OnInit {
  private readonly fb = inject(FormBuilder);
  private readonly ref = inject(BrnDialogRef);
  private readonly toastService = inject(ToastService);
  private readonly apiKeyService = inject(ApiKeyService);
  private readonly authService = inject(AuthService);
  private readonly notificationPreferenceService = inject(NotificationPreferenceService);

  passwordForm!: FormGroup;
  activeSection: SettingsSection = 'security';
  isSubmitting = false;

  sections = this.buildSections();

  protected readonly apiKeys = signal<ApiKey[]>([]);
  protected readonly isLoadingApiKeys = signal(false);
  protected readonly newKeyName = signal('');
  protected readonly isCreatingApiKey = signal(false);
  protected readonly createdApiKey = signal<CreatedApiKey | null>(null);
  protected readonly newKeyCopied = signal(false);
  protected readonly revokingApiKeyId = signal<string | null>(null);

  protected readonly notificationPreferences = signal<NotificationPreference[]>([]);
  protected readonly isLoadingNotificationPreferences = signal(false);
  protected readonly updatingNotificationType = signal<string | null>(null);

  ngOnInit(): void {
    this.initPasswordForm();
  }

  private buildSections(): { label: string; value: SettingsSection }[] {
    return [
      { label: 'Security', value: 'security' },
      { label: 'Profile', value: 'profile' },
      { label: 'Notifications', value: 'notifications' },
      { label: 'Developer', value: 'developer' },
    ];
  }

  private initPasswordForm(): void {
    this.passwordForm = this.fb.group(
      {
        currentPassword: ['', [Validators.required, Validators.minLength(8)]],
        newPassword: ['', [Validators.required, Validators.minLength(8)]],
        confirmPassword: ['', [Validators.required]],
      },
      {
        validators: this.passwordMatchValidator,
      }
    );
  }

  private passwordMatchValidator(group: FormGroup): Record<string, boolean> | null {
    const newPassword = group.get('newPassword')?.value;
    const confirmPassword = group.get('confirmPassword')?.value;

    if (newPassword && confirmPassword && newPassword !== confirmPassword) {
      return { passwordMismatch: true };
    }
    return null;
  }

  changeSection(section: SettingsSection): void {
    this.activeSection = section;

    if ('developer' === section) {
      this.loadApiKeys();
    }

    if ('notifications' === section) {
      this.loadNotificationPreferences();
    }
  }

  onSectionSelectChange(event: Event): void {
    const value = (event.target as HTMLSelectElement).value as SettingsSection;
    this.changeSection(value);
  }

  createApiKey(): void {
    const name = this.newKeyName().trim();
    if (!name) {
      return;
    }

    this.isCreatingApiKey.set(true);

    this.apiKeyService.create(name).subscribe({
      next: created => {
        this.isCreatingApiKey.set(false);
        this.newKeyName.set('');
        this.createdApiKey.set(created);
        this.loadApiKeys();
      },
      error: () => this.isCreatingApiKey.set(false),
    });
  }

  copyNewKeyToken(token: string): void {
    void navigator.clipboard.writeText(token);
    this.newKeyCopied.set(true);
    setTimeout(() => this.newKeyCopied.set(false), 2000);
  }

  dismissNewApiKey(): void {
    this.createdApiKey.set(null);
  }

  revokeApiKey(apiKey: ApiKey): void {
    this.revokingApiKeyId.set(apiKey.id);

    this.apiKeyService.revoke(apiKey.id).subscribe({
      next: () => {
        this.revokingApiKeyId.set(null);
        this.loadApiKeys();
      },
      error: () => this.revokingApiKeyId.set(null),
    });
  }

  private loadApiKeys(): void {
    this.isLoadingApiKeys.set(true);
    this.apiKeyService
      .list()
      .pipe(catchError(() => of({ apiKeys: [], count: 0 })))
      .subscribe(result => {
        this.apiKeys.set(result.apiKeys);
        this.isLoadingApiKeys.set(false);
      });
  }

  isChannelEnabled(preference: NotificationPreference, channel: NotificationChannel): boolean {
    return preference.enabledChannels.includes(channel);
  }

  toggleChannel(preference: NotificationPreference, channel: NotificationChannel): void {
    const enabledChannels = this.isChannelEnabled(preference, channel)
      ? preference.enabledChannels.filter(enabled => enabled !== channel)
      : [...preference.enabledChannels, channel];

    this.updatingNotificationType.set(preference.notificationType);

    this.notificationPreferenceService
      .update(preference.notificationType, enabledChannels)
      .subscribe({
        next: () => {
          this.notificationPreferences.update(preferences =>
            preferences.map(existing =>
              existing.notificationType === preference.notificationType
                ? { ...existing, enabledChannels }
                : existing
            )
          );
          this.updatingNotificationType.set(null);
        },
        error: () => this.updatingNotificationType.set(null),
      });
  }

  private loadNotificationPreferences(): void {
    this.isLoadingNotificationPreferences.set(true);
    this.notificationPreferenceService
      .list()
      .pipe(catchError(() => of({ preferences: [] })))
      .subscribe(result => {
        this.notificationPreferences.set(result.preferences);
        this.isLoadingNotificationPreferences.set(false);
      });
  }

  onSubmitPasswordChange(): void {
    if (this.passwordForm.invalid) {
      Object.keys(this.passwordForm.controls).forEach(key => {
        this.passwordForm.get(key)?.markAsTouched();
      });
      return;
    }

    this.isSubmitting = true;
    const { currentPassword, newPassword } = this.passwordForm.value;

    this.authService.changePassword(currentPassword, newPassword).subscribe({
      next: () => {
        this.toastService.add({
          severity: 'success',
          summary: 'Password changed',
        });
        this.isSubmitting = false;
        this.passwordForm.reset();
      },
      error: () => {
        this.isSubmitting = false;
      },
    });
  }

  getFieldError(fieldName: string): string {
    const control = this.passwordForm.get(fieldName);
    if (!control || !control.touched || !control.errors) {
      return '';
    }

    if (control.errors['required']) {
      return 'This field is required';
    }
    if (control.errors['minlength']) {
      return 'Password must be at least 8 characters';
    }
    return '';
  }

  getFormError(): string {
    if (
      this.passwordForm.errors?.['passwordMismatch'] &&
      this.passwordForm.get('confirmPassword')?.touched
    ) {
      return 'Passwords do not match';
    }
    return '';
  }

  close(): void {
    this.ref.close();
  }
}
