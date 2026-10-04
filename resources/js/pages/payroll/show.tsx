import { Head } from '@inertiajs/react';
import {
    AlertTriangle,
    CheckCircle2,
    Download,
    FileSpreadsheet,
    RefreshCw,
    Undo2,
    Wallet,
    XCircle,
} from 'lucide-react';
import { useMemo, useState } from 'react';
import { ConfirmDialog } from '@/components/confirm-dialog';
import type { TableColumn } from '@/components/data-table';
import { DataTable } from '@/components/data-table';
import { MarkPaidDialog } from '@/components/payroll/mark-paid-dialog';
import { PayrollStatusBadge } from '@/components/payroll/payroll-status-badge';
import { PayslipSheet } from '@/components/payroll/payslip-sheet';
import { StatCard } from '@/components/stat-card';
import { StatusBadge } from '@/components/status-badge';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import { formatDate } from '@/lib/dates';
import { formatMinutes } from '@/lib/shifts';
import { formatAmount } from '@/lib/utils';
import type { PayrollRun, Payslip } from '@/types';
import {
    approve,
    bankSheet,
    cancel,
    index,
    regenerate,
    register,
    revert,
} from '@/actions/App/Http/Controllers/Payroll/PayrollRunController';
import { dashboard } from '@/routes';

type PayrollShowProps = {
    run: PayrollRun;
    payslips: Payslip[];
    summary: { shortfalls: number; unpaid: number; bank: string; cash: string };
    can: { run: boolean; approve: boolean; pay: boolean; revert: boolean };
};

type PendingAction = 'regenerate' | 'approve' | 'revert' | 'cancel';

const ACTIONS: Record<
    PendingAction,
    { title: string; description: string; label: string; destructive: boolean }
> = {
    regenerate: {
        title: 'Regenerate Payslips',
        description:
            'Recalculates attendance and rebuilds every payslip with the latest salaries, adjustments and advances.',
        label: 'Regenerate',
        destructive: false,
    },
    approve: {
        title: 'Approve Payroll',
        description:
            'Locks the attendance of the period, marks the bonuses and deductions as paid and takes the advance installments. Only a super admin can undo this.',
        label: 'Approve',
        destructive: false,
    },
    revert: {
        title: 'Back to Draft',
        description:
            'Unlocks attendance and returns adjustments and installments so the run can be regenerated.',
        label: 'Back to Draft',
        destructive: true,
    },
    cancel: {
        title: 'Cancel Payroll Run',
        description:
            'Deletes the draft payslips. The period can then be run again.',
        label: 'Cancel Run',
        destructive: true,
    },
};

export default function PayrollShow({
    run,
    payslips,
    summary,
    can,
}: PayrollShowProps) {
    const [selected, setSelected] = useState<Payslip | null>(null);
    const [pending, setPending] = useState<PendingAction | null>(null);
    const [paying, setPaying] = useState<{
        ids?: number[];
        label: string;
    } | null>(null);
    const isDraft = run.status === 'draft';
    const isApproved = run.status === 'approved';

    const urls: Record<PendingAction, string> = {
        regenerate: regenerate(run).url,
        approve: approve(run).url,
        revert: revert(run).url,
        cancel: cancel(run).url,
    };

    const columns = useMemo<TableColumn<Payslip>[]>(
        () => [
            {
                id: 'employee',
                header: () => <span>Employee</span>,
                cell: ({ row }) => (
                    <button
                        type="button"
                        className="text-left"
                        onClick={() => setSelected(row.original)}
                    >
                        <div className="font-medium hover:underline">
                            {row.original.employee_name}
                        </div>
                        <div className="text-xs text-muted-foreground">
                            {row.original.employee_code} ·{' '}
                            {row.original.department ?? '—'}
                        </div>
                    </button>
                ),
            },
            {
                id: 'gross',
                header: () => <span className="block text-right">Salary</span>,
                cell: ({ row }) => (
                    <span className="block text-right tabular-nums">
                        {formatAmount(row.original.monthly_gross)}
                    </span>
                ),
            },
            {
                id: 'attendance',
                header: () => <span>Attendance</span>,
                cell: ({ row }) => (
                    <span className="text-xs">
                        P {Number(row.original.present_days)} · A{' '}
                        {Number(row.original.absent_days)} · H{' '}
                        {Number(row.original.half_days)} · L{' '}
                        {row.original.late_count}
                        {row.original.overtime_minutes +
                            row.original.holiday_overtime_minutes >
                            0 &&
                            ` · OT ${formatMinutes(row.original.overtime_minutes + row.original.holiday_overtime_minutes)}`}
                    </span>
                ),
            },
            {
                id: 'earnings',
                header: () => (
                    <span className="block text-right">Earnings</span>
                ),
                cell: ({ row }) => (
                    <span className="block text-right tabular-nums">
                        {formatAmount(row.original.earnings_total)}
                    </span>
                ),
            },
            {
                id: 'deductions',
                header: () => (
                    <span className="block text-right">Deductions</span>
                ),
                cell: ({ row }) => (
                    <span className="block text-right tabular-nums">
                        {formatAmount(row.original.deductions_total)}
                    </span>
                ),
            },
            {
                id: 'net',
                header: () => <span className="block text-right">Net pay</span>,
                cell: ({ row }) => (
                    <span className="block text-right font-semibold tabular-nums">
                        {formatAmount(row.original.net_pay)}
                        {Number(row.original.shortfall) > 0 && (
                            <AlertTriangle
                                className="ml-1 inline size-3.5 text-red-600"
                                aria-label="Shortfall"
                            />
                        )}
                    </span>
                ),
            },
            {
                id: 'payment',
                header: () => <span>Payment</span>,
                cell: ({ row }) =>
                    row.original.payment_status === 'paid' ? (
                        <StatusBadge tone="success">Paid</StatusBadge>
                    ) : can.pay &&
                      (run.status === 'approved' || run.status === 'paid') ? (
                        <Button
                            size="sm"
                            variant="outline"
                            onClick={() =>
                                setPaying({
                                    ids: [row.original.id],
                                    label: `Pay ${row.original.employee_name} ${formatAmount(row.original.net_pay)}.`,
                                })
                            }
                        >
                            Mark paid
                        </Button>
                    ) : (
                        <span className="text-xs text-muted-foreground">
                            {row.original.payment_method === 'bank'
                                ? 'Bank'
                                : 'Cash'}
                        </span>
                    ),
            },
        ],
        [can.pay, run.status],
    );

    return (
        <>
            <Head title={run.reference} />
            <div className="flex h-full flex-1 flex-col gap-4 overflow-x-auto rounded-xl p-4">
                <div className="flex flex-wrap items-start justify-between gap-4">
                    <div>
                        <div className="flex items-center gap-3">
                            <h1 className="font-mono text-xl font-semibold">
                                {run.reference}
                            </h1>
                            <PayrollStatusBadge status={run.status} />
                        </div>
                        <p className="text-sm text-muted-foreground">
                            {formatDate(run.period_start)} →{' '}
                            {formatDate(run.period_end)} ·{' '}
                            {run.branch?.name ?? 'All branches'}
                            {run.notes && ` · ${run.notes}`}
                        </p>
                    </div>
                    <div className="flex flex-wrap gap-2">
                        <Button variant="outline" size="sm" asChild>
                            <a href={register(run).url}>
                                <FileSpreadsheet className="mr-2 size-4" />{' '}
                                Register
                            </a>
                        </Button>
                        <Button variant="outline" size="sm" asChild>
                            <a href={bankSheet(run).url}>
                                <Download className="mr-2 size-4" /> Bank sheet
                            </a>
                        </Button>
                        {isDraft && can.run && (
                            <>
                                <Button
                                    variant="outline"
                                    size="sm"
                                    onClick={() => setPending('regenerate')}
                                >
                                    <RefreshCw className="mr-2 size-4" />{' '}
                                    Regenerate
                                </Button>
                                <Button
                                    variant="outline"
                                    size="sm"
                                    onClick={() => setPending('cancel')}
                                >
                                    <XCircle className="mr-2 size-4" /> Cancel
                                </Button>
                            </>
                        )}
                        {isDraft && can.approve && (
                            <Button
                                size="sm"
                                onClick={() => setPending('approve')}
                            >
                                <CheckCircle2 className="mr-2 size-4" /> Approve
                            </Button>
                        )}
                        {isApproved && can.revert && (
                            <Button
                                variant="outline"
                                size="sm"
                                onClick={() => setPending('revert')}
                            >
                                <Undo2 className="mr-2 size-4" /> Back to draft
                            </Button>
                        )}
                        {(isApproved || run.status === 'paid') &&
                            can.pay &&
                            summary.unpaid > 0 && (
                                <Button
                                    size="sm"
                                    onClick={() =>
                                        setPaying({
                                            label: `Mark all ${summary.unpaid} unpaid payslips as paid.`,
                                        })
                                    }
                                >
                                    <Wallet className="mr-2 size-4" /> Mark all
                                    paid
                                </Button>
                            )}
                    </div>
                </div>

                <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                    <StatCard
                        label="Employees"
                        value={run.employee_count}
                        hint={`${summary.unpaid} unpaid`}
                    />
                    <StatCard
                        label="Earnings"
                        value={formatAmount(run.earnings_total)}
                    />
                    <StatCard
                        label="Deductions"
                        value={formatAmount(run.deductions_total)}
                    />
                    <StatCard
                        label="Net pay"
                        value={formatAmount(run.net_total)}
                        hint={`Bank ${formatAmount(summary.bank)} · Cash ${formatAmount(summary.cash)}`}
                    />
                </div>

                {summary.shortfalls > 0 && (
                    <Alert variant="destructive">
                        <AlertTriangle className="size-4" />
                        <AlertTitle>
                            {summary.shortfalls} payslip(s) have deductions
                            larger than earnings
                        </AlertTitle>
                        <AlertDescription>
                            <p>
                                Their net pay is zero. Review fines and
                                deductions before approving.
                            </p>
                        </AlertDescription>
                    </Alert>
                )}

                <DataTable
                    columns={columns}
                    data={payslips}
                    emptyMessage="No payslips."
                    emptyDescription=""
                />
            </div>

            {selected && (
                <PayslipSheet
                    run={run}
                    payslip={selected}
                    onClose={() => setSelected(null)}
                />
            )}
            {pending && (
                <ConfirmDialog
                    open
                    onClose={() => setPending(null)}
                    title={ACTIONS[pending].title}
                    description={ACTIONS[pending].description}
                    url={urls[pending]}
                    method="post"
                    confirmLabel={ACTIONS[pending].label}
                    destructive={ACTIONS[pending].destructive}
                />
            )}
            {paying && (
                <MarkPaidDialog
                    run={run}
                    payslipIds={paying.ids}
                    label={paying.label}
                    onClose={() => setPaying(null)}
                />
            )}
        </>
    );
}

PayrollShow.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard().url },
        { title: 'Payroll', href: index().url },
        { title: 'Run', href: index().url },
    ],
};
