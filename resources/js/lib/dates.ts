/**
 * Dates travel to and from the server as ISO `YYYY-MM-DD` strings and are
 * displayed to users as `DD-MM-YYYY`.
 */

function pad(value: number): string {
    return String(value).padStart(2, '0');
}

/**
 * Parse a `YYYY-MM-DD` string into a local Date (no timezone shift).
 */
export function parseIsoDate(
    value: string | null | undefined,
): Date | undefined {
    const match = /^(\d{4})-(\d{2})-(\d{2})/.exec(value ?? '');

    if (!match) {
        return undefined;
    }

    return new Date(Number(match[1]), Number(match[2]) - 1, Number(match[3]));
}

/**
 * Convert a Date to a `YYYY-MM-DD` string using its local calendar day.
 */
export function toIsoDate(date: Date): string {
    return `${date.getFullYear()}-${pad(date.getMonth() + 1)}-${pad(date.getDate())}`;
}

/**
 * Format a `YYYY-MM-DD` string or Date as `DD-MM-YYYY`.
 */
export function formatDate(value: string | Date | null | undefined): string {
    const date = value instanceof Date ? value : parseIsoDate(value);

    if (!date) {
        return '';
    }

    return `${pad(date.getDate())}-${pad(date.getMonth() + 1)}-${date.getFullYear()}`;
}

/**
 * Format a server timestamp ("2026-10-05 09:02:11" or ISO with offset) as `DD-MM-YYYY hh:mm AM`.
 */
export function formatDateTime(value: string | null | undefined): string {
    if (!value) {
        return '';
    }

    const date = new Date(
        value.includes('T') ? value : value.replace(' ', 'T'),
    );

    if (Number.isNaN(date.getTime())) {
        return value;
    }

    const hours = date.getHours();
    const time = `${pad(hours % 12 || 12)}:${pad(date.getMinutes())} ${hours < 12 ? 'AM' : 'PM'}`;

    return `${formatDate(date)} ${time}`;
}

/**
 * "just now", "5 min ago", "3 h ago", or the date for anything older than a day.
 */
export function timeAgo(value: string | null | undefined): string {
    if (!value) {
        return 'never';
    }

    const date = new Date(
        value.includes('T') ? value : value.replace(' ', 'T'),
    );
    const seconds = Math.round((Date.now() - date.getTime()) / 1000);

    if (seconds < 60) {
        return 'just now';
    }

    if (seconds < 3600) {
        return `${Math.floor(seconds / 60)} min ago`;
    }

    if (seconds < 86400) {
        return `${Math.floor(seconds / 3600)} h ago`;
    }

    return formatDateTime(value);
}
