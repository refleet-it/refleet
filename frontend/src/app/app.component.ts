import { Component, OnInit, HostListener } from '@angular/core';
import { RouterOutlet, Router, NavigationEnd } from '@angular/router';
import { filter, take } from 'rxjs';
import { HlmToaster } from '@spartan-ng/helm/sonner';
import { inject, PLATFORM_ID } from '@angular/core';
import { isPlatformBrowser } from '@angular/common';
import { ToastPositionService } from './core/services/toast-position.service';
import { CookieConsentComponent } from './shared/components/cookie-consent/cookie-consent.component';

@Component({
  selector: 'app-root',
  standalone: true,
  imports: [RouterOutlet, HlmToaster, CookieConsentComponent],
  templateUrl: './app.component.html',
  styleUrl: './app.component.scss',
})
export class AppComponent implements OnInit {
  private static readonly MEDIA_QUERY_REDUCED_MOTION = '(prefers-reduced-motion: reduce)';
  private static readonly CONFETTI_COLORS = ['#ffbc42', '#f59e0b', '#fbbf24', '#fcd34d'];
  private static readonly CONFETTI_COLORS_FULL = [
    '#ffbc42',
    '#f59e0b',
    '#fbbf24',
    '#fcd34d',
    '#fef3c7',
  ];
  private static readonly CONFETTI_DELAY_SIDE_MS = 200;
  private static readonly CONFETTI_DELAY_BURST_MS = 400;
  private static readonly CONFETTI_DELAY_LOOP_MS = 600;
  private static readonly CONFETTI_DURATION_MS = 5000;

  title = 'frontend';
  protected toastPosition = inject(ToastPositionService);
  private platformId = inject(PLATFORM_ID);
  private router = inject(Router);

  private konamiCode = [
    'ArrowUp',
    'ArrowUp',
    'ArrowDown',
    'ArrowDown',
    'ArrowLeft',
    'ArrowRight',
    'ArrowLeft',
    'ArrowRight',
    'b',
    'a',
  ];
  private konamiIndex = 0;

  ngOnInit(): void {
    if (isPlatformBrowser(this.platformId)) {
      this.router.events
        .pipe(
          filter(e => e instanceof NavigationEnd),
          take(1)
        )
        .subscribe(() => document.documentElement.classList.remove('auth-pending'));
    }
  }

  @HostListener('window:keydown', ['$event'])
  handleKeyboardEvent(event: KeyboardEvent): void {
    if (!event.key) return;

    const key = event.key.toLowerCase();

    if (
      key === this.konamiCode[this.konamiIndex] ||
      event.key === this.konamiCode[this.konamiIndex]
    ) {
      this.konamiIndex++;

      if (this.konamiIndex === this.konamiCode.length) {
        this.triggerKonamiConfetti();
        this.konamiIndex = 0;
      }
    } else {
      this.konamiIndex = 0;
    }
  }

  private async triggerKonamiConfetti(): Promise<void> {
    const prefersReducedMotion = window.matchMedia(AppComponent.MEDIA_QUERY_REDUCED_MOTION).matches;
    if (prefersReducedMotion) return;

    const { default: confetti } = await import('canvas-confetti');

    confetti({
      particleCount: 150,
      spread: 120,
      origin: { y: 0.6 },
      colors: AppComponent.CONFETTI_COLORS,
    });

    setTimeout(() => {
      confetti({
        particleCount: 100,
        angle: 60,
        spread: 70,
        origin: { x: 0, y: 0.6 },
        colors: AppComponent.CONFETTI_COLORS,
      });
      confetti({
        particleCount: 100,
        angle: 120,
        spread: 70,
        origin: { x: 1, y: 0.6 },
        colors: AppComponent.CONFETTI_COLORS,
      });
    }, AppComponent.CONFETTI_DELAY_SIDE_MS);

    setTimeout(() => {
      confetti({
        particleCount: 200,
        spread: 180,
        startVelocity: 50,
        origin: { y: 0.5 },
        colors: AppComponent.CONFETTI_COLORS_FULL,
      });
    }, AppComponent.CONFETTI_DELAY_BURST_MS);

    const end = Date.now() + AppComponent.CONFETTI_DURATION_MS;
    const frame = () => {
      confetti({
        particleCount: 3,
        angle: 90,
        spread: 360,
        origin: { x: Math.random(), y: 0 },
        colors: AppComponent.CONFETTI_COLORS_FULL,
        ticks: 200,
        gravity: 0.8,
      });

      if (Date.now() < end) {
        requestAnimationFrame(frame);
      }
    };
    setTimeout(() => frame(), AppComponent.CONFETTI_DELAY_LOOP_MS);
  }
}
