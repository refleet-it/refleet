import { Component, inject } from '@angular/core';
import { BrnDialogRef, injectBrnDialogContext } from '@spartan-ng/brain/dialog';
import { HlmButton } from '@spartan-ng/helm/button';

export interface ResultDetailDialogContext {
  title: string;
  subtitle?: string;
  body: string;
}

/**
 * Shows the untruncated qualification/change result for a single order target — the
 * table cell it's opened from truncates to one line, which cuts off multi-paragraph AI
 * summaries (including any clarifying question the agent asked back).
 */
@Component({
  selector: 'app-result-detail-modal',
  standalone: true,
  imports: [HlmButton],
  templateUrl: './result-detail-modal.component.html',
})
export class ResultDetailModalComponent {
  private readonly ref = inject(BrnDialogRef);

  protected readonly context = injectBrnDialogContext<ResultDetailDialogContext>();

  close(): void {
    this.ref.close();
  }
}
