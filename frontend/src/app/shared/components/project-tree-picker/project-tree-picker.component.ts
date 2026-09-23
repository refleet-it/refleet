import { CdkTree, CdkTreeModule } from '@angular/cdk/tree';
import {
  booleanAttribute,
  ChangeDetectionStrategy,
  Component,
  computed,
  effect,
  input,
  model,
  signal,
  untracked,
  viewChild,
} from '@angular/core';
import { FormsModule } from '@angular/forms';
import { NgIcon, provideIcons } from '@ng-icons/core';
import {
  lucideChevronRight,
  lucideFolder,
  lucideFolderGit2,
  lucideFolderOpen,
  lucideSearch,
} from '@ng-icons/lucide';
import { HlmButton } from '@spartan-ng/helm/button';
import { HlmCheckboxImports } from '@spartan-ng/helm/checkbox';
import { HlmInput } from '@spartan-ng/helm/input';
import { HlmLabel } from '@spartan-ng/helm/label';
import {
  buildProjectTree,
  filterProjectTree,
  ProjectTreeItem,
  ProjectTreeNode,
} from './project-tree';

/**
 * Multi-select of projects laid out as their GitLab namespace tree, so a whole group can be
 * expanded, collapsed or (un)selected at once. Selection is exposed as a flat list of project ids.
 */
@Component({
  selector: 'app-project-tree-picker',
  standalone: true,
  imports: [CdkTreeModule, FormsModule, NgIcon, HlmButton, HlmCheckboxImports, HlmInput, HlmLabel],
  providers: [
    provideIcons({
      lucideChevronRight,
      lucideFolder,
      lucideFolderGit2,
      lucideFolderOpen,
      lucideSearch,
    }),
  ],
  changeDetection: ChangeDetectionStrategy.OnPush,
  host: { class: 'block' },
  template: `
    <div
      class="rounded-lg border"
      [class.border-border]="!invalid()"
      [class.border-destructive]="invalid()"
    >
      <div class="border-border flex flex-wrap items-center gap-x-4 gap-y-2 border-b px-3 py-2">
        <div class="flex items-center gap-2">
          <hlm-checkbox
            [checked]="allSelected()"
            [indeterminate]="someSelected()"
            (checkedChange)="toggleAll()"
            [inputId]="idPrefix() + '-select-all'"
          />
          <label hlmLabel class="font-medium" [for]="idPrefix() + '-select-all'">Select all</label>
          <span class="text-muted-foreground text-xs"
            >{{ selectedCount() }} of {{ items().length }} selected</span
          >
        </div>

        <div class="ms-auto flex items-center gap-1">
          <button hlmBtn type="button" variant="ghost" size="sm" (click)="expandAll()">
            Expand all
          </button>
          <button hlmBtn type="button" variant="ghost" size="sm" (click)="collapseAll()">
            Collapse all
          </button>
        </div>

        <div class="relative w-full">
          <ng-icon
            name="lucideSearch"
            size="1rem"
            class="text-muted-foreground pointer-events-none absolute inset-y-0 start-3 my-auto"
          />
          <input
            hlmInput
            type="search"
            class="h-8 w-full ps-9"
            placeholder="Filter by name or path…"
            [attr.aria-label]="'Filter projects'"
            [ngModel]="query()"
            (ngModelChange)="query.set($event)"
            [name]="idPrefix() + '-filter'"
          />
        </div>
      </div>

      @if (0 === visibleTree().length) {
        <p class="text-muted-foreground px-3 py-4 text-center text-sm">
          No projects match "{{ query() }}".
        </p>
      } @else {
        <cdk-tree
          class="block max-h-72 overflow-y-auto py-1"
          [dataSource]="visibleTree()"
          [childrenAccessor]="childrenOf"
          [trackBy]="trackNode"
          [expansionKey]="keyOf"
        >
          <cdk-tree-node
            *cdkTreeNodeDef="let node"
            #treeNode="cdkTreeNode"
            class="hover:bg-muted/50 flex items-center gap-2 py-1 pe-3"
            [style.padding-inline-start.rem]="indentFor(treeNode.level, true)"
            [isExpandable]="false"
            [cdkTreeNodeTypeaheadLabel]="node.label"
          >
            <hlm-checkbox
              [checked]="isSelected(node)"
              (checkedChange)="toggleNode(node)"
              [inputId]="idPrefix() + '-' + node.projectId"
            />
            <ng-icon name="lucideFolderGit2" size="1rem" class="text-muted-foreground shrink-0" />
            <label hlmLabel class="min-w-0 truncate" [for]="idPrefix() + '-' + node.projectId">
              {{ node.label }}
              @if (node.slug && !sameName(node)) {
                <span class="text-muted-foreground font-normal">({{ node.slug }})</span>
              }
              @if (node.hint) {
                <span class="text-muted-foreground font-normal">— {{ node.hint }}</span>
              }
            </label>
          </cdk-tree-node>

          <cdk-tree-node
            *cdkTreeNodeDef="let node; when: isGroup"
            #treeNode="cdkTreeNode"
            class="hover:bg-muted/50 flex items-center gap-1 py-1 pe-3"
            [style.padding-inline-start.rem]="indentFor(treeNode.level, false)"
            [isExpandable]="true"
            [cdkTreeNodeTypeaheadLabel]="node.label"
          >
            <button
              type="button"
              cdkTreeNodeToggle
              class="text-muted-foreground hover:text-foreground flex size-6 shrink-0 items-center justify-center rounded"
              [attr.aria-label]="(treeNode.isExpanded ? 'Collapse ' : 'Expand ') + node.path"
            >
              <ng-icon
                name="lucideChevronRight"
                size="1rem"
                class="transition-transform"
                [class.rotate-90]="treeNode.isExpanded"
              />
            </button>
            <hlm-checkbox
              [checked]="isSelected(node)"
              [indeterminate]="isPartiallySelected(node)"
              (checkedChange)="toggleNode(node)"
              [inputId]="idPrefix() + '-group-' + node.path"
            />
            <ng-icon
              [name]="treeNode.isExpanded ? 'lucideFolderOpen' : 'lucideFolder'"
              size="1rem"
              class="text-muted-foreground ms-1 shrink-0"
            />
            <label
              hlmLabel
              class="min-w-0 truncate font-medium"
              [for]="idPrefix() + '-group-' + node.path"
            >
              {{ node.label }}
              <span class="text-muted-foreground text-xs font-normal">{{
                node.projectIds.length
              }}</span>
            </label>
          </cdk-tree-node>
        </cdk-tree>
      }
    </div>
  `,
})
export class ProjectTreePickerComponent {
  public readonly items = input.required<ProjectTreeItem[]>();
  public readonly selectedIds = model<string[]>([]);
  /** Namespaces DOM ids so two pickers on one page don't collide. */
  public readonly idPrefix = input('project');
  public readonly invalid = input(false, { transform: booleanAttribute });

  protected readonly query = signal('');

  private readonly tree = computed(() => buildProjectTree(this.items()));
  protected readonly visibleTree = computed(() => filterProjectTree(this.tree(), this.query()));
  private readonly selectedSet = computed(() => new Set(this.selectedIds()));

  protected readonly selectedCount = computed(() => {
    const selected = this.selectedSet();
    return this.items().filter(item => selected.has(item.id)).length;
  });
  protected readonly allSelected = computed(
    () => this.items().length > 0 && this.selectedCount() === this.items().length
  );
  protected readonly someSelected = computed(() => this.selectedCount() > 0 && !this.allSelected());

  private readonly cdkTree = viewChild(CdkTree<ProjectTreeNode, string>);

  protected readonly childrenOf = (node: ProjectTreeNode): ProjectTreeNode[] => node.children;
  protected readonly keyOf = (node: ProjectTreeNode): string => node.key;
  protected readonly trackNode = (_: number, node: ProjectTreeNode): string => node.key;
  protected readonly isGroup = (_: number, node: ProjectTreeNode): boolean => !node.projectId;

  constructor() {
    // A fresh tree starts with its top-level groups open; a filter opens everything it left in.
    effect(() => {
      const cdkTree = this.cdkTree();
      const tree = this.visibleTree();
      const filtering = '' !== this.query().trim();
      if (!cdkTree) {
        return;
      }
      untracked(() => {
        if (filtering) {
          cdkTree.expandAll();
        } else {
          tree.forEach(node => cdkTree.expand(node));
        }
      });
    });
  }

  isSelected(node: ProjectTreeNode): boolean {
    const selected = this.selectedSet();
    return node.projectIds.length > 0 && node.projectIds.every(id => selected.has(id));
  }

  isPartiallySelected(node: ProjectTreeNode): boolean {
    const selected = this.selectedSet();
    return !this.isSelected(node) && node.projectIds.some(id => selected.has(id));
  }

  toggleNode(node: ProjectTreeNode): void {
    const ids = node.projectIds;
    if (this.isSelected(node)) {
      this.selectedIds.update(current => current.filter(id => !ids.includes(id)));
    } else {
      this.selectedIds.update(current => [...current, ...ids.filter(id => !current.includes(id))]);
    }
  }

  toggleAll(): void {
    this.selectedIds.set(this.allSelected() ? [] : this.items().map(item => item.id));
  }

  expandAll(): void {
    this.cdkTree()?.expandAll();
  }

  collapseAll(): void {
    this.cdkTree()?.collapseAll();
  }

  /** Leaves have no chevron, so they get its width on top to line up with their group's label. */
  protected indentFor(level: number, leaf: boolean): number {
    return 0.5 + 1.75 * level + (leaf ? 1.75 : 0);
  }

  protected sameName(node: ProjectTreeNode): boolean {
    return node.slug?.toLowerCase() === node.label.toLowerCase();
  }
}
