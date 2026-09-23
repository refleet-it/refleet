import { Component, inject } from '@angular/core';
import { BrnDialogRef, injectBrnDialogContext } from '@spartan-ng/brain/dialog';
import { HlmButton } from '@spartan-ng/helm/button';

export interface ConfirmDialogContext {
  title: string;
  description: string;
  confirmLabel: string;
  cancelLabel?: string;
  destructive?: boolean;
}

/**
 * Generic yes/no confirmation modal, used for destructive project actions
 * (archive/delete) that need explicit confirmation before firing.
 */
@Component({
  selector: 'app-confirm-dialog',
  standalone: true,
  imports: [HlmButton],
  templateUrl: './confirm-dialog.component.html',
})
export class ConfirmDialogComponent {
  private readonly ref = inject<BrnDialogRef<boolean>>(BrnDialogRef);

  protected readonly context = injectBrnDialogContext<ConfirmDialogContext>();

  confirm(): void {
    this.ref.close(true);
  }

  cancel(): void {
    this.ref.close(false);
  }
}
