import { Component, OnInit, signal, inject, PLATFORM_ID } from '@angular/core';
import { isPlatformBrowser } from '@angular/common';
import { RouterModule } from '@angular/router';
import { HlmButton } from '@spartan-ng/helm/button';
import { HlmCard } from '@spartan-ng/helm/card';

@Component({
  selector: 'app-cookie-consent',
  standalone: true,
  imports: [RouterModule, HlmButton, HlmCard],
  templateUrl: './cookie-consent.component.html',
})
export class CookieConsentComponent implements OnInit {
  private static readonly STORAGE_KEY = 'app_cookie_consent';

  visible = signal(false);
  private platformId = inject(PLATFORM_ID);

  ngOnInit(): void {
    if (
      isPlatformBrowser(this.platformId) &&
      !localStorage.getItem(CookieConsentComponent.STORAGE_KEY)
    ) {
      this.visible.set(true);
    }
  }

  accept(): void {
    if (isPlatformBrowser(this.platformId)) {
      localStorage.setItem(CookieConsentComponent.STORAGE_KEY, 'accepted');
    }
    this.visible.set(false);
  }
}
