import { Component, inject } from '@angular/core';
import { toSignal } from '@angular/core/rxjs-interop';
import { CommonModule } from '@angular/common';
import { RouterLink } from '@angular/router';
import { BrnDialogRef } from '@spartan-ng/brain/dialog';
import { HlmDialogImports } from '@spartan-ng/helm/dialog';
import { HlmAccordionImports } from '@spartan-ng/helm/accordion';
import { map } from 'rxjs';

import { InstanceConfigService } from '../../../core/services/instance-config.service';

@Component({
  selector: 'app-help-modal',
  standalone: true,
  imports: [CommonModule, HlmDialogImports, HlmAccordionImports, RouterLink],
  templateUrl: './help-modal.component.html',
  styleUrls: ['./help-modal.component.scss'],
})
export class HelpModalComponent {
  private readonly ref = inject(BrnDialogRef);

  protected readonly supportEmail = toSignal(
    inject(InstanceConfigService)
      .config()
      .pipe(map(config => config.supportEmail)),
    { initialValue: '' }
  );

  close(): void {
    this.ref.close();
  }
}
