import type { BadgeTone } from '@/components/status-badge';
import { StatusBadge, headline } from '@/components/status-badge';
import type { PayrollStatus } from '@/types';

const tones: Record<PayrollStatus, BadgeTone> = {
    draft: 'warning',
    approved: 'info',
    paid: 'success',
    cancelled: 'neutral',
};

export function PayrollStatusBadge({ status }: { status: PayrollStatus }) {
    return <StatusBadge tone={tones[status]}>{headline(status)}</StatusBadge>;
}
