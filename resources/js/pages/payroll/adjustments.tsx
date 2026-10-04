import { Head } from '@inertiajs/react';
import { ChevronLeft, ChevronRight, Plus, Trash2 } from 'lucide-react';
import { useState } from 'react';
import { ConfirmDialog } from '@/components/confirm-dialog';
import type { TableColumn } from '@/components/data-table';
import {
    DataTable,
    DataTableSearch,
    DataTableToolbar,
} from '@/components/data-table';
import Heading from '@/components/heading';
import type { SelectOption } from '@/components/option-select';
import { OptionSelect } from '@/components/option-select';
import { AdjustmentDialog } from '@/components/payroll/adjustment-dialog';
import { StatCard } from '@/components/stat-card';
import { StatusBadge, headline } from '@/components/status-badge';
import { Button } from '@/components/ui/button';
import { useDataTable } from '@/hooks/use-data-table';
import { formatAmount } from '@/lib/utils';
import type { EmployeeOption, Option, PayrollAdjustment } from '@/types';
import {
    destroy,
    index,
} from '@/actions/App/Http/Controllers/Payroll/PayrollAdjustmentController';
import { dashboard } from '@/routes';

type AdjustmentsPageProps = {
    month: string;
    adjustments: PayrollAdjustment[];
    totals: { earnings: string; deductions: string };
    kinds: SelectOption[];
    employees?: EmployeeOption[];
    branches: Option[];
    departments: Option[];
    canManage: boolean;
};

const EARNING_KINDS = ['bonus', 'allowance', 'commission', 'arrears'];

export default function Adjustments({
    month,
    adjustments,
    totals,
    kinds,
    employees = [],
    branches,
    departments,
    canManage,
}: AdjustmentsPageProps) {
    const table = useDataTable({ only: ['adjustments', 'totals', 'month'] });
    const [isCreating, setIsCreating] = useState(false);
    const [deleting, setDeleting] = useState<PayrollAdjustment | null>(null);

    const goToMonth = (offset: number) => {
        const [year, monthNumber] = month.split('-').map(Number);
        const target = new Date(year, monthNumber - 1 + offset, 1);
        table.setFilter(
            'month',
            `${target.getFullYear()}-${String(target.getMonth() + 1).padStart(2, '0')}`,
        );
    };

    const columns: TableColumn<PayrollAdjustment>[] = [
        {
            id: 'employee',
            header: () => <span>Employee</span>,
            cell: ({ row }) => (
                <div>
                    <div className="font-medium">
                        {row.original.employee.name}
                    </div>
                    <div className="text-xs text-muted-foreground">
                        {row.original.employee.employee_code}
                    </div>
                </div>
            ),
        },
        {
            id: 'kind',
            header: () => <span>Type</span>,
            cell: ({ row }) => (
                <StatusBadge
                    tone={
                        EARNING_KINDS.includes(row.original.kind)
                            ? 'success'
                            : 'warning'
                    }
                >
                    {headline(row.original.kind)}
                </StatusBadge>
            ),
        },
        { accessorKey: 'name', header: () => <span>Description</span> },
        {
            id: 'amount',
            header: () => <span className="block text-right">Amount</span>,
            cell: ({ row }) => (
                <span className="block text-right font-medium tabular-nums">
                    {EARNING_KINDS.includes(row.original.kind) ? '+' : '−'}
                    {formatAmount(row.original.amount)}
                </span>
            ),
        },
        {
            id: 'run',
            header: () => <span>Paid in</span>,
            cell: ({ row }) =>
                row.original.payroll_run?.reference ?? (
                    <span className="text-xs text-muted-foreground">
                        Next payroll
                    </span>
                ),
        },
        {
            id: 'actions',
            header: () => <span className="sr-only">Actions</span>,
            cell: ({ row }) =>
                canManage && !row.original.payroll_run_id ? (
                    <Button
                        variant="ghost"
                        size="icon"
                        className="size-8"
                        onClick={() => setDeleting(row.original)}
                    >
                        <Trash2 className="size-4" />
                        <span className="sr-only">Remove</span>
                    </Button>
                ) : null,
        },
    ];

    const monthLabel = new Date(`${month}-01T00:00:00`).toLocaleDateString(
        'en-GB',
        { month: 'long', year: 'numeric' },
    );

    return (
        <>
            <Head title="Bonuses & Deductions" />
            <div className="flex h-full flex-1 flex-col gap-4 overflow-x-auto rounded-xl p-4">
                <Heading
                    title="Bonuses & Deductions"
                    description="One-off amounts added to or taken from a month's pay."
                />
                <div className="grid gap-4 sm:grid-cols-2">
                    <StatCard
                        label={`Additions in ${monthLabel}`}
                        value={formatAmount(totals.earnings)}
                    />
                    <StatCard
                        label={`Deductions in ${monthLabel}`}
                        value={formatAmount(totals.deductions)}
                    />
                </div>
                <DataTable
                    columns={columns}
                    data={adjustments}
                    emptyMessage={`Nothing for ${monthLabel}.`}
                    emptyDescription=""
                    toolbar={
                        <DataTableToolbar>
                            <div className="flex flex-1 flex-wrap items-center gap-2">
                                <div className="flex items-center gap-1">
                                    <Button
                                        variant="outline"
                                        size="icon"
                                        className="size-8"
                                        onClick={() => goToMonth(-1)}
                                    >
                                        <ChevronLeft className="size-4" />
                                        <span className="sr-only">
                                            Previous month
                                        </span>
                                    </Button>
                                    <span className="w-36 text-center font-semibold">
                                        {monthLabel}
                                    </span>
                                    <Button
                                        variant="outline"
                                        size="icon"
                                        className="size-8"
                                        onClick={() => goToMonth(1)}
                                    >
                                        <ChevronRight className="size-4" />
                                        <span className="sr-only">
                                            Next month
                                        </span>
                                    </Button>
                                </div>
                                <DataTableSearch
                                    value={table.search}
                                    onChange={table.setSearch}
                                    placeholder="Employee..."
                                    className="w-full sm:w-56"
                                />
                                <OptionSelect
                                    size="sm"
                                    className="w-40"
                                    value={String(table.filters.kind ?? '')}
                                    onChange={(value) =>
                                        table.setFilter(
                                            'kind',
                                            value || undefined,
                                        )
                                    }
                                    options={kinds}
                                    noneLabel="All types"
                                    placeholder="All types"
                                />
                            </div>
                            {canManage && (
                                <Button
                                    size="sm"
                                    onClick={() => setIsCreating(true)}
                                >
                                    <Plus className="mr-2 size-4" /> Add
                                </Button>
                            )}
                        </DataTableToolbar>
                    }
                />
            </div>
            {isCreating && (
                <AdjustmentDialog
                    month={month}
                    kinds={kinds}
                    employees={employees}
                    branches={branches}
                    departments={departments}
                    onClose={() => setIsCreating(false)}
                />
            )}
            {deleting && (
                <ConfirmDialog
                    open
                    onClose={() => setDeleting(null)}
                    title="Remove Adjustment"
                    description={`Remove ${deleting.name} (${formatAmount(deleting.amount)}) for ${deleting.employee.name}?`}
                    url={destroy(deleting).url}
                    confirmLabel="Remove"
                />
            )}
        </>
    );
}

Adjustments.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard().url },
        { title: 'Bonuses & Deductions', href: index().url },
    ],
};
