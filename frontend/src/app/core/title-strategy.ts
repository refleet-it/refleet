import { inject, Injectable } from '@angular/core';
import { Title } from '@angular/platform-browser';
import { RouterStateSnapshot, TitleStrategy } from '@angular/router';

import { environment } from '../../environments/environment';

/**
 * Appends the product name to a route's `title`. Routes that declare no title are left alone,
 * which is what lets the landing and documentation pages keep setting theirs from their content.
 */
@Injectable()
export class AppTitleStrategy extends TitleStrategy {
  private readonly title = inject(Title);

  override updateTitle(snapshot: RouterStateSnapshot): void {
    const routeTitle = this.buildTitle(snapshot);

    if (undefined !== routeTitle) {
      this.title.setTitle(`${routeTitle} — ${environment.appName}`);
    }
  }
}
