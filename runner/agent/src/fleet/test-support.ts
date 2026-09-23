import type { FetchFn } from '../backend-client.js';

export interface RecordedRequest {
  method: string;
  url: string;
  headers: Record<string, string>;
  body: unknown;
}

export type ResponseScript = (request: RecordedRequest) => { status: number; body?: unknown } | Error;

/**
 * A fetch double for the fleet modules: records every request and answers from a
 * script keyed on the request, so a test can assert on wire bodies without any
 * network. A script returning an Error makes fetch reject (a connection failure).
 */
export function fakeFetch(script: ResponseScript): { fetchFn: FetchFn; requests: RecordedRequest[] } {
  const requests: RecordedRequest[] = [];

  const fetchFn: FetchFn = (input, init) => {
    const headers: Record<string, string> = {};
    for (const [key, value] of Object.entries(init?.headers ?? {})) {
      headers[key] = value;
    }
    const rawBody = init?.body;
    const request: RecordedRequest = {
      method: init?.method ?? 'GET',
      url: String(input),
      headers,
      body: 'string' === typeof rawBody ? (JSON.parse(rawBody) as unknown) : undefined,
    };
    requests.push(request);

    const scripted = script(request);
    if (scripted instanceof Error) {
      return Promise.reject(scripted);
    }
    const text = undefined === scripted.body ? '' : JSON.stringify(scripted.body);
    return Promise.resolve(new Response(text, { status: scripted.status }));
  };

  return { fetchFn, requests };
}

export function collectLog(): { log: (message: string) => void; lines: string[] } {
  const lines: string[] = [];
  return { log: message => lines.push(message), lines };
}
