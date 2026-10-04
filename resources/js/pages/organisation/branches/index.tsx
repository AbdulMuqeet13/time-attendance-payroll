import { Head } from '@inertiajs/react';
import { Plus } from 'lucide-react';
import { useState } from 'react';
import { ConfirmDialog } from '@/components/confirm-dialog';
import type { TableColumn } from '@/components/data-table';
import { DataTable, DataTableToolbar } from '@/components/data-table';
import Heading from '@/components/heading';
import { BranchFormDialog } from '@/components/organisation/branch-form-dialog';
import { RowActions } from '@/components/organisation/row-actions';
import { ActiveBadge } from '@/components/status-badge';
import { Button } from '@/components/ui/button';
import type { Branch } from '@/types';
import {
    destroy,
    index,
} from '@/actions/App/Http/Controllers/Organisation/BranchController';
import { dashboard } from '@/routes';

type BranchesPageProps = {
    branches: Branch[];
};

export default function Branches({ branches }: BranchesPageProps) {
    const [isCreateOpen, setIsCreateOpen] = useState(false);
    const [editing, setEditing] = useState<Branch | null>(null);
    const [deleting, setDeleting] = useState<Branch | null>(null);

    const columns: TableColumn<Branch>[] = [
        {
            accessorKey: 'code',
            header: () => <span>Code</span>,
            cell: ({ row }) => (
                <span className="font-mono text-sm">{row.original.code}</span>
            ),
        },
        {
            accessorKey: 'name',
            header: () => <span>Name</span>,
            cell: ({ row }) => (
                <div>
                    <div className="font-medium">{row.original.name}</div>
                    {row.original.address && (
                        <div className="text-xs text-muted-foreground">
                            {row.original.address}
                        </div>
                    )}
                </div>
            ),
        },
        {
            accessorKey: 'phone',
            header: () => <span>Phone</span>,
            cell: ({ row }) => row.original.phone ?? '—',
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
            <Head title="Branches" />

            <div className="flex h-full flex-1 flex-col gap-4 overflow-x-auto rounded-xl p-4">
                <Heading
                    title="Branches"
                    description="Company locations. Employees, devices and payroll runs belong to a branch."
                />

                <DataTable
                    columns={columns}
                    data={branches}
                    emptyMessage="No branches yet."
                    emptyDescription="Add your first branch to start adding employees."
                    toolbar={
                        <DataTableToolbar>
                            <div className="flex-1" />
                            <Button
                                size="sm"
                                onClick={() => setIsCreateOpen(true)}
                            >
                                <Plus className="mr-2 size-4" />
                                Add Branch
                            </Button>
                        </DataTableToolbar>
                    }
                />
            </div>

            {isCreateOpen && (
                <BranchFormDialog open onClose={() => setIsCreateOpen(false)} />
            )}

            {editing && (
                <BranchFormDialog
                    open
                    branch={editing}
                    onClose={() => setEditing(null)}
                />
            )}

            {deleting && (
                <ConfirmDialog
                    open
                    onClose={() => setDeleting(null)}
                    title="Delete Branch"
                    description={
                        <>
                            Delete <strong>{deleting.name}</strong>? Branches
                            with employees cannot be deleted.
                        </>
                    }
                    url={destroy(deleting).url}
                />
            )}
        </>
    );
}

Branches.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard().url },
        { title: 'Branches', href: index().url },
    ],
};
