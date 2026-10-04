import { Head, Link } from '@inertiajs/react';
import { AlertTriangle, Fingerprint } from 'lucide-react';
import { useState } from 'react';
import type { TableColumn } from '@/components/data-table';
import { DataTable, DataTableToolbar } from '@/components/data-table';
import { DeviceSettingsDialog } from '@/components/devices/device-settings-dialog';
import { DeviceSetupCard } from '@/components/devices/device-setup-card';
import { DeviceStatusBadge } from '@/components/devices/device-status-badge';
import Heading from '@/components/heading';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import { timeAgo } from '@/lib/dates';
import type { Device, Option } from '@/types';
import {
    index,
    show,
} from '@/actions/App/Http/Controllers/Devices/DeviceController';
import { index as unmatchedPunches } from '@/actions/App/Http/Controllers/Devices/UnmatchedPunchController';
import { dashboard } from '@/routes';

type DevicesPageProps = {
    devices: Device[];
    unmatchedPunchCount: number;
    serverUrl: string;
    branches: Option[];
    canManage: boolean;
};

export default function Devices({
    devices,
    unmatchedPunchCount,
    serverUrl,
    branches,
    canManage,
}: DevicesPageProps) {
    const [claiming, setClaiming] = useState<Device | null>(null);

    const columns: TableColumn<Device>[] = [
        {
            accessorKey: 'name',
            header: () => <span>Device</span>,
            cell: ({ row }) => (
                <div>
                    <Link
                        href={show(row.original).url}
                        className="font-medium hover:underline"
                    >
                        {row.original.name}
                    </Link>
                    <div className="font-mono text-xs text-muted-foreground">
                        {row.original.serial_number}
                    </div>
                </div>
            ),
        },
        {
            id: 'branch',
            header: () => <span>Branch</span>,
            cell: ({ row }) => row.original.branch?.name ?? '—',
        },
        {
            id: 'status',
            header: () => <span>Status</span>,
            cell: ({ row }) => <DeviceStatusBadge device={row.original} />,
        },
        {
            accessorKey: 'last_seen_at',
            header: () => <span>Last contact</span>,
            cell: ({ row }) => (
                <div className="text-sm">
                    <div>{timeAgo(row.original.last_seen_at)}</div>
                    <div className="font-mono text-xs text-muted-foreground">
                        {row.original.ip_address ?? ''}
                    </div>
                </div>
            ),
        },
        {
            id: 'counts',
            header: () => <span>Users / FP / Faces</span>,
            cell: ({ row }) =>
                [
                    row.original.user_count,
                    row.original.fp_count,
                    row.original.face_count,
                ]
                    .map((count) => count ?? '—')
                    .join(' / '),
        },
        {
            accessorKey: 'open_commands_count',
            header: () => <span>Queued</span>,
            cell: ({ row }) => row.original.open_commands_count ?? 0,
        },
        {
            id: 'actions',
            header: () => <span className="sr-only">Actions</span>,
            cell: ({ row }) =>
                row.original.branch_id === null && canManage ? (
                    <Button size="sm" onClick={() => setClaiming(row.original)}>
                        Claim
                    </Button>
                ) : (
                    <Button size="sm" variant="ghost" asChild>
                        <Link href={show(row.original).url}>Open</Link>
                    </Button>
                ),
        },
    ];

    return (
        <>
            <Head title="Devices" />

            <div className="flex h-full flex-1 flex-col gap-4 overflow-x-auto rounded-xl p-4">
                <Heading
                    title="Devices"
                    description="ZKTeco attendance devices connected over ADMS push."
                />

                {unmatchedPunchCount > 0 && (
                    <Alert>
                        <AlertTriangle className="size-4" />
                        <AlertTitle>
                            {unmatchedPunchCount} scans don't match any employee
                        </AlertTitle>
                        <AlertDescription>
                            <p>
                                Their device PINs aren't set on any employee
                                yet.{' '}
                                <Link
                                    href={unmatchedPunches().url}
                                    className="font-medium underline"
                                >
                                    Review unmatched scans
                                </Link>
                            </p>
                        </AlertDescription>
                    </Alert>
                )}

                <DataTable
                    columns={columns}
                    data={devices}
                    emptyMessage="No devices have connected yet."
                    emptyDescription="Point a device at this server using the steps below."
                    toolbar={
                        <DataTableToolbar>
                            <div className="flex items-center gap-2 text-sm text-muted-foreground">
                                <Fingerprint className="size-4" />
                                {
                                    devices.filter((d) => d.is_online).length
                                } of{' '}
                                {devices.length} online
                            </div>
                        </DataTableToolbar>
                    }
                />

                <DeviceSetupCard serverUrl={serverUrl} />
            </div>

            {claiming && (
                <DeviceSettingsDialog
                    device={claiming}
                    branches={branches}
                    onClose={() => setClaiming(null)}
                />
            )}
        </>
    );
}

Devices.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard().url },
        { title: 'Devices', href: index().url },
    ],
};
