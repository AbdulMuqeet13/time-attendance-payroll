import { Plus } from 'lucide-react';
import { useState } from 'react';
import { ConfirmDialog } from '@/components/confirm-dialog';
import type { TableColumn } from '@/components/data-table';
import { DataTable, DataTableToolbar } from '@/components/data-table';
import Heading from '@/components/heading';
import { NamedRecordFormDialog } from '@/components/organisation/named-record-form-dialog';
import { RowActions } from '@/components/organisation/row-actions';
import { ActiveBadge } from '@/components/status-badge';
import { Button } from '@/components/ui/button';
import type { NamedRecord } from '@/types';

type NamedRecordPageProps = {
    /** Singular noun, e.g. "Department". */
    noun: string;
    title: string;
    description: string;
    records: NamedRecord[];
    storeUrl: string;
    updateUrl: (record: NamedRecord) => string;
    destroyUrl: (record: NamedRecord) => string;
};

/**
 * List + add/edit/delete for organisation records that only have a name and an active flag
 * (departments, designations).
 */
export function NamedRecordPage({
    noun,
    title,
    description,
    records,
    storeUrl,
    updateUrl,
    destroyUrl,
}: NamedRecordPageProps) {
    const [isCreateOpen, setIsCreateOpen] = useState(false);
    const [editing, setEditing] = useState<NamedRecord | null>(null);
    const [deleting, setDeleting] = useState<NamedRecord | null>(null);

    const columns: TableColumn<NamedRecord>[] = [
        {
            accessorKey: 'name',
            header: () => <span>Name</span>,
            cell: ({ row }) => (
                <span className="font-medium">{row.original.name}</span>
            ),
        },
        {
            accessorKey: 'employees_count',
            header: () => <span>Active Employees</span>,
            cell: ({ row }) => row.original.employees_count ?? 0,
        },
        {
            accessorKey: 'is_active',
            header: () => <span>Status</span>,
            cell: ({ row }) => <ActiveBadge active={row.original.is_active} />,
        },
        {
            id: 'actions',
            header: () => <span className="sr-only">Actions</span>,
            cell: ({ row }) => (
                <RowActions
                    onEdit={() => setEditing(row.original)}
                    onDelete={() => setDeleting(row.original)}
                />
            ),
        },
    ];

    return (
        <>
            <div className="flex h-full flex-1 flex-col gap-4 overflow-x-auto rounded-xl p-4">
                <Heading title={title} description={description} />

                <DataTable
                    columns={columns}
                    data={records}
                    emptyMessage={`No ${title.toLowerCase()} yet.`}
                    emptyDescription=""
                    toolbar={
                        <DataTableToolbar>
                            <div className="flex-1" />
                            <Button
                                size="sm"
                                onClick={() => setIsCreateOpen(true)}
                            >
                                <Plus className="mr-2 size-4" />
                                Add {noun}
                            </Button>
                        </DataTableToolbar>
                    }
                />
            </div>

            {(isCreateOpen || editing) && (
                <NamedRecordFormDialog
                    noun={noun}
                    record={editing}
                    url={editing ? updateUrl(editing) : storeUrl}
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
                    title={`Delete ${noun}`}
                    description={
                        <>
                            Delete <strong>{deleting.name}</strong>? A{' '}
                            {noun.toLowerCase()} that employees use cannot be
                            deleted; mark it inactive instead.
                        </>
                    }
                    url={destroyUrl(deleting)}
                />
            )}
        </>
    );
}
