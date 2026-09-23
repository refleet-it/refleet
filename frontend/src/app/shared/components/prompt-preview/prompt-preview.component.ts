import { ChangeDetectionStrategy, Component, input, signal } from '@angular/core';
import { Observable } from 'rxjs';
import { HlmButton } from '@spartan-ng/helm/button';

/**
 * "Show the full prompt" — fetches, on demand, the exact text the agent will receive
 * (framing, rules, change/criteria and answer contract) so nothing about what runs is
 * hidden behind the editable fields. Re-fetched on every open, since the fields change.
 */
@Component({
  selector: 'app-prompt-preview',
  standalone: true,
  imports: [HlmButton],
  changeDetection: ChangeDetectionStrategy.OnPush,
  host: { class: 'block' },
  template: `
    <div class="flex flex-col gap-2">
      <div class="flex items-center gap-2">
        <button
          hlmBtn
          type="button"
          variant="ghost"
          size="sm"
          (click)="toggle()"
          [disabled]="isLoading()"
        >
          @if (isLoading()) {
            Loading…
          } @else if (isOpen()) {
            Hide full prompt
          } @else {
            Show full prompt
          }
        </button>
        <span class="text-muted-foreground text-xs">{{ hint() }}</span>
      </div>
      @if (error()) {
        <p class="text-destructive text-sm">{{ error() }}</p>
      }
      @if (isOpen() && prompt(); as text) {
        <pre
          class="bg-muted max-h-[32rem] overflow-auto rounded-md p-3 text-xs leading-relaxed whitespace-pre-wrap"
          >{{ text }}</pre>
      }
    </div>
  `,
})
export class PromptPreviewComponent {
  /** Produces the full prompt for the fields as they are right now. */
  readonly load = input.required<() => Observable<string>>();
  readonly hint = input<string>('Exactly what the agent receives, rendered by the backend.');

  protected readonly isOpen = signal(false);
  protected readonly isLoading = signal(false);
  protected readonly error = signal<string | null>(null);
  protected readonly prompt = signal<string | null>(null);

  toggle(): void {
    if (this.isOpen()) {
      this.isOpen.set(false);
      this.prompt.set(null);
      return;
    }
    this.isLoading.set(true);
    this.error.set(null);
    this.load()().subscribe({
      next: prompt => {
        this.prompt.set(prompt);
        this.isOpen.set(true);
        this.isLoading.set(false);
      },
      error: () => {
        this.isLoading.set(false);
        this.error.set('Failed to render the prompt.');
      },
    });
  }
}
