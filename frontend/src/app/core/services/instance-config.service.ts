import { HttpClient } from '@angular/common/http';
import { inject, Injectable } from '@angular/core';
import { Observable, catchError, map, of, shareReplay } from 'rxjs';
import { environment } from '../../../environments/environment';

export interface InstanceConfig {
  /** Whether visitors may create their own account. Self-hosted instances default to no. */
  allowSignup: boolean;
  /** The address this instance publishes, or '' when it publishes none. */
  supportEmail: string;
  /** Where this instance's terms and privacy policy live, or '' when it has none. */
  termsUrl: string;
}

const CLOSED: InstanceConfig = { allowSignup: false, supportEmail: '', termsUrl: '' };

/**
 * Settings that belong to the deployment rather than the build. One browser bundle serves
 * the hosted service and every self-hosted instance, so none of this can come from
 * `environment` — a support address compiled in would have every self-hosted instance
 * pointing its users at somebody else.
 *
 * Fetched once and replayed, since the answer cannot change under a running page. An
 * unreachable API is treated as the closed, says-nothing case: offering what cannot work is
 * worse than hiding what could.
 */
@Injectable({
  providedIn: 'root',
})
export class InstanceConfigService {
  private readonly http = inject(HttpClient);

  private readonly config$: Observable<InstanceConfig> = this.http
    .get<InstanceConfig>(`${environment.apiUrl}/instance`)
    .pipe(
      map(response => ({ ...CLOSED, ...response })),
      catchError(() => of(CLOSED)),
      shareReplay({ bufferSize: 1, refCount: false })
    );

  config(): Observable<InstanceConfig> {
    return this.config$;
  }

  allowsSignup(): Observable<boolean> {
    return this.config$.pipe(map(config => config.allowSignup));
  }
}
