/**
 * Formats an ISO date string into a human-readable date.
 *
 * Using `Intl.DateTimeFormat` with an explicit `timeZone: 'UTC'` makes the
 * output identical on the Node.js SSR process and in every browser, avoiding
 * React hydration mismatches caused by timezone or ICU locale differences
 * between the server and client environments.
 */
export function formatDate(iso: string): string {
  return new Intl.DateTimeFormat('en-GB', {
    day:      'numeric',
    month:    'long',
    year:     'numeric',
    timeZone: 'UTC',   // pin timezone so SSR and browser agree
  }).format(new Date(iso))
}
