import { Component, input, output } from '@angular/core';
import { HlmButton } from '@spartan-ng/helm/button';

/**
 * The "we could not load this" branch of a list. Without it an empty list reads the same
 * whether the organization has nothing in it or the request failed.
 */
@Component({
  selector: 'app-list-error',
  standalone: true,
  imports: [HlmButton],
  host: { role: 'alert' },
  template: `
    <div class="flex flex-col items-center gap-3 py-6 text-center">
      <p class="text-destructive text-sm">{{ message() }}</p>
      <button hlmBtn variant="outline" (click)="retry.emit()">Retry</button>
    </div>
  `,
})
export class ListErrorComponent {
  readonly message = input.required<string>();
  readonly retry = output<void>();
}
