import { Component, inject } from '@angular/core';
import { Router } from '@angular/router';
import { NgIcon, provideIcons } from '@ng-icons/core';
import { lucideMail, lucideLogIn } from '@ng-icons/lucide';
import { HlmButton } from '@spartan-ng/helm/button';

@Component({
  selector: 'app-registration-pending-page',
  standalone: true,
  imports: [NgIcon, HlmButton],
  providers: [provideIcons({ lucideMail, lucideLogIn })],
  templateUrl: './registration-pending-page.component.html',
  styleUrl: './registration-pending-page.component.scss',
})
export class RegistrationPendingPageComponent {
  private router = inject(Router);

  goToLogin(): void {
    this.router.navigate(['/auth']);
  }
}
