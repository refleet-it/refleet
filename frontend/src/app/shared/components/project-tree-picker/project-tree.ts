/** The minimum a picker needs to know about a project: GitLab's `group/subgroup/project` path drives the tree. */
export interface ProjectTreeItem {
  id: string;
  name: string;
  path: string;
  /** Free-form suffix rendered after the name, e.g. a qualification target's status. */
  hint?: string;
}

export interface ProjectTreeNode {
  /** Namespace path for groups, project id for leaves — stable across rebuilds so expansion survives filtering. */
  key: string;
  label: string;
  path: string;
  children: ProjectTreeNode[];
  projectId?: string;
  /** The project's own path segment, shown when GitLab's display name differs from it. */
  slug?: string;
  hint?: string;
  /** Every project id under this node, so group checkboxes never walk the subtree. */
  projectIds: string[];
}

const collator = new Intl.Collator(undefined, { sensitivity: 'base', numeric: true });

export function buildProjectTree(items: ProjectTreeItem[]): ProjectTreeNode[] {
  const root: ProjectTreeNode = { key: '', label: '', path: '', children: [], projectIds: [] };

  for (const item of items) {
    const segments = item.path.split('/').filter(Boolean);
    const slug = segments.pop() ?? item.path;
    let parent = root;
    let path = '';

    for (const segment of segments) {
      path = path ? `${path}/${segment}` : segment;
      let group = parent.children.find(child => !child.projectId && child.path === path);
      if (!group) {
        group = { key: path, label: segment, path, children: [], projectIds: [] };
        parent.children.push(group);
      }
      group.projectIds.push(item.id);
      parent = group;
    }

    parent.children.push({
      key: item.id,
      label: item.name,
      path: item.path,
      children: [],
      projectId: item.id,
      slug,
      hint: item.hint,
      projectIds: [item.id],
    });
  }

  sortTree(root.children);
  return root.children;
}

/** Keeps only the projects whose name or path contains the query, and the groups leading to them. */
export function filterProjectTree(nodes: ProjectTreeNode[], query: string): ProjectTreeNode[] {
  const needle = query.trim().toLowerCase();
  if (!needle) {
    return nodes;
  }

  const filtered: ProjectTreeNode[] = [];
  for (const node of nodes) {
    if (node.projectId) {
      if (node.label.toLowerCase().includes(needle) || node.path.toLowerCase().includes(needle)) {
        filtered.push(node);
      }
      continue;
    }

    const children = filterProjectTree(node.children, needle);
    if (children.length > 0) {
      filtered.push({
        ...node,
        children,
        projectIds: children.flatMap(child => child.projectIds),
      });
    }
  }
  return filtered;
}

function sortTree(nodes: ProjectTreeNode[]): void {
  nodes.sort((a, b) => {
    const aIsGroup = !a.projectId;
    const bIsGroup = !b.projectId;
    if (aIsGroup !== bIsGroup) {
      return aIsGroup ? -1 : 1;
    }
    return collator.compare(a.label, b.label);
  });
  for (const node of nodes) {
    sortTree(node.children);
  }
}
