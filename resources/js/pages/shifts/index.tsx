import { Head, Link } from '@inertiajs/react';
import { Plus } from 'lucide-react';
import { useState } from 'react';
import { ConfirmDialog } from '@/components/confirm-dialog';
import type { TableColumn } from '@/components/data-table';
import { DataTable, DataTableToolbar } from '@/components/data-table';
import Heading from '@/components/heading';
import { RowActions } from '@/components/organisation/row-actions';
import type { ShiftPolicyDefaults } from '@/components/shifts/shift-form-dialog';
import { ShiftFormDialog } from '@/components/shifts/shift-form-dialog';
import { ActiveBadge, StatusBadge } from '@/components/status-badge';
import { Button } from '@/components/ui/button';
import { useCan } from '@/hooks/use-can';
import { SHIFT_COLORS, formatMinutes } from '@/lib/shifts';
import { cn } from '@/lib/utils';
import type { Shift } from '@/types';
import {
    destroy,
    index,
} from '@/actions/App/Http/Controllers/Shifts/ShiftController';
import { index as roster } from '@/actions/App/Http/Controllers/Shifts/ShiftAssignmentController';
import { dashboard } from '@/routes';

type ShiftsPageProps = {
    shifts: Shift[];
    defaults: ShiftPolicyDefaults;
};

export default function Shifts({ shifts, defaults }: ShiftsPageProps) {
    const { can } = useCan();
    const canManage = can('shifts.manage');

    const [isCreateOpen, setIsCreateOpen] = useState(false);
    const [editing, setEditing] = useState<Shift | null>(null);
    const [deleting, setDeleting] = useState<Shift | null>(null);

    const columns: TableColumn<Shift>[] = [
        {
            accessorKey: 'name',
            header: () => <span>Shift</span>,
            cell: ({ row }) => (
                <div className="flex items-center gap-2">
                    <span
                        className={cn(
                            'size-2.5 rounded-full',
                            SHIFT_COLORS[row.original.color],
                        )}
                    />
                    <span className="font-medium">{row.original.name}</span>
                </div>
            ),
        },
        {
            id: 'times',
            header: () => <span>Times</span>,
            cell: ({ row }) => (
                <span className="font-mono text-sm">
                    {row.original.start_time}–{row.original.end_time}
                    {row.original.is_overnight && (
                        <StatusBadge tone="info" className="ml-2 font-sans">
                            Overnight
                        </StatusBadge>
                    )}
                </span>
            ),
        },
        {
            accessorKey: 'break_minutes',
            header: () => <span>Break</span>,
            cell: ({ row }) => formatMinutes(row.original.break_minutes),
        },
        {
            accessorKey: 'scheduled_minutes',
            header: () => <span>Work</span>,
            cell: ({ row }) => formatMinutes(row.original.scheduled_minutes),
        },
        {
            accessorKey: 'late_grace_minutes',
            header: () => <span>Late after</span>,
            cell: ({ row }) =>
                `${row.original.late_grace_minutes ?? defaults.late_grace_minutes} min`,
        },
        {
            accessorKey: 'employees_count',
            header: () => <span>Employees</span>,
            cell: ({ row }) => row.original.employees_count ?? 0,
        },
        {
            accessorKey: 'is_active',
            header: () => <span>Status</span>,
            cell: ({ row }) => <ActiveBadge active={row.original.is_active} />,
        },
    ];

    if (canManage) {
        columns.push({
            id: 'actions',
            header: () => <span className="sr-only">Actions</span>,
            cell: ({ row }) => (
                <RowActions
                    onEdit={() => setEditing(row.original)}
                    onDelete={() => setDeleting(row.original)}
                />
            ),
        });
    }

    return (
        <>
            <Head title="Shifts" />

            <div className="flex h-full flex-1 flex-col gap-4 overflow-x-auto rounded-xl p-4">
                <Heading
                    title="Shifts"
                    description="Shift templates. Assign them to employees on the roster."
                />

                <DataTable
                    columns={columns}
                    data={shifts}
                    emptyMessage="No shifts yet."
                    emptyDescription="Add a shift, then assign it to employees on the roster."
                    toolbar={
                        <DataTableToolbar>
                            <div className="flex-1" />
                            <Button size="sm" variant="outline" asChild>
                                <Link href={roster().url}>Open Roster</Link>
                            </Button>
                            {canManage && (
                                <Button
                                    size="sm"
                                    onClick={() => setIsCreateOpen(true)}
                                >
                                    <Plus className="mr-2 size-4" />
                                    Add Shift
                                </Button>
                            )}
                        </DataTableToolbar>
                    }
                />
            </div>

            {(isCreateOpen || editing) && (
                <ShiftFormDialog
                    shift={editing}
                    defaults={defaults}
                    onClose={() => {
                        setIsCreateOpen(false);
                        setEditing(null);
                    }}
                />
            )}

            {deleting && (
                <ConfirmDialog
                    open
                    onClose={() => setDeleting(null)}
                    title="Delete Shift"
                    description={
                        <>
                            Delete <strong>{deleting.name}</strong>? Past
                            attendance keeps showing it. Shifts still assigned
                            to employees cannot be deleted.
                        </>
                    }
                    url={destroy(deleting).url}
                />
            )}
        </>
    );
}

Shifts.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard().url },
        { title: 'Shifts', href: index().url },
    ],
};
