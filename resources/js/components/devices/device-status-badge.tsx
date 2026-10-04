import { StatusBadge } from '@/components/status-badge';
import type { Device } from '@/types';

export function DeviceStatusBadge({
    device,
}: {
    device: Pick<Device, 'branch_id' | 'is_active' | 'is_online'>;
}) {
    if (device.branch_id === null) {
        return <StatusBadge tone="warning">Unclaimed</StatusBadge>;
    }

    if (!device.is_active) {
        return <StatusBadge tone="neutral">Disabled</StatusBadge>;
    }

    return device.is_online ? (
        <StatusBadge tone="success">Online</StatusBadge>
    ) : (
        <StatusBadge tone="danger">Offline</StatusBadge>
    );
}
