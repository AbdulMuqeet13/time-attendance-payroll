import type { Shift, ShiftColor } from '@/types';

export const WEEKDAYS = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];

/**
 * "Mon–Sat", "Every day" or "Mon, Wed, Fri".
 */
export function formatDays(days: number[] | null): string {
    if (!days || days.length === 0 || days.length === 7) {
        return 'Every day';
    }

    const sorted = [...days].sort((a, b) => a - b);
    const isRun = sorted.every(
        (day, index) => index === 0 || day === sorted[index - 1] + 1,
    );

    if (isRun && sorted.length > 2) {
        return `${WEEKDAYS[sorted[0]]}–${WEEKDAYS[sorted[sorted.length - 1]]}`;
    }

    return sorted.map((day) => WEEKDAYS[day]).join(', ');
}

/**
 * "Morning (09:00–17:00)".
 */
export function shiftLabel(
    shift: Pick<Shift, 'name' | 'start_time' | 'end_time'>,
): string {
    return `${shift.name} (${shift.start_time}–${shift.end_time})`;
}

/**
 * 510 → "8h 30m".
 */
export function formatMinutes(minutes: number | null | undefined): string {
    const total = Math.max(0, Math.round(minutes ?? 0));
    const hours = Math.floor(total / 60);
    const rest = total % 60;

    if (hours === 0) {
        return `${rest}m`;
    }

    return rest === 0 ? `${hours}h` : `${hours}h ${rest}m`;
}

export const SHIFT_COLORS: Record<ShiftColor, string> = {
    sky: 'bg-sky-500',
    emerald: 'bg-emerald-500',
    amber: 'bg-amber-500',
    violet: 'bg-violet-500',
    rose: 'bg-rose-500',
    slate: 'bg-slate-500',
};
