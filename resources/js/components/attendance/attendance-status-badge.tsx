import type { BadgeTone } from '@/components/status-badge';
import { StatusBadge, headline } from '@/components/status-badge';
import type { AttendanceStatus } from '@/types';

export const attendanceTones: Record<AttendanceStatus, BadgeTone> = {
    present: 'success',
    late: 'warning',
    half_day: 'warning',
    absent: 'danger',
    leave: 'info',
    holiday: 'info',
    weekly_off: 'neutral',
    scheduled: 'neutral',
    unscheduled: 'neutral',
};

export function AttendanceStatusBadge({
    status,
}: {
    status: AttendanceStatus;
}) {
    return (
        <StatusBadge tone={attendanceTones[status]}>
            {headline(status)}
        </StatusBadge>
    );
}
