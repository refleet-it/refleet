import { MenuItemInterface } from '../interfaces/menu-item.interface';

export const dashboardMenu: MenuItemInterface[] = [
  // exact, because nav-main defaults routerLinkActive to prefix matching: without it this item
  // matches every /dashboard/* path and stays highlighted alongside the page you are actually on.
  { label: 'Dashboard', icon: 'lucideLayoutDashboard', routerLink: '/dashboard', exact: true },
  { label: 'Qualifications', icon: 'lucideSearchCheck', routerLink: 'qualifications' },
  { label: 'Shifts', icon: 'lucideListChecks', routerLink: 'shifts' },
  { label: 'Playbooks', icon: 'lucideBookOpen', routerLink: 'playbooks' },
  { label: 'Runners', icon: 'lucideServer', routerLink: 'runners' },
  { label: 'Projects', icon: 'lucideFolderGit2', routerLink: 'projects' },
  { label: 'Team', icon: 'lucideUsers', routerLink: 'team' },
  { label: 'Settings', icon: 'lucideSettings', routerLink: 'settings' },
];

export const adminMenu: MenuItemInterface[] = [
  { label: 'Admin panel', icon: 'lucideLayoutDashboard', routerLink: '' },
];
