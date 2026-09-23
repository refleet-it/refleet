import { Component, inject } from '@angular/core';
import { Router } from '@angular/router';
import { HlmButton } from '@spartan-ng/helm/button';
import { NgIcon, provideIcons } from '@ng-icons/core';
import { lucideHouse, lucideArrowLeft } from '@ng-icons/lucide';

@Component({
  selector: 'app-not-found-page',
  standalone: true,
  imports: [HlmButton, NgIcon],
  providers: [provideIcons({ lucideHouse, lucideArrowLeft })],
  templateUrl: './not-found-page.component.html',
  styleUrls: ['./not-found-page.component.scss'],
})
export class NotFoundPageComponent {
  private router = inject(Router);

  goHome(): void {
    this.router.navigate(['/']);
  }

  goBack(): void {
    window.history.back();
  }
}
