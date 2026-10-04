import type { BadgeTone } from '@/components/status-badge';
import { StatusBadge, headline } from '@/components/status-badge';
import type { DeviceCommandStatus } from '@/types';

const tones: Record<DeviceCommandStatus, BadgeTone> = {
    pending: 'neutral',
    sent: 'info',
    succeeded: 'success',
    failed: 'danger',
    cancelled: 'neutral',
};

export function CommandStatusBadge({
    status,
}: {
    status: DeviceCommandStatus;
}) {
    return <StatusBadge tone={tones[status]}>{headline(status)}</StatusBadge>;
}
