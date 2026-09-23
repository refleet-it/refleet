import { Component, signal } from '@angular/core';
import { ComponentFixture, TestBed } from '@angular/core/testing';
import { beforeEach, describe, expect, it } from 'vitest';
import { ProjectTreeItem } from './project-tree';
import { ProjectTreePickerComponent } from './project-tree-picker.component';

@Component({
  standalone: true,
  imports: [ProjectTreePickerComponent],
  template: `<app-project-tree-picker [items]="items()" [(selectedIds)]="selectedIds" />`,
})
class HostComponent {
  readonly items = signal<ProjectTreeItem[]>([
    { id: 'p1', name: 'api', path: 'acme/backend/api' },
    { id: 'p2', name: 'legacy-lib', path: 'acme/backend/legacy-lib' },
    { id: 'p3', name: 'www', path: 'acme/frontend/www' },
    { id: 'p4', name: 'solo', path: 'solo' },
  ]);
  readonly selectedIds = signal<string[]>([]);
}

describe('ProjectTreePickerComponent', () => {
  let fixture: ComponentFixture<HostComponent>;
  let host: HostComponent;

  const picker = (): ProjectTreePickerComponent =>
    fixture.debugElement.children[0].componentInstance as ProjectTreePickerComponent;
  const labels = (): string[] =>
    Array.from(fixture.nativeElement.querySelectorAll('cdk-tree-node label')).map(
      el => (el as HTMLElement).textContent!.trim().split(/\s+/)[0]
    );

  beforeEach(async () => {
    await TestBed.configureTestingModule({ imports: [HostComponent] }).compileComponents();
    fixture = TestBed.createComponent(HostComponent);
    host = fixture.componentInstance;
    fixture.detectChanges();
    await fixture.whenStable();
    fixture.detectChanges();
  });

  it('renders top-level groups expanded and deeper groups collapsed', () => {
    expect(labels()).toEqual(['acme', 'backend', 'frontend', 'solo']);
  });

  it('expands and collapses everything', () => {
    picker().expandAll();
    fixture.detectChanges();
    expect(labels()).toEqual(['acme', 'backend', 'api', 'legacy-lib', 'frontend', 'www', 'solo']);

    picker().collapseAll();
    fixture.detectChanges();
    expect(labels()).toEqual(['acme', 'solo']);
  });

  it('selecting a group selects every project beneath it', () => {
    const [acme] = picker()['visibleTree']();

    picker().toggleNode(acme);

    expect(host.selectedIds().sort()).toEqual(['p1', 'p2', 'p3']);
    expect(picker().isSelected(acme)).toBe(true);
  });

  it('a group with only some projects selected is partially selected, and toggling it selects the rest', () => {
    const [acme] = picker()['visibleTree']();
    host.selectedIds.set(['p1']);
    fixture.detectChanges();

    expect(picker().isSelected(acme)).toBe(false);
    expect(picker().isPartiallySelected(acme)).toBe(true);

    picker().toggleNode(acme);
    expect(host.selectedIds().sort()).toEqual(['p1', 'p2', 'p3']);

    picker().toggleNode(acme);
    expect(host.selectedIds()).toEqual([]);
  });

  it('select all toggles the whole list', () => {
    picker().toggleAll();
    expect(host.selectedIds().sort()).toEqual(['p1', 'p2', 'p3', 'p4']);

    picker().toggleAll();
    expect(host.selectedIds()).toEqual([]);
  });

  it('filtering prunes the tree and opens every remaining group', () => {
    picker()['query'].set('legacy');
    fixture.detectChanges();

    expect(labels()).toEqual(['acme', 'backend', 'legacy-lib']);
  });

  it('keeps the selection of projects hidden by the filter', () => {
    host.selectedIds.set(['p4']);
    picker()['query'].set('legacy');
    fixture.detectChanges();

    const [acme] = picker()['visibleTree']();
    picker().toggleNode(acme);

    expect(host.selectedIds().sort()).toEqual(['p2', 'p4']);
  });
});
