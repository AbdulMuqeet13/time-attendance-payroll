/**
 * "2026-10-05 09:02:11" → "09:02"; also shows the date when it differs from `onDate`.
 */
export function formatTime(
    value: string | null | undefined,
    onDate?: string,
): string {
    if (!value) {
        return '—';
    }

    const [date, time = ''] = value.split(' ');
    const hhmm = time.slice(0, 5);

    if (onDate && date !== onDate) {
        const [, month, day] = date.split('-');

        return `${hhmm} (${day}/${month})`;
    }

    return hhmm;
}
