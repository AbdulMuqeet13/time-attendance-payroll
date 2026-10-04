import type { BadgeTone } from '@/components/status-badge';
import { StatusBadge, headline } from '@/components/status-badge';
import type { EmploymentStatus } from '@/types';

const tones: Record<EmploymentStatus, BadgeTone> = {
    active: 'success',
    suspended: 'warning',
    resigned: 'neutral',
    terminated: 'danger',
};

export function EmploymentStatusBadge({
    status,
}: {
    status: EmploymentStatus;
}) {
    return <StatusBadge tone={tones[status]}>{headline(status)}</StatusBadge>;
}
