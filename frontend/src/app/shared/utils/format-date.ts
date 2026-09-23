/** Every timestamp in the app is shown the same way: `dd/mm/yyyy, hh:mm`, or a dash when unset. */
export function formatDateTime(iso: string | null | undefined): string {
  if (!iso) {
    return '—';
  }

  return new Date(iso).toLocaleString('en-GB', {
    year: 'numeric',
    month: '2-digit',
    day: '2-digit',
    hour: '2-digit',
    minute: '2-digit',
  });
}
