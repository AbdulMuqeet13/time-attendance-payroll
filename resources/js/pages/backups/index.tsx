import { Head, router, usePoll } from '@inertiajs/react';
import {
    DatabaseBackup,
    Download,
    MoreHorizontal,
    RotateCcw,
    Trash2,
    Upload,
    UploadCloud,
    Users,
} from 'lucide-react';
import { useState } from 'react';
import { RestoreDialog } from '@/components/backups/restore-dialog';
import { NewBackupDialog } from '@/components/backups/new-backup-dialog';
import { UploadBackupDialog } from '@/components/backups/upload-backup-dialog';
import { ConfirmDialog } from '@/components/confirm-dialog';
import type { TableColumn } from '@/components/data-table';
import { DataTable, DataTableToolbar } from '@/components/data-table';
import Heading from '@/components/heading';
import { OptionSelect } from '@/components/option-select';
import type { BadgeTone } from '@/components/status-badge';
import { StatusBadge, headline } from '@/components/status-badge';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { useDataTable } from '@/hooks/use-data-table';
import { formatDateTime } from '@/lib/dates';
import type { BackupDevice, DeviceBackup, DeviceRestore } from '@/types';
import {
    destroy,
    download,
    importLogs,
    index,
} from '@/actions/App/Http/Controllers/Backups/DeviceBackupController';
import { dashboard } from '@/routes';

type BackupsPageProps = {
    backups: DeviceBackup[];
    restores: DeviceRestore[];
    devices: BackupDevice[];
};

const backupTones: Record<DeviceBackup['status'], BadgeTone> = {
    collecting: 'info',
    completed: 'success',
    partial: 'warning',
    failed: 'danger',
};
const restoreTones: Record<DeviceRestore['status'], BadgeTone> = {
    running: 'info',
    completed: 'success',
    partial: 'warning',
    failed: 'danger',
};
const MODE_LABELS: Record<DeviceBackup['mode'], string> = {
    device_query: 'From device',
    server_snapshot: 'From app',
    uploaded: 'Uploaded',
};

function formatSize(bytes: number | null): string {
    if (!bytes) {
        return '—';
    }

    return bytes < 1024 * 1024
        ? `${Math.ceil(bytes / 1024)} KB`
        : `${(bytes / 1024 / 1024).toFixed(1)} MB`;
}

export default function Backups({
    backups,
    restores,
    devices,
}: BackupsPageProps) {
    const table = useDataTable({ only: ['backups'] });
    const isBusy =
        backups.some((backup) => backup.status === 'collecting') ||
        restores.some((restore) => restore.status === 'running');
    usePoll(5000, { only: ['backups', 'restores'] }, { autoStart: isBusy });

    const [isCreating, setIsCreating] = useState(false);
    const [isUploading, setIsUploading] = useState(false);
    const [restoring, setRestoring] = useState<{
        backup: DeviceBackup | null;
    } | null>(null);
    const [deleting, setDeleting] = useState<DeviceBackup | null>(null);

    const backupColumns: TableColumn<DeviceBackup>[] = [
        {
            id: 'device',
            header: () => <span>Device</span>,
            cell: ({ row }) => (
                <div>
                    <div className="font-medium">
                        {row.original.device_name}
                    </div>
                    <div className="font-mono text-xs text-muted-foreground">
                        {row.original.device_serial}
                    </div>
                </div>
            ),
        },
        {
            id: 'when',
            header: () => <span>Taken</span>,
            cell: ({ row }) => (
                <div className="text-sm">
                    <div>{formatDateTime(row.original.created_at)}</div>
                    <div className="text-xs text-muted-foreground">
                        {MODE_LABELS[row.original.mode]}
                        {row.original.creator
                            ? ` · ${row.original.creator.name}`
                            : ' · automatic'}
                    </div>
                </div>
            ),
        },
        {
            id: 'contents',
            header: () => <span>Contents</span>,
            cell: ({ row }) =>
                row.original.counts ? (
                    <span className="text-xs">
                        {row.original.counts.users} users ·{' '}
                        {row.original.counts.fingerprints} fingerprints ·{' '}
                        {row.original.counts.faces} faces ·{' '}
                        {row.original.counts.logs} logs
                    </span>
                ) : (
                    <span className="text-xs text-muted-foreground">
                        Collecting…
                    </span>
                ),
        },
        {
            id: 'size',
            header: () => <span>Size</span>,
            cell: ({ row }) => formatSize(row.original.file_size),
        },
        {
            id: 'status',
            header: () => <span>Status</span>,
            cell: ({ row }) => (
                <div>
                    <StatusBadge tone={backupTones[row.original.status]}>
                        {headline(row.original.status)}
                    </StatusBadge>
                    {row.original.error && (
                        <p className="mt-1 max-w-48 text-xs text-muted-foreground">
                            {row.original.error}
                        </p>
                    )}
                </div>
            ),
        },
        {
            id: 'actions',
            header: () => <span className="sr-only">Actions</span>,
            cell: ({ row }) => {
                const backup = row.original;
                const hasFile =
                    backup.status !== 'collecting' && backup.file_size !== null;

                return (
                    <DropdownMenu>
                        <DropdownMenuTrigger asChild>
                            <Button
                                variant="ghost"
                                size="icon"
                                className="size-8"
                            >
                                <MoreHorizontal className="size-4" />
                                <span className="sr-only">Open menu</span>
                            </Button>
                        </DropdownMenuTrigger>
                        <DropdownMenuContent align="end">
                            <DropdownMenuItem disabled={!hasFile} asChild>
                                <a href={download(backup).url}>
                                    <Download className="mr-2 size-4" />{' '}
                                    Download
                                </a>
                            </DropdownMenuItem>
                            <DropdownMenuItem
                                disabled={
                                    !hasFile ||
                                    (!backup.include_users &&
                                        !backup.include_templates)
                                }
                                onClick={() => setRestoring({ backup })}
                            >
                                <RotateCcw className="mr-2 size-4" /> Restore to
                                a device…
                            </DropdownMenuItem>
                            <DropdownMenuItem
                                disabled={!hasFile || !backup.counts?.logs}
                                onClick={() =>
                                    router.post(
                                        importLogs(backup).url,
                                        {},
                                        { preserveScroll: true },
                                    )
                                }
                            >
                                <UploadCloud className="mr-2 size-4" /> Import
                                logs into attendance
                            </DropdownMenuItem>
                            <DropdownMenuSeparator />
                            <DropdownMenuItem
                                className="text-destructive focus:text-destructive"
                                onClick={() => setDeleting(backup)}
                            >
                                <Trash2 className="mr-2 size-4" /> Delete
                            </DropdownMenuItem>
                        </DropdownMenuContent>
                    </DropdownMenu>
                );
            },
        },
    ];

    const restoreColumns: TableColumn<DeviceRestore>[] = [
        {
            id: 'target',
            header: () => <span>Target device</span>,
            cell: ({ row }) => (
                <span className="font-medium">
                    {row.original.target_device.name}
                </span>
            ),
        },
        {
            id: 'source',
            header: () => <span>Source</span>,
            cell: ({ row }) =>
                row.original.backup
                    ? `Backup of ${row.original.backup.device_name} (${formatDateTime(row.original.backup.created_at)})`
                    : 'App data',
        },
        {
            id: 'sent',
            header: () => <span>Sent</span>,
            cell: ({ row }) => (
                <span className="text-xs">
                    {row.original.users_count} users ·{' '}
                    {row.original.templates_count} templates
                    {row.original.skipped_templates > 0 &&
                        ` · ${row.original.skipped_templates} skipped`}
                    {row.original.clear_first && ' · wiped first'}
                </span>
            ),
        },
        {
            id: 'progress',
            header: () => <span>Progress</span>,
            cell: ({ row }) => {
                const done =
                    row.original.succeeded_commands +
                    row.original.failed_commands;
                const percent =
                    row.original.total_commands === 0
                        ? 100
                        : Math.round(
                              (done / row.original.total_commands) * 100,
                          );

                return (
                    <div className="w-40">
                        <div className="h-2 overflow-hidden rounded-full bg-muted">
                            <div
                                className="h-full bg-primary transition-all"
                                style={{ width: `${percent}%` }}
                            />
                        </div>
                        <div className="mt-1 text-xs text-muted-foreground">
                            {done} / {row.original.total_commands}
                            {row.original.failed_commands > 0 &&
                                ` · ${row.original.failed_commands} failed`}
                        </div>
                    </div>
                );
            },
        },
        {
            id: 'status',
            header: () => <span>Status</span>,
            cell: ({ row }) => (
                <StatusBadge tone={restoreTones[row.original.status]}>
                    {headline(row.original.status)}
                </StatusBadge>
            ),
        },
    ];

    return (
        <>
            <Head title="Device Backups" />
            <div className="flex h-full flex-1 flex-col gap-4 overflow-x-auto rounded-xl p-4">
                <div className="flex flex-wrap items-start justify-between gap-4">
                    <Heading
                        title="Device Backups"
                        description="Back up users, fingerprints, faces and logs; restore them to the same or a replacement device."
                    />
                    <div className="flex flex-wrap gap-2">
                        <Button
                            size="sm"
                            variant="outline"
                            onClick={() => setIsUploading(true)}
                        >
                            <Upload className="mr-2 size-4" /> Upload
                        </Button>
                        <Button
                            size="sm"
                            variant="outline"
                            onClick={() => setRestoring({ backup: null })}
                            disabled={devices.length === 0}
                        >
                            <Users className="mr-2 size-4" /> Restore from app
                        </Button>
                        <Button
                            size="sm"
                            onClick={() => setIsCreating(true)}
                            disabled={devices.length === 0}
                        >
                            <DatabaseBackup className="mr-2 size-4" /> Back Up
                            Now
                        </Button>
                    </div>
                </div>

                <Tabs defaultValue="backups">
                    <TabsList>
                        <TabsTrigger value="backups">
                            Backups ({backups.length})
                        </TabsTrigger>
                        <TabsTrigger value="restores">
                            Restores ({restores.length})
                        </TabsTrigger>
                    </TabsList>
                    <TabsContent value="backups" className="mt-4">
                        <DataTable
                            columns={backupColumns}
                            data={backups}
                            emptyMessage="No backups yet."
                            emptyDescription="Back up a device now, or turn on automatic backups in its settings."
                            toolbar={
                                <DataTableToolbar>
                                    <OptionSelect
                                        size="sm"
                                        className="w-56"
                                        value={String(
                                            table.filters.device_id ?? '',
                                        )}
                                        onChange={(value) =>
                                            table.setFilter(
                                                'device_id',
                                                value || undefined,
                                            )
                                        }
                                        options={devices.map((device) => ({
                                            value: String(device.id),
                                            label: device.name,
                                        }))}
                                        noneLabel="All devices"
                                        placeholder="All devices"
                                    />
                                </DataTableToolbar>
                            }
                        />
                    </TabsContent>
                    <TabsContent value="restores" className="mt-4">
                        <DataTable
                            columns={restoreColumns}
                            data={restores}
                            emptyMessage="No restores yet."
                            emptyDescription=""
                        />
                    </TabsContent>
                </Tabs>
            </div>

            {isCreating && (
                <NewBackupDialog
                    devices={devices}
                    onClose={() => setIsCreating(false)}
                />
            )}
            {isUploading && (
                <UploadBackupDialog
                    devices={devices}
                    onClose={() => setIsUploading(false)}
                />
            )}
            {restoring && (
                <RestoreDialog
                    backup={restoring.backup}
                    devices={devices}
                    onClose={() => setRestoring(null)}
                />
            )}
            {deleting && (
                <ConfirmDialog
                    open
                    onClose={() => setDeleting(null)}
                    title="Delete Backup"
                    description={`Delete the backup of ${deleting.device_name} from ${formatDateTime(deleting.created_at)}? The file is removed permanently.`}
                    url={destroy(deleting).url}
                />
            )}
        </>
    );
}

Backups.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard().url },
        { title: 'Device Backups', href: index().url },
    ],
};
