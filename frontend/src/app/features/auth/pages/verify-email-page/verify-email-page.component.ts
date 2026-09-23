import { Component, inject, OnInit, PLATFORM_ID } from '@angular/core';
import { isPlatformBrowser } from '@angular/common';
import { Router, ActivatedRoute } from '@angular/router';
import { NgIcon, provideIcons } from '@ng-icons/core';
import { lucideLoader2, lucideCircleCheck, lucideCircleX, lucideArrowLeft } from '@ng-icons/lucide';
import { HlmButton } from '@spartan-ng/helm/button';
import { AuthService } from '../../../../core/services/auth.service';

@Component({
  selector: 'app-verify-email-page',
  standalone: true,
  imports: [NgIcon, HlmButton],
  providers: [provideIcons({ lucideLoader2, lucideCircleCheck, lucideCircleX, lucideArrowLeft })],
  templateUrl: './verify-email-page.component.html',
  styleUrl: './verify-email-page.component.scss',
})
export class VerifyEmailPageComponent implements OnInit {
  isVerifying = true;
  verificationSuccess = false;
  verificationError: string | null = null;
  token: string | null = null;

  private authService = inject(AuthService);
  private router = inject(Router);
  private route = inject(ActivatedRoute);
  private platformId = inject(PLATFORM_ID);

  ngOnInit(): void {
    if (!isPlatformBrowser(this.platformId)) {
      return;
    }

    this.token = this.route.snapshot.queryParamMap.get('token');

    if (!this.token) {
      this.verificationError = 'Invalid verification link';
      this.isVerifying = false;
      return;
    }

    this.verifyEmail();
  }

  private verifyEmail() {
    if (!this.token) return;

    this.authService.verifyEmail(this.token).subscribe({
      next: () => {
        this.verificationSuccess = true;
        this.isVerifying = false;

        setTimeout(() => {
          this.authService.redirectAfterAuth(this.router);
        }, 2000);
      },
      error: error => {
        this.isVerifying = false;
        this.verificationError =
          error?.error?.message ||
          'Failed to verify email address. The link may be invalid or expired.';
      },
    });
  }

  goToLogin() {
    this.router.navigate(['/auth']);
  }
}
