/**
 * The contract between the prompt the backend builds and this runner: an agent ends its
 * response with one JSON object (see QualificationJobPayloadFactory and
 * ShiftJobPayloadFactory on the backend). Models still wrap it in a ```json fence,
 * restate it, or add a closing sentence after it now and then, so the parser takes the
 * last object that parses rather than insisting the whole output be JSON.
 */

export const QUALIFICATION_SCORE_MIN = 1;
export const QUALIFICATION_SCORE_MAX = 5;

export interface QualificationResult {
  /** 1 (clearly does not match) … 5 (clearly matches); the backend decides the cut-off. */
  score: number;
  reasoning: string;
}

/** Git's conventional subject-line limit; anything longer is treated as not-a-subject. */
export const COMMIT_SUBJECT_MAX_CHARS = 72;

export interface ChangeResult {
  summary: string;
  /**
   * The agent's proposed wording for the commit and merge request the runner creates.
   * Optional on the wire: an older prompt, or a model that ignored the contract, yields
   * a result without them and the runner falls back to the shift title.
   */
  commit?: ProposedCommit;
  mergeRequest?: ProposedMergeRequest;
}

export interface ProposedCommit {
  subject: string;
  body: string;
}

export interface ProposedMergeRequest {
  title: string;
  description: string;
}

type JsonObject = Record<string, unknown>;

function parseObject(candidate: string): JsonObject | null {
  try {
    const parsed: unknown = JSON.parse(candidate);
    return parsed && 'object' === typeof parsed && !Array.isArray(parsed) ? (parsed as JsonObject) : null;
  } catch {
    return null;
  }
}

/**
 * Returns the last JSON object in the text. Fenced blocks win over bare braces so an
 * object quoted inside the reasoning cannot shadow the real answer at the end.
 */
export function extractLastJsonObject(output: string): JsonObject | null {
  const fenced = [...output.matchAll(/```(?:json)?\s*([\s\S]*?)```/gi)];
  for (let i = fenced.length - 1; i >= 0; i--) {
    const parsed = parseObject((fenced[i]?.[1] ?? '').trim());
    if (parsed) {
      return parsed;
    }
  }

  const end = output.lastIndexOf('}');
  if (-1 === end) {
    return null;
  }
  for (let start = output.lastIndexOf('{', end); start >= 0; start = output.lastIndexOf('{', start - 1)) {
    const parsed = parseObject(output.slice(start, end + 1));
    if (parsed) {
      return parsed;
    }
  }

  return null;
}

function asInteger(value: unknown): number | null {
  if ('number' === typeof value && Number.isInteger(value)) {
    return value;
  }
  if ('string' === typeof value && /^\s*\d+\s*$/.test(value)) {
    return Number.parseInt(value, 10);
  }
  return null;
}

function asText(value: unknown): string {
  return 'string' === typeof value ? value.trim() : '';
}

/** Null when the output carries no object with an integer score in range — a failed run, not a "no". */
export function parseQualificationResult(output: string): QualificationResult | null {
  const object = extractLastJsonObject(output);
  if (!object) {
    return null;
  }
  const score = asInteger(object['score']);
  if (null === score || score < QUALIFICATION_SCORE_MIN || score > QUALIFICATION_SCORE_MAX) {
    return null;
  }
  return { score, reasoning: asText(object['reasoning']) };
}

function asObject(value: unknown): JsonObject | null {
  return value && 'object' === typeof value && !Array.isArray(value) ? (value as JsonObject) : null;
}

/** A usable subject is one non-blank line within git's conventional limit; anything else is dropped. */
function asSubjectLine(value: unknown): string {
  const text = asText(value);
  return '' === text || text.includes('\n') || text.length > COMMIT_SUBJECT_MAX_CHARS ? '' : text;
}

function parseProposedCommit(value: unknown): ProposedCommit | undefined {
  const subject = asSubjectLine(asObject(value)?.['subject']);
  return '' === subject ? undefined : { subject, body: asText(asObject(value)?.['body']) };
}

function parseProposedMergeRequest(value: unknown): ProposedMergeRequest | undefined {
  const object = asObject(value);
  const title = asText(object?.['title']);
  return '' === title || title.includes('\n') ? undefined : { title, description: asText(object?.['description']) };
}

/**
 * Null when the output carries no object with a non-empty summary. The commit and merge
 * request proposals are best-effort: a malformed one is left out rather than failing the
 * run, since the runner has the shift title to fall back on.
 */
export function parseChangeResult(output: string): ChangeResult | null {
  const object = extractLastJsonObject(output);
  const summary = asText(object?.['summary']);
  if ('' === summary) {
    return null;
  }
  const commit = parseProposedCommit(object?.['commit']);
  const mergeRequest = parseProposedMergeRequest(object?.['mergeRequest']);
  return { summary, ...(commit ? { commit } : {}), ...(mergeRequest ? { mergeRequest } : {}) };
}
