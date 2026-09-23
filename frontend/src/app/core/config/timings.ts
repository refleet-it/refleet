/**
 * Timings that several screens have to agree on. They were copied into four page components and
 * both auth guards, so a change to one of them silently produced two behaviours instead of one.
 */

/** How often a screen re-asks the API whether a running job has moved on. */
export const POLL_INTERVAL_MS = 5000;

/** How long to wait for a keystroke to settle before turning it into a request. */
export const SEARCH_DEBOUNCE_MS = 300;

/** How long a guard waits for authentication to resolve before sending the visitor to /auth. */
export const AUTH_TIMEOUT_MS = 2000;
