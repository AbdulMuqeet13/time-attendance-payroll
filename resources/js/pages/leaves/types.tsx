import { Head } from '@inertiajs/react';
import { Plus } from 'lucide-react';
import { useState } from 'react';
import { ConfirmDialog } from '@/components/confirm-dialog';
import type { TableColumn } from '@/components/data-table';
import { DataTable, DataTableToolbar } from '@/components/data-table';
import Heading from '@/components/heading';
import { LeaveTypeFormDialog } from '@/components/leaves/leave-type-form-dialog';
import type { SelectOption } from '@/components/option-select';
import { RowActions } from '@/components/organisation/row-actions';
import { ActiveBadge, StatusBadge, headline } from '@/components/status-badge';
import { Button } from '@/components/ui/button';
import type { LeaveType } from '@/types';
import {
    destroy,
    index,
} from '@/actions/App/Http/Controllers/Leaves/LeaveTypeController';
import { dashboard } from '@/routes';

type LeaveTypesPageProps = {
    leaveTypes: LeaveType[];
    genders: SelectOption[];
    canManage: boolean;
};

export default function LeaveTypes({
    leaveTypes,
    genders,
    canManage,
}: LeaveTypesPageProps) {
    const [isCreating, setIsCreating] = useState(false);
    const [editing, setEditing] = useState<LeaveType | null>(null);
    const [deleting, setDeleting] = useState<LeaveType | null>(null);

    const columns: TableColumn<LeaveType>[] = [
        {
            accessorKey: 'name',
            header: () => <span>Leave type</span>,
            cell: ({ row }) => (
                <span className="font-medium">
                    {row.original.name}{' '}
                    <span className="font-mono text-xs text-muted-foreground">
                        {row.original.code}
                    </span>
                </span>
            ),
        },
        {
            id: 'paid',
            header: () => <span>Pay</span>,
            cell: ({ row }) => (
                <StatusBadge
                    tone={row.original.is_paid ? 'success' : 'neutral'}
                >
                    {row.original.is_paid ? 'Paid' : 'Unpaid'}
                </StatusBadge>
            ),
        },
        {
            id: 'quota',
            header: () => <span>Days / year</span>,
            cell: ({ row }) =>
                Number(row.original.yearly_quota) > 0
                    ? Number(row.original.yearly_quota)
                    : 'No limit',
        },
        {
            id: 'carry',
            header: () => <span>Carry forward</span>,
            cell: ({ row }) => Number(row.original.carry_forward_max) || '—',
        },
        {
            id: 'rules',
            header: () => <span>Rules</span>,
            cell: ({ row }) => (
                <span className="text-xs text-muted-foreground">
                    {[
                        row.original.allow_half_day && 'half days',
                        row.original.requires_attachment && 'document required',
                        row.original.gender &&
                            `${headline(row.original.gender)} only`,
                    ]
                        .filter(Boolean)
                        .join(' · ') || '—'}
                </span>
            ),
        },
        {
            id: 'active',
            header: () => <span>Status</span>,
            cell: ({ row }) => (
                <ActiveBadge active={row.original.is_active ?? true} />
            ),
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
            <Head title="Leave Types" />
            <div className="flex h-full flex-1 flex-col gap-4 overflow-x-auto rounded-xl p-4">
                <Heading
                    title="Leave Types"
                    description="Paid types with days per year are tracked against each employee's balance."
                />
                <DataTable
                    columns={columns}
                    data={leaveTypes}
                    toolbar={
                        <DataTableToolbar>
                            <div className="flex-1" />
                            {canManage && (
                                <Button
                                    size="sm"
                                    onClick={() => setIsCreating(true)}
                                >
                                    <Plus className="mr-2 size-4" /> Add Leave
                                    Type
                                </Button>
                            )}
                        </DataTableToolbar>
                    }
                />
            </div>
            {(isCreating || editing) && (
                <LeaveTypeFormDialog
                    leaveType={editing}
                    genders={genders}
                    onClose={() => {
                        setIsCreating(false);
                        setEditing(null);
                    }}
                />
            )}
            {deleting && (
                <ConfirmDialog
                    open
                    onClose={() => setDeleting(null)}
                    title="Delete Leave Type"
                    description={`Delete ${deleting.name}? Types that have requests can only be made inactive.`}
                    url={destroy(deleting).url}
                />
            )}
        </>
    );
}

LeaveTypes.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard().url },
        { title: 'Leave Types', href: index().url },
    ],
};
