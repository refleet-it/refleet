import { Component, computed, inject, OnInit, signal } from '@angular/core';
import { FormsModule } from '@angular/forms';
import { RouterLink } from '@angular/router';
import { HlmBadgeImports } from '@spartan-ng/helm/badge';
import { HlmButton } from '@spartan-ng/helm/button';
import { HlmCardImports } from '@spartan-ng/helm/card';
import { HlmInput } from '@spartan-ng/helm/input';
import { HlmDialogService } from '@spartan-ng/helm/dialog';
import {
  ConfirmDialogComponent,
  ConfirmDialogContext,
} from '../../../../shared/modals/confirm-dialog/confirm-dialog.component';
import { ListErrorComponent } from '../../../../shared/components/list-error/list-error.component';
import {
  Playbook,
  PLAYBOOK_APPLIES_TO_LABELS,
  PLAYBOOK_KIND_LABELS,
  PlaybookKind,
} from '../../../../core/models/playbook.model';
import { PlaybookService } from '../../../../core/services/playbook.service';

type KindFilter = 'all' | PlaybookKind;

@Component({
  selector: 'app-playbooks-page',
  standalone: true,
  imports: [
    FormsModule,
    RouterLink,
    HlmBadgeImports,
    HlmButton,
    HlmCardImports,
    HlmInput,
    ListErrorComponent,
  ],
  templateUrl: './playbooks-page.component.html',
})
export class PlaybooksPageComponent implements OnInit {
  private readonly playbookService = inject(PlaybookService);
  private readonly dialogService = inject(HlmDialogService);

  protected readonly kindLabels = PLAYBOOK_KIND_LABELS;
  protected readonly appliesToLabels = PLAYBOOK_APPLIES_TO_LABELS;

  protected readonly playbooks = signal<Playbook[]>([]);
  protected readonly isLoading = signal(true);
  protected readonly loadError = signal<string | null>(null);
  protected readonly actionError = signal<string | null>(null);
  protected readonly deletingId = signal<string | null>(null);

  protected readonly searchQuery = signal('');
  protected readonly kindFilter = signal<KindFilter>('all');

  protected readonly filtered = computed(() => {
    const query = this.searchQuery().trim().toLowerCase();
    const kind = this.kindFilter();
    return this.playbooks().filter(
      playbook =>
        ('all' === kind || playbook.kind === kind) &&
        ('' === query ||
          playbook.name.toLowerCase().includes(query) ||
          (playbook.description ?? '').toLowerCase().includes(query))
    );
  });

  protected readonly hasActiveFilters = computed(
    () => '' !== this.searchQuery().trim() || 'all' !== this.kindFilter()
  );

  ngOnInit(): void {
    this.load();
  }

  load(): void {
    this.isLoading.set(true);
    this.loadError.set(null);
    this.playbookService.list().subscribe({
      next: playbooks => {
        this.playbooks.set(playbooks);
        this.isLoading.set(false);
      },
      error: () => {
        this.isLoading.set(false);
        this.loadError.set('Could not load playbooks.');
      },
    });
  }

  confirmDelete(playbook: Playbook): void {
    this.dialogService
      .open<boolean, ConfirmDialogContext>(ConfirmDialogComponent, {
        context: {
          title: `Delete "${playbook.name}"?`,
          description:
            'Shifts and qualifications keep the text they composed from it; only the playbook itself goes away.',
          confirmLabel: 'Delete',
          destructive: true,
        },
      })
      .closed$.subscribe(confirmed => {
        if (confirmed) {
          this.delete(playbook);
        }
      });
  }

  private delete(playbook: Playbook): void {
    this.deletingId.set(playbook.id);
    this.actionError.set(null);
    this.playbookService.delete(playbook.id).subscribe({
      next: () => {
        this.deletingId.set(null);
        this.playbooks.update(items => items.filter(item => item.id !== playbook.id));
      },
      error: () => {
        this.deletingId.set(null);
        this.actionError.set(`Failed to delete "${playbook.name}".`);
      },
    });
  }
}
