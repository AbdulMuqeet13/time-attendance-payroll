import { Head, router, usePoll } from '@inertiajs/react';
import {
    ChevronDown,
    Download,
    Eraser,
    Info,
    Power,
    RotateCcw,
    Settings,
    Trash2,
    UserPlus,
    Users,
    X,
} from 'lucide-react';
import { useState } from 'react';
import { ConfirmDialog } from '@/components/confirm-dialog';
import type { TableColumn } from '@/components/data-table';
import { DataTable } from '@/components/data-table';
import { CommandStatusBadge } from '@/components/devices/command-status-badge';
import { AssignPinDialog } from '@/components/devices/assign-pin-dialog';
import { DeviceActionDialog } from '@/components/devices/device-action-dialog';
import { DeviceSettingsDialog } from '@/components/devices/device-settings-dialog';
import { DeviceStatusBadge } from '@/components/devices/device-status-badge';
import { DeviceUsersTable } from '@/components/devices/device-users-table';
import { StatCard } from '@/components/stat-card';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { formatDateTime, timeAgo } from '@/lib/dates';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import type {
    Device,
    DeviceCommand,
    DeviceUserRow,
    EmployeeOption,
    Option,
    Paginated,
} from '@/types';
import {
    action as deviceAction,
    cancelCommand,
    destroy,
    index,
    retryCommand,
} from '@/actions/App/Http/Controllers/Devices/DeviceController';
import { dashboard } from '@/routes';

type DeviceShowPageProps = {
    device: Device;
    commands: Paginated<DeviceCommand>;
    stats: {
        punches_today: number;
        last_punch_at: string | null;
        open_commands: number;
        failed_commands: number;
    };
    branches: Option[];
    deviceUsers?: DeviceUserRow[];
    employeesWithoutPin?: EmployeeOption[];
    can: { update: boolean; command: boolean };
};

export default function DeviceShow({
    device,
    commands,
    stats,
    branches,
    deviceUsers,
    employeesWithoutPin = [],
    can,
}: DeviceShowPageProps) {
    usePoll(10000, { only: ['device', 'commands', 'stats'] });

    const [isEditing, setIsEditing] = useState(false);
    const [isDeleting, setIsDeleting] = useState(false);
    const [linkingPin, setLinkingPin] = useState<string | null>(null);
    const [inputAction, setInputAction] = useState<
        'pull-logs' | 'clear-logs' | null
    >(null);

    const run = (action: string) =>
        router.post(
            deviceAction(device).url,
            { action },
            { preserveScroll: true },
        );

    const columns: TableColumn<DeviceCommand>[] = [
        {
            accessorKey: 'sequence',
            header: () => <span>#</span>,
            cell: ({ row }) => (
                <span className="font-mono text-xs">
                    {row.original.sequence}
                </span>
            ),
        },
        {
            accessorKey: 'command',
            header: () => <span>Command</span>,
            cell: ({ row }) => (
                <span className="font-mono text-xs break-all">
                    {row.original.command}
                </span>
            ),
        },
        {
            accessorKey: 'status',
            header: () => <span>Status</span>,
            cell: ({ row }) => (
                <div className="space-y-1">
                    <CommandStatusBadge status={row.original.status} />
                    {row.original.return_code !== null &&
                        row.original.return_code !== 0 && (
                            <div className="text-xs text-muted-foreground">
                                Code {row.original.return_code}
                            </div>
                        )}
                </div>
            ),
        },
        {
            id: 'timing',
            header: () => <span>Queued / Done</span>,
            cell: ({ row }) => (
                <div className="text-xs">
                    <div>{formatDateTime(row.original.created_at)}</div>
                    <div className="text-muted-foreground">
                        {row.original.executed_at
                            ? formatDateTime(row.original.executed_at)
                            : row.original.attempts > 1
                              ? `Attempt ${row.original.attempts}`
                              : ''}
                    </div>
                </div>
            ),
        },
    ];

    if (can.command) {
        columns.push({
            id: 'actions',
            header: () => <span className="sr-only">Actions</span>,
            cell: ({ row }) => {
                const command = row.original;

                if (
                    command.status === 'failed' ||
                    command.status === 'cancelled'
                ) {
                    return (
                        <Button
                            variant="ghost"
                            size="sm"
                            onClick={() =>
                                router.post(
                                    retryCommand([device, command.id]).url,
                                    {},
                                    { preserveScroll: true },
                                )
                            }
                        >
                            <RotateCcw className="mr-1 size-3.5" />
                            Retry
                        </Button>
                    );
                }

                if (command.status === 'pending' || command.status === 'sent') {
                    return (
                        <Button
                            variant="ghost"
                            size="sm"
                            onClick={() =>
                                router.post(
                                    cancelCommand([device, command.id]).url,
                                    {},
                                    { preserveScroll: true },
                                )
                            }
                        >
                            <X className="mr-1 size-3.5" />
                            Cancel
                        </Button>
                    );
                }

                return null;
            },
        });
    }

    return (
        <>
            <Head title={device.name} />

            <div className="flex h-full flex-1 flex-col gap-4 overflow-x-auto rounded-xl p-4">
                <div className="flex flex-wrap items-start justify-between gap-4">
                    <div>
                        <div className="flex items-center gap-3">
                            <h1 className="text-xl font-semibold tracking-tight">
                                {device.name}
                            </h1>
                            <DeviceStatusBadge device={device} />
                        </div>
                        <p className="text-sm text-muted-foreground">
                            <span className="font-mono">
                                {device.serial_number}
                            </span>
                            {device.branch && ` · ${device.branch.name}`} · last
                            contact {timeAgo(device.last_seen_at)}
                            {device.ip_address && ` from ${device.ip_address}`}
                        </p>
                        <p className="text-xs text-muted-foreground">
                            Firmware {device.firmware ?? 'unknown'} · push
                            protocol {device.push_version ?? 'unknown'} ·
                            fingerprint algorithm {device.fp_algorithm ?? '—'} ·
                            face algorithm {device.face_algorithm ?? '—'}
                        </p>
                    </div>
                    <div className="flex gap-2">
                        {can.update && (
                            <Button
                                variant="outline"
                                size="sm"
                                onClick={() => setIsEditing(true)}
                            >
                                <Settings className="mr-2 size-4" />
                                {device.branch_id ? 'Settings' : 'Claim'}
                            </Button>
                        )}
                        {can.command && (
                            <DropdownMenu>
                                <DropdownMenuTrigger asChild>
                                    <Button size="sm">
                                        Actions
                                        <ChevronDown className="ml-2 size-4" />
                                    </Button>
                                </DropdownMenuTrigger>
                                <DropdownMenuContent align="end">
                                    <DropdownMenuItem
                                        onClick={() => run('pull-users')}
                                    >
                                        <Users className="mr-2 size-4" />
                                        Re-read users & biometrics
                                    </DropdownMenuItem>
                                    <DropdownMenuItem
                                        onClick={() =>
                                            run('push-all-employees')
                                        }
                                    >
                                        <UserPlus className="mr-2 size-4" />
                                        Push all branch employees
                                    </DropdownMenuItem>
                                    <DropdownMenuItem
                                        onClick={() =>
                                            setInputAction('pull-logs')
                                        }
                                    >
                                        <Download className="mr-2 size-4" />
                                        Re-read attendance logs…
                                    </DropdownMenuItem>
                                    <DropdownMenuItem
                                        onClick={() => run('refresh-info')}
                                    >
                                        <Info className="mr-2 size-4" />
                                        Refresh device info
                                    </DropdownMenuItem>
                                    <DropdownMenuItem
                                        onClick={() => run('reboot')}
                                    >
                                        <Power className="mr-2 size-4" />
                                        Reboot
                                    </DropdownMenuItem>
                                    <DropdownMenuSeparator />
                                    <DropdownMenuItem
                                        onClick={() =>
                                            setInputAction('clear-logs')
                                        }
                                        className="text-destructive focus:text-destructive"
                                    >
                                        <Eraser className="mr-2 size-4" />
                                        Clear attendance log…
                                    </DropdownMenuItem>
                                </DropdownMenuContent>
                            </DropdownMenu>
                        )}
                        {can.update && (
                            <Button
                                variant="outline"
                                size="icon"
                                className="size-8"
                                onClick={() => setIsDeleting(true)}
                            >
                                <Trash2 className="size-4" />
                                <span className="sr-only">Remove device</span>
                            </Button>
                        )}
                    </div>
                </div>

                <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                    <StatCard
                        label="Scans today"
                        value={stats.punches_today}
                        hint={`Last scan ${timeAgo(stats.last_punch_at)}`}
                    />
                    <StatCard
                        label="Users on device"
                        value={device.user_count ?? '—'}
                        hint={`${device.fp_count ?? '—'} fingerprints · ${device.face_count ?? '—'} faces`}
                    />
                    <StatCard
                        label="Logs held on device"
                        value={device.att_count ?? '—'}
                    />
                    <StatCard
                        label="Queued commands"
                        value={stats.open_commands}
                        hint={
                            stats.failed_commands > 0
                                ? `${stats.failed_commands} failed`
                                : 'None failed'
                        }
                    />
                </div>

                <Tabs defaultValue="commands">
                    <TabsList>
                        <TabsTrigger value="commands">Command log</TabsTrigger>
                        <TabsTrigger value="users">Users on device</TabsTrigger>
                    </TabsList>
                    <TabsContent value="users" className="mt-4">
                        <DeviceUsersTable
                            rows={deviceUsers}
                            onLinkPin={setLinkingPin}
                        />
                    </TabsContent>
                    <TabsContent value="commands" className="mt-4">
                        <DataTable
                            columns={columns}
                            data={commands.data}
                            meta={commands}
                            onPageChange={(page) =>
                                router.reload({
                                    only: ['commands'],
                                    data: { page },
                                })
                            }
                            emptyMessage="No commands sent yet."
                            emptyDescription=""
                        />
                    </TabsContent>
                </Tabs>
            </div>

            {isEditing && (
                <DeviceSettingsDialog
                    device={device}
                    branches={branches}
                    onClose={() => setIsEditing(false)}
                />
            )}

            {inputAction && (
                <DeviceActionDialog
                    device={device}
                    action={inputAction}
                    onClose={() => setInputAction(null)}
                />
            )}

            {linkingPin && (
                <AssignPinDialog
                    unmatched={{
                        pin: linkingPin,
                        punches_count: 0,
                        first_punch_at: '',
                        last_punch_at: '',
                    }}
                    employees={employeesWithoutPin}
                    onClose={() => {
                        setLinkingPin(null);
                        router.reload({ only: ['deviceUsers'] });
                    }}
                />
            )}

            {isDeleting && (
                <ConfirmDialog
                    open
                    onClose={() => setIsDeleting(false)}
                    title="Remove Device"
                    description="Its scans stay in attendance. If it contacts the server again it re-registers as unclaimed."
                    url={destroy(device).url}
                    confirmLabel="Remove"
                />
            )}
        </>
    );
}

DeviceShow.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard().url },
        { title: 'Devices', href: index().url },
        { title: 'Device', href: index().url },
    ],
};
