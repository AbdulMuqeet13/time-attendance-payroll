import { Head, router } from '@inertiajs/react';
import { Plus } from 'lucide-react';
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
import { AdvanceDialog } from '@/components/payroll/advance-dialog';
import type { BadgeTone } from '@/components/status-badge';
import { StatusBadge, headline } from '@/components/status-badge';
import { Button } from '@/components/ui/button';
import { useDataTable } from '@/hooks/use-data-table';
import { formatDate, toIsoDate } from '@/lib/dates';
import { formatAmount } from '@/lib/utils';
import type { EmployeeOption, SalaryAdvance } from '@/types';
import {
    cancel,
    index,
    repay,
} from '@/actions/App/Http/Controllers/Payroll/SalaryAdvanceController';
import { dashboard } from '@/routes';

type AdvancesPageProps = {
    advances: SalaryAdvance[];
    employees?: EmployeeOption[];
    statuses: SelectOption[];
    canManage: boolean;
};

const tones: Record<SalaryAdvance['status'], BadgeTone> = {
    active: 'info',
    settled: 'success',
    cancelled: 'neutral',
};

export default function Advances({
    advances,
    employees = [],
    statuses,
    canManage,
}: AdvancesPageProps) {
    const table = useDataTable({ only: ['advances'] });
    const [isCreating, setIsCreating] = useState(false);
    const [cancelling, setCancelling] = useState<SalaryAdvance | null>(null);

    const recordRepayment = (advance: SalaryAdvance) => {
        const amount = window.prompt(
            `Cash repayment from ${advance.employee.name} (up to ${formatAmount(advance.remaining)}):`,
        );

        if (amount) {
            router.post(
                repay(advance).url,
                {
                    amount,
                    recovered_on: toIsoDate(new Date()),
                    note: 'Repaid outside payroll',
                },
                { preserveScroll: true },
            );
        }
    };

    const columns: TableColumn<SalaryAdvance>[] = [
        {
            id: 'employee',
            header: () => <span>Employee</span>,
            cell: ({ row }) => (
                <div>
                    <div className="font-medium">
                        {row.original.employee.name}
                    </div>
                    <div className="text-xs text-muted-foreground">
                        Given {formatDate(row.original.issued_on)}
                    </div>
                </div>
            ),
        },
        {
            id: 'amount',
            header: () => <span className="block text-right">Amount</span>,
            cell: ({ row }) => (
                <span className="block text-right tabular-nums">
                    {formatAmount(row.original.amount)}
                </span>
            ),
        },
        {
            id: 'installment',
            header: () => <span className="block text-right">Installment</span>,
            cell: ({ row }) => (
                <span className="block text-right tabular-nums">
                    {formatAmount(row.original.installment_amount)}
                    <div className="text-xs text-muted-foreground">
                        from {formatDate(row.original.start_period).slice(3)}
                    </div>
                </span>
            ),
        },
        {
            id: 'remaining',
            header: () => <span className="block text-right">Remaining</span>,
            cell: ({ row }) => (
                <span className="block text-right font-semibold tabular-nums">
                    {formatAmount(row.original.remaining)}
                </span>
            ),
        },
        {
            id: 'recoveries',
            header: () => <span>Recovered</span>,
            cell: ({ row }) => (
                <div className="max-w-56 text-xs text-muted-foreground">
                    {row.original.recoveries.length === 0
                        ? '—'
                        : row.original.recoveries
                              .slice(0, 3)
                              .map(
                                  (recovery) =>
                                      `${formatAmount(recovery.amount)} (${recovery.payslip?.payroll_run?.reference ?? 'manual'})`,
                              )
                              .join(', ')}
                </div>
            ),
        },
        {
            id: 'status',
            header: () => <span>Status</span>,
            cell: ({ row }) => (
                <StatusBadge tone={tones[row.original.status]}>
                    {headline(row.original.status)}
                </StatusBadge>
            ),
        },
        {
            id: 'actions',
            header: () => <span className="sr-only">Actions</span>,
            cell: ({ row }) =>
                canManage && row.original.status === 'active' ? (
                    <div className="flex justify-end gap-1">
                        <Button
                            size="sm"
                            variant="outline"
                            onClick={() => recordRepayment(row.original)}
                        >
                            Repayment
                        </Button>
                        <Button
                            size="sm"
                            variant="ghost"
                            onClick={() => setCancelling(row.original)}
                        >
                            Cancel
                        </Button>
                    </div>
                ) : null,
        },
    ];

    return (
        <>
            <Head title="Advances & Loans" />
            <div className="flex h-full flex-1 flex-col gap-4 overflow-x-auto rounded-xl p-4">
                <Heading
                    title="Advances & Loans"
                    description="Recovered automatically from each approved payroll."
                />
                <DataTable
                    columns={columns}
                    data={advances}
                    emptyMessage="No advances."
                    emptyDescription=""
                    toolbar={
                        <DataTableToolbar>
                            <div className="flex flex-1 flex-wrap items-center gap-2">
                                <DataTableSearch
                                    value={table.search}
                                    onChange={table.setSearch}
                                    placeholder="Employee..."
                                    className="w-full sm:w-56"
                                />
                                <OptionSelect
                                    size="sm"
                                    className="w-36"
                                    value={String(
                                        table.filters.status ?? 'active',
                                    )}
                                    onChange={(value) =>
                                        table.setFilter(
                                            'status',
                                            value === 'active'
                                                ? undefined
                                                : value,
                                        )
                                    }
                                    options={[
                                        ...statuses,
                                        { value: 'all', label: 'All' },
                                    ]}
                                />
                            </div>
                            {canManage && (
                                <Button
                                    size="sm"
                                    onClick={() => setIsCreating(true)}
                                >
                                    <Plus className="mr-2 size-4" /> Record
                                    Advance
                                </Button>
                            )}
                        </DataTableToolbar>
                    }
                />
            </div>
            {isCreating && (
                <AdvanceDialog
                    employees={employees}
                    onClose={() => setIsCreating(false)}
                />
            )}
            {cancelling && (
                <ConfirmDialog
                    open
                    onClose={() => setCancelling(null)}
                    title="Cancel Advance"
                    description={`Stop recovering ${cancelling.employee.name}'s advance? ${formatAmount(cancelling.remaining)} is still outstanding.`}
                    url={cancel(cancelling).url}
                    method="post"
                    confirmLabel="Cancel Advance"
                />
            )}
        </>
    );
}

Advances.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard().url },
        { title: 'Advances & Loans', href: index().url },
    ],
};
