import type { FetchFn } from '../backend-client.js';
import type { AgentUsage } from '../backends/types.js';
import type { Logger } from './log.js';

/**
 * What this runner advertises to the backend. The space-separated RUNNER_SUPPORTED_*
 * env vars the old shell loop read are gone; the fleet loop builds one of these per
 * detected engine directly. Empty/absent lists mean "no filter" for that dimension,
 * matching the backend's claim semantics (see ClaimRunnerJobController).
 */
export interface RunnerIdentity {
  name: string;
  supportedKinds?: string[];
  supportedModes?: string[];
  supportedEngines?: string[];
  supportedModels?: string[];
}

export interface ClaimedJobProject {
  path?: string;
  externalId?: string;
  defaultBranch?: string;
}

export interface ClaimedJobPayload {
  project?: ClaimedJobProject;
  engine?: string;
  prompt?: string;
  model?: string;
  [key: string]: unknown;
}

export interface ClaimedJob {
  jobId: string;
  ownerId?: string;
  ownerTargetId?: string;
  /** The owning shift's/qualification's title — what a customer sees as the MR title. */
  ownerLabel?: string;
  kind?: string;
  mode?: string;
  payload?: ClaimedJobPayload;
}

/**
 * One report carries everything the backend needs to settle the target — for a change
 * job that includes the merge request, so the shift never has to learn about it through
 * a second call that could race the first.
 */
export interface JobReport {
  outcome: 'success' | 'failure';
  summary: string;
  errorMessage?: string;
  /** kind=qualification: the agent's 1–5 score; the backend applies the cut-off. */
  score?: number;
  /** kind=change: where the change was pushed and the merge request opened for it. */
  branchName?: string;
  mergeRequestUrl?: string;
  mergeRequestIid?: string;
}

/**
 * What the backend answers a heartbeat with about this runner's release: the newest
 * published version it knows of, whether this runner is behind it, and whether someone
 * asked for an update from the dashboard (delivered once — the backend clears the
 * request as it answers).
 */
export interface HeartbeatResponse {
  latestVersion: string | null;
  updateAvailable: boolean;
  updateRequested: boolean;
}

export interface GitLabCredentials {
  baseUrl: string;
  accessToken: string;
}

export interface ApiResponse {
  /** 0 when the request never got an HTTP answer (DNS/connection/timeout). */
  status: number;
  body: string;
}

function withList(key: string, values: string[] | undefined): Record<string, string[]> {
  return values && values.length > 0 ? { [key]: values } : {};
}

/**
 * The runner-facing slice of the Refleet API. Nothing here throws on a network error:
 * a transient outage must skip one poll cycle, not kill the loop.
 */
export class FleetApi {
  private readonly baseUrl: string;

  constructor(
    apiUrl: string,
    private readonly apiKey: string,
    private readonly log: Logger,
    private readonly fetchFn: FetchFn = fetch,
  ) {
    this.baseUrl = apiUrl.replace(/\/+$/, '');
  }

  async request(method: string, path: string, body?: unknown): Promise<ApiResponse> {
    try {
      const response = await this.fetchFn(`${this.baseUrl}${path}`, {
        method,
        headers: {
          Authorization: `Bearer ${this.apiKey}`,
          'Content-Type': 'application/json',
        },
        body: undefined === body ? undefined : JSON.stringify(body),
      });
      return { status: response.status, body: await response.text() };
    } catch {
      return { status: 0, body: '' };
    }
  }

  /**
   * `usage` is what the last agent run reported (see AgentUsage); omitted until a job
   * has run. Returns null when the heartbeat did not get through — the release fields
   * are then simply unknown for this cycle.
   */
  async heartbeat(identity: RunnerIdentity, usage: AgentUsage | null = null, version: string | null = null): Promise<HeartbeatResponse | null> {
    const body = {
      name: identity.name,
      ...withList('supportedEngines', identity.supportedEngines),
      ...withList('supportedModels', identity.supportedModels),
      ...(usage ? { usage } : {}),
      ...(version ? { version } : {}),
    };
    const response = await this.request('POST', '/runner/heartbeat', body);
    if (!isSuccess(response.status)) {
      this.log(`heartbeat request failed (status ${String(response.status)})`);
      return null;
    }

    try {
      const parsed = JSON.parse(response.body) as Partial<HeartbeatResponse>;
      return {
        latestVersion: 'string' === typeof parsed.latestVersion ? parsed.latestVersion : null,
        updateAvailable: true === parsed.updateAvailable,
        updateRequested: true === parsed.updateRequested,
      };
    } catch {
      return null;
    }
  }

  /** Returns null when no job is available (204) or the claim failed for any reason. */
  async claimJob(identity: RunnerIdentity): Promise<ClaimedJob | null> {
    const body = {
      runnerId: identity.name,
      ...withList('supportedKinds', identity.supportedKinds),
      ...withList('supportedModes', identity.supportedModes),
      ...withList('supportedEngines', identity.supportedEngines),
    };
    const response = await this.request('POST', '/runner/jobs/claim', body);
    if (200 !== response.status) {
      return null;
    }

    try {
      const claimed = JSON.parse(response.body) as ClaimedJob;
      return claimed.jobId ? claimed : null;
    } catch {
      return null;
    }
  }

  async reportJob(identity: RunnerIdentity, jobId: string, report: JobReport): Promise<void> {
    const body = {
      runnerId: identity.name,
      outcome: report.outcome,
      summary: report.summary,
      ...(report.errorMessage ? { errorMessage: report.errorMessage } : {}),
      ...(undefined !== report.score ? { score: report.score } : {}),
      ...(report.branchName ? { branchName: report.branchName } : {}),
      ...(report.mergeRequestUrl ? { mergeRequestUrl: report.mergeRequestUrl } : {}),
      ...(report.mergeRequestIid ? { mergeRequestIid: report.mergeRequestIid } : {}),
    };
    const response = await this.request('POST', `/runner/jobs/${jobId}/report`, body);
    if (!isSuccess(response.status)) {
      this.log(`report failed for job ${jobId} (status ${String(response.status)})`);
    }
  }

  /**
   * Fetched right before every git/GitLab call rather than once per job or at startup:
   * OAuth-backed connections hand out short-lived access tokens, and the backend refreshes
   * one only when asked for it, so a token fetched before a long agent run may be dead by
   * the time the branch is pushed.
   */
  async fetchGitLabCredentials(): Promise<GitLabCredentials | null> {
    const response = await this.request('GET', '/runner/gitlab-credentials');
    if (200 !== response.status) {
      return null;
    }

    try {
      const body = JSON.parse(response.body) as Partial<GitLabCredentials>;
      if (!body.baseUrl || !body.accessToken) {
        return null;
      }
      return { baseUrl: body.baseUrl, accessToken: body.accessToken };
    } catch {
      return null;
    }
  }
}

function isSuccess(status: number): boolean {
  return status >= 200 && status < 300;
}
