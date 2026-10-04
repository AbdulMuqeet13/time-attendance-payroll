import { Head, Link, router } from '@inertiajs/react';
import { Plus } from 'lucide-react';
import { useState } from 'react';
import type { TableColumn } from '@/components/data-table';
import { DataTable, DataTableToolbar } from '@/components/data-table';
import Heading from '@/components/heading';
import { NewRunDialog } from '@/components/payroll/new-run-dialog';
import { PayrollStatusBadge } from '@/components/payroll/payroll-status-badge';
import { Button } from '@/components/ui/button';
import { formatDate } from '@/lib/dates';
import { formatAmount } from '@/lib/utils';
import type { Option, Paginated, PayrollRun } from '@/types';
import {
    index,
    show,
} from '@/actions/App/Http/Controllers/Payroll/PayrollRunController';
import { dashboard } from '@/routes';

type PayrollPageProps = {
    runs: Paginated<PayrollRun>;
    branches: Option[];
    defaultPeriod: { start: string; end: string };
    canRun: boolean;
};

export default function PayrollIndex({
    runs,
    branches,
    defaultPeriod,
    canRun,
}: PayrollPageProps) {
    const [isCreating, setIsCreating] = useState(false);

    const columns: TableColumn<PayrollRun>[] = [
        {
            accessorKey: 'reference',
            header: () => <span>Run</span>,
            cell: ({ row }) => (
                <Link
                    href={show(row.original).url}
                    className="font-mono font-medium hover:underline"
                >
                    {row.original.reference}
                </Link>
            ),
        },
        {
            id: 'period',
            header: () => <span>Period</span>,
            cell: ({ row }) =>
                `${formatDate(row.original.period_start)} → ${formatDate(row.original.period_end)}`,
        },
        {
            id: 'branch',
            header: () => <span>Branch</span>,
            cell: ({ row }) => row.original.branch?.name ?? 'All branches',
        },
        {
            accessorKey: 'employee_count',
            header: () => <span>Employees</span>,
        },
        {
            accessorKey: 'net_total',
            header: () => <span className="block text-right">Net pay</span>,
            cell: ({ row }) => (
                <span className="block text-right font-medium tabular-nums">
                    {formatAmount(row.original.net_total)}
                </span>
            ),
        },
        {
            accessorKey: 'status',
            header: () => <span>Status</span>,
            cell: ({ row }) => (
                <PayrollStatusBadge status={row.original.status} />
            ),
        },
        {
            id: 'by',
            header: () => <span>Approved</span>,
            cell: ({ row }) => (
                <span className="text-xs text-muted-foreground">
                    {row.original.approver
                        ? `${row.original.approver.name}, ${formatDate(row.original.approved_at?.slice(0, 10))}`
                        : '—'}
                </span>
            ),
        },
    ];

    return (
        <>
            <Head title="Payroll" />
            <div className="flex h-full flex-1 flex-col gap-4 overflow-x-auto rounded-xl p-4">
                <Heading
                    title="Payroll"
                    description="Monthly salary adjusted for absences, lates, unpaid leave and overtime, plus bonuses and deductions."
                />
                <DataTable
                    columns={columns}
                    data={runs.data}
                    meta={runs}
                    onPageChange={(page) =>
                        router.reload({ only: ['runs'], data: { page } })
                    }
                    emptyMessage="No payroll runs yet."
                    emptyDescription="Create a draft for last month to see everyone's pay."
                    toolbar={
                        <DataTableToolbar>
                            <div className="flex-1" />
                            {canRun && (
                                <Button
                                    size="sm"
                                    onClick={() => setIsCreating(true)}
                                >
                                    <Plus className="mr-2 size-4" /> New Payroll
                                    Run
                                </Button>
                            )}
                        </DataTableToolbar>
                    }
                />
            </div>
            {isCreating && (
                <NewRunDialog
                    branches={branches}
                    defaultPeriod={defaultPeriod}
                    onClose={() => setIsCreating(false)}
                />
            )}
        </>
    );
}

PayrollIndex.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard().url },
        { title: 'Payroll', href: index().url },
    ],
};
