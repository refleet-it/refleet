import { Component, inject } from '@angular/core';
import { Router } from '@angular/router';
import { HlmButton } from '@spartan-ng/helm/button';
import { NgIcon, provideIcons } from '@ng-icons/core';
import { lucideHouse, lucideArrowLeft, lucideRefreshCw } from '@ng-icons/lucide';

@Component({
  selector: 'app-server-error-page',
  standalone: true,
  imports: [HlmButton, NgIcon],
  providers: [provideIcons({ lucideHouse, lucideArrowLeft, lucideRefreshCw })],
  templateUrl: './server-error-page.component.html',
  styleUrls: ['./server-error-page.component.scss'],
})
export class ServerErrorPageComponent {
  private router = inject(Router);

  goHome(): void {
    this.router.navigate(['/']);
  }

  refreshPage(): void {
    window.location.reload();
  }

  goBack(): void {
    window.history.back();
  }
}
