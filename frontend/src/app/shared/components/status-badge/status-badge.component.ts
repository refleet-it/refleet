import { Component, input } from '@angular/core';
import { HlmBadge } from '@spartan-ng/helm/badge';

/**
 * The one badge every status in the app is rendered with, so a qualification, a shift target
 * and a runner job all read the same way. `working` marks statuses where an agent is busy on
 * the thing right now: the label shimmers, so a live page is told apart from a stuck one.
 */
@Component({
  selector: 'app-status-badge',
  standalone: true,
  hostDirectives: [{ directive: HlmBadge, inputs: ['variant'] }],
  template: `
    <span
      [class]="
        working()
          ? 'text-primary-foreground/50 shimmer shimmer-color-white/95 shimmer-spread-4 shimmer-duration-1400'
          : ''
      "
      ><ng-content
    /></span>
  `,
})
export class StatusBadgeComponent {
  readonly working = input(false);
}
