import type { BadgeTone } from '@/components/status-badge';
import { StatusBadge, headline } from '@/components/status-badge';
import type { LeaveStatus } from '@/types';

const tones: Record<LeaveStatus, BadgeTone> = {
    pending: 'warning',
    approved: 'success',
    rejected: 'danger',
    cancelled: 'neutral',
};

export function LeaveStatusBadge({ status }: { status: LeaveStatus }) {
    return <StatusBadge tone={tones[status]}>{headline(status)}</StatusBadge>;
}
