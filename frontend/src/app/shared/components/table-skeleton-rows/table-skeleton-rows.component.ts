import { Component, computed, input } from '@angular/core';
import { HlmSkeletonImports } from '@spartan-ng/helm/skeleton';

export interface TableSkeletonColumn {
  /** Tailwind width utility for this column's skeleton bar, e.g. 'w-32'. */
  width: string;
}

const ROW_ANIMATION_STAGGER_MS = 75;

/**
 * Skeleton `<tr>` rows sized to match a real table's columns. Drop it straight into a
 * `<tbody>` while the row data is loading, so the table keeps its header and shape
 * instead of collapsing to a "Loading…" line.
 */
@Component({
  selector: 'app-table-skeleton-rows',
  standalone: true,
  imports: [HlmSkeletonImports],
  host: { style: 'display: contents' },
  template: `
    @for (row of rowIndexes(); track row) {
      <tr class="border-border border-b last:border-0">
        @for (column of columns(); track $index) {
          <td class="py-2 pr-4">
            <div [class]="column.width">
              <span
                hlmSkeleton
                class="block h-4 w-full"
                [style.animation-delay.ms]="row * stagger"
              ></span>
            </div>
          </td>
        }
      </tr>
    }
  `,
})
export class TableSkeletonRowsComponent {
  readonly rows = input(5);
  readonly columns = input.required<TableSkeletonColumn[]>();

  protected readonly stagger = ROW_ANIMATION_STAGGER_MS;
  protected readonly rowIndexes = computed(() =>
    Array.from({ length: this.rows() }, (_, index) => index)
  );
}
