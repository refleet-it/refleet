import { describe, expect, it } from 'vitest';
import { buildProjectTree, filterProjectTree, ProjectTreeItem } from './project-tree';

const items: ProjectTreeItem[] = [
  { id: 'p1', name: 'Legacy Lib', path: 'acme/backend/legacy-lib' },
  { id: 'p2', name: 'api', path: 'acme/backend/api' },
  { id: 'p3', name: 'www', path: 'acme/frontend/www' },
  { id: 'p4', name: 'Standalone', path: 'standalone' },
  { id: 'p5', name: 'tools', path: 'acme/tools' },
];

describe('buildProjectTree', () => {
  it('nests projects under their namespace path, groups before projects, sorted by label', () => {
    const tree = buildProjectTree(items);

    expect(tree.map(n => n.label)).toEqual(['acme', 'Standalone']);

    const acme = tree[0];
    expect(acme.projectId).toBeUndefined();
    expect(acme.children.map(n => n.label)).toEqual(['backend', 'frontend', 'tools']);
    expect(acme.children[0].children.map(n => n.label)).toEqual(['api', 'Legacy Lib']);
  });

  it('collects every descendant project id on a group', () => {
    const [acme] = buildProjectTree(items);

    expect(acme.projectIds.sort()).toEqual(['p1', 'p2', 'p3', 'p5']);
    expect(acme.children[0].projectIds.sort()).toEqual(['p1', 'p2']);
  });

  it('keeps the project slug so a differing display name can show it', () => {
    const [acme] = buildProjectTree(items);
    const legacyLib = acme.children[0].children.find(n => 'p1' === n.projectId);

    expect(legacyLib?.slug).toBe('legacy-lib');
    expect(legacyLib?.key).toBe('p1');
  });

  it('keys groups by their namespace path', () => {
    const [acme] = buildProjectTree(items);

    expect(acme.key).toBe('acme');
    expect(acme.children[0].key).toBe('acme/backend');
  });
});

describe('filterProjectTree', () => {
  const tree = buildProjectTree(items);

  it('returns the same tree for a blank query', () => {
    expect(filterProjectTree(tree, '  ')).toBe(tree);
  });

  it('keeps only matching projects and the groups leading to them', () => {
    const filtered = filterProjectTree(tree, 'LEGACY');

    expect(filtered).toHaveLength(1);
    expect(filtered[0].label).toBe('acme');
    expect(filtered[0].projectIds).toEqual(['p1']);
    expect(filtered[0].children.map(n => n.label)).toEqual(['backend']);
    expect(filtered[0].children[0].children.map(n => n.projectId)).toEqual(['p1']);
  });

  it('matches on the path as well as the name', () => {
    const filtered = filterProjectTree(tree, 'acme/frontend');

    expect(filtered[0].children.map(n => n.label)).toEqual(['frontend']);
  });

  it('returns nothing when nothing matches', () => {
    expect(filterProjectTree(tree, 'nope')).toEqual([]);
  });
});
