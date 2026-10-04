import { Head } from '@inertiajs/react';
import { Plus } from 'lucide-react';
import { useState } from 'react';
import { ConfirmDialog } from '@/components/confirm-dialog';
import type { TableColumn } from '@/components/data-table';
import { DataTable, DataTableToolbar } from '@/components/data-table';
import Heading from '@/components/heading';
import type { SelectOption } from '@/components/option-select';
import { RowActions } from '@/components/organisation/row-actions';
import { SalaryComponentFormDialog } from '@/components/organisation/salary-component-form-dialog';
import { ActiveBadge, StatusBadge } from '@/components/status-badge';
import { Button } from '@/components/ui/button';
import { useCan } from '@/hooks/use-can';
import type { SalaryComponent } from '@/types';
import {
    destroy,
    index,
} from '@/actions/App/Http/Controllers/Organisation/SalaryComponentController';
import { dashboard } from '@/routes';

type SalaryComponentsPageProps = {
    salaryComponents: SalaryComponent[];
    componentTypes: SelectOption[];
};

export default function SalaryComponents({
    salaryComponents,
    componentTypes,
}: SalaryComponentsPageProps) {
    const { can } = useCan();
    const canManage = can('salaries.manage');

    const [isCreateOpen, setIsCreateOpen] = useState(false);
    const [editing, setEditing] = useState<SalaryComponent | null>(null);
    const [deleting, setDeleting] = useState<SalaryComponent | null>(null);

    const nextSortOrder =
        Math.max(0, ...salaryComponents.map((c) => c.sort_order)) + 1;

    const columns: TableColumn<SalaryComponent>[] = [
        {
            accessorKey: 'sort_order',
            header: () => <span>Order</span>,
            cell: ({ row }) => (
                <span className="text-muted-foreground">
                    {row.original.sort_order}
                </span>
            ),
        },
        {
            accessorKey: 'name',
            header: () => <span>Name</span>,
            cell: ({ row }) => (
                <span className="font-medium">{row.original.name}</span>
            ),
        },
        {
            accessorKey: 'type',
            header: () => <span>Type</span>,
            cell: ({ row }) => (
                <StatusBadge
                    tone={
                        row.original.type === 'earning' ? 'success' : 'warning'
                    }
                >
                    {row.original.type === 'earning' ? 'Earning' : 'Deduction'}
                </StatusBadge>
            ),
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
            <Head title="Salary Components" />

            <div className="flex h-full flex-1 flex-col gap-4 overflow-x-auto rounded-xl p-4">
                <Heading
                    title="Salary Components"
                    description="The parts that make up an employee's monthly salary breakdown."
                />

                <DataTable
                    columns={columns}
                    data={salaryComponents}
                    emptyMessage="No salary components yet."
                    toolbar={
                        <DataTableToolbar>
                            <div className="flex-1" />
                            {canManage && (
                                <Button
                                    size="sm"
                                    onClick={() => setIsCreateOpen(true)}
                                >
                                    <Plus className="mr-2 size-4" />
                                    Add Component
                                </Button>
                            )}
                        </DataTableToolbar>
                    }
                />
            </div>

            {(isCreateOpen || editing) && (
                <SalaryComponentFormDialog
                    salaryComponent={editing}
                    componentTypes={componentTypes}
                    nextSortOrder={nextSortOrder}
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
                    title="Delete Salary Component"
                    description={
                        <>
                            Delete <strong>{deleting.name}</strong>? It will no
                            longer appear on salary forms. Existing salary
                            records and payslips keep their amounts.
                        </>
                    }
                    url={destroy(deleting).url}
                />
            )}
        </>
    );
}

SalaryComponents.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard().url },
        { title: 'Salary Components', href: index().url },
    ],
};
