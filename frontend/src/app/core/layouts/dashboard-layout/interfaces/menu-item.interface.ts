export interface MenuItemInterface {
  label: string;
  icon: string;
  routerLink: string | null;
  children?: MenuItemInterface[];
  expanded?: boolean;
  sectionHeader?: string;
  exact?: boolean;
}
