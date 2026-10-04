import { Head, Link } from '@inertiajs/react';
import { Check, Paperclip, Plus, Undo2, X } from 'lucide-react';
import { useMemo, useState } from 'react';
import type { TableColumn } from '@/components/data-table';
import {
    DataTable,
    DataTableSearch,
    DataTableToolbar,
} from '@/components/data-table';
import Heading from '@/components/heading';
import { LeaveDecisionDialog } from '@/components/leaves/leave-decision-dialog';
import { LeaveRequestDialog } from '@/components/leaves/leave-request-dialog';
import { LeaveStatusBadge } from '@/components/leaves/leave-status-badge';
import type { SelectOption } from '@/components/option-select';
import { OptionSelect, toOptions } from '@/components/option-select';
import { StatusBadge } from '@/components/status-badge';
import { Button } from '@/components/ui/button';
import { useDataTable } from '@/hooks/use-data-table';
import { formatDate } from '@/lib/dates';
import type {
    EmployeeOption,
    LeaveRequest,
    LeaveType,
    Option,
    Paginated,
} from '@/types';
import { index as balances } from '@/actions/App/Http/Controllers/Leaves/LeaveBalanceController';
import {
    attachment,
    index,
} from '@/actions/App/Http/Controllers/Leaves/LeaveRequestController';
import { dashboard } from '@/routes';

type LeavesPageProps = {
    requests: Paginated<LeaveRequest>;
    counts: Record<string, number>;
    leaveTypes: LeaveType[];
    branches: Option[];
    statuses: SelectOption[];
    employees?: EmployeeOption[];
    can: { manage: boolean; approve: boolean };
};

export default function Leaves({
    requests,
    counts,
    leaveTypes,
    branches,
    statuses,
    employees = [],
    can,
}: LeavesPageProps) {
    const table = useDataTable({
        only: ['requests', 'counts'],
        defaultPerPage: 25,
    });
    const [isCreating, setIsCreating] = useState(false);
    const [deciding, setDeciding] = useState<{
        request: LeaveRequest;
        decision: 'approve' | 'reject' | 'cancel';
    } | null>(null);

    const columns = useMemo<TableColumn<LeaveRequest>[]>(() => {
        const list: TableColumn<LeaveRequest>[] = [
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
                id: 'type',
                header: () => <span>Type</span>,
                cell: ({ row }) => (
                    <span>
                        {row.original.leave_type.name}
                        {!row.original.leave_type.is_paid && (
                            <StatusBadge tone="neutral" className="ml-2">
                                Unpaid
                            </StatusBadge>
                        )}
                    </span>
                ),
            },
            {
                id: 'period',
                header: () => <span>Period</span>,
                cell: ({ row }) => (
                    <div className="text-sm">
                        <div>
                            {formatDate(row.original.start_date)}
                            {row.original.end_date !==
                                row.original.start_date &&
                                ` → ${formatDate(row.original.end_date)}`}
                        </div>
                        <div className="text-xs text-muted-foreground">
                            {row.original.is_half_day
                                ? 'Half day'
                                : `${Number(row.original.days)} day(s)`}
                        </div>
                    </div>
                ),
            },
            {
                id: 'reason',
                header: () => <span>Reason</span>,
                cell: ({ row }) => (
                    <div className="max-w-56 text-sm">
                        <p className="truncate">{row.original.reason ?? '—'}</p>
                        {row.original.attachment_path && (
                            <a
                                href={attachment(row.original).url}
                                className="inline-flex items-center gap-1 text-xs text-primary hover:underline"
                            >
                                <Paperclip className="size-3" /> Document
                            </a>
                        )}
                    </div>
                ),
            },
            {
                id: 'status',
                header: () => <span>Status</span>,
                cell: ({ row }) => (
                    <div>
                        <LeaveStatusBadge status={row.original.status} />
                        {row.original.decision_note && (
                            <p className="mt-1 max-w-48 truncate text-xs text-muted-foreground">
                                {row.original.decision_note}
                            </p>
                        )}
                    </div>
                ),
            },
        ];

        if (can.approve) {
            list.push({
                id: 'actions',
                header: () => <span className="sr-only">Actions</span>,
                cell: ({ row }) => {
                    const request = row.original;

                    return (
                        <div className="flex justify-end gap-1">
                            {request.status === 'pending' && (
                                <>
                                    <Button
                                        size="sm"
                                        onClick={() =>
                                            setDeciding({
                                                request,
                                                decision: 'approve',
                                            })
                                        }
                                    >
                                        <Check className="mr-1 size-3.5" />{' '}
                                        Approve
                                    </Button>
                                    <Button
                                        size="sm"
                                        variant="outline"
                                        onClick={() =>
                                            setDeciding({
                                                request,
                                                decision: 'reject',
                                            })
                                        }
                                    >
                                        <X className="mr-1 size-3.5" /> Reject
                                    </Button>
                                </>
                            )}
                            {request.status === 'approved' && (
                                <Button
                                    size="sm"
                                    variant="ghost"
                                    onClick={() =>
                                        setDeciding({
                                            request,
                                            decision: 'cancel',
                                        })
                                    }
                                >
                                    <Undo2 className="mr-1 size-3.5" /> Cancel
                                </Button>
                            )}
                        </div>
                    );
                },
            });
        }

        return list;
    }, [can.approve]);

    const currentStatus = String(table.filters.status ?? 'pending');

    return (
        <>
            <Head title="Leave" />

            <div className="flex h-full flex-1 flex-col gap-4 overflow-x-auto rounded-xl p-4">
                <div className="flex flex-wrap items-start justify-between gap-4">
                    <Heading
                        title="Leave"
                        description="Requests, approvals and cancellations. Approved leave updates attendance."
                    />
                    <div className="flex gap-2">
                        <Button size="sm" variant="outline" asChild>
                            <Link href={balances().url}>Balances</Link>
                        </Button>
                        {can.manage && (
                            <Button
                                size="sm"
                                onClick={() => setIsCreating(true)}
                            >
                                <Plus className="mr-2 size-4" /> Record Leave
                            </Button>
                        )}
                    </div>
                </div>

                <DataTable
                    columns={columns}
                    data={requests.data}
                    meta={requests}
                    onPageChange={table.setPage}
                    onPerPageChange={table.setPerPage}
                    emptyMessage={
                        currentStatus === 'pending'
                            ? 'Nothing waiting for approval.'
                            : 'No leave requests found.'
                    }
                    emptyDescription=""
                    toolbar={
                        <DataTableToolbar>
                            <div className="flex flex-1 flex-wrap items-center gap-2">
                                <OptionSelect
                                    size="sm"
                                    className="w-44"
                                    value={currentStatus}
                                    onChange={(value) =>
                                        table.setFilter(
                                            'status',
                                            value === 'pending'
                                                ? undefined
                                                : value,
                                        )
                                    }
                                    options={[
                                        ...statuses.map((status) => ({
                                            value: status.value,
                                            label: `${status.label} (${counts[status.value] ?? 0})`,
                                        })),
                                        { value: 'all', label: 'All' },
                                    ]}
                                />
                                <DataTableSearch
                                    value={table.search}
                                    onChange={table.setSearch}
                                    placeholder="Employee..."
                                    className="w-full sm:w-56"
                                />
                                <OptionSelect
                                    size="sm"
                                    className="w-44"
                                    value={String(
                                        table.filters.leave_type_id ?? '',
                                    )}
                                    onChange={(value) =>
                                        table.setFilter(
                                            'leave_type_id',
                                            value || undefined,
                                        )
                                    }
                                    options={leaveTypes.map((type) => ({
                                        value: String(type.id),
                                        label: type.name,
                                    }))}
                                    noneLabel="All types"
                                    placeholder="All types"
                                />
                                {branches.length > 1 && (
                                    <OptionSelect
                                        size="sm"
                                        className="w-40"
                                        value={String(
                                            table.filters.branch_id ?? '',
                                        )}
                                        onChange={(value) =>
                                            table.setFilter(
                                                'branch_id',
                                                value || undefined,
                                            )
                                        }
                                        options={toOptions(branches)}
                                        noneLabel="All branches"
                                        placeholder="All branches"
                                    />
                                )}
                            </div>
                        </DataTableToolbar>
                    }
                />
            </div>

            {isCreating && (
                <LeaveRequestDialog
                    leaveTypes={leaveTypes}
                    employees={employees}
                    canApprove={can.approve}
                    onClose={() => setIsCreating(false)}
                />
            )}
            {deciding && (
                <LeaveDecisionDialog
                    request={deciding.request}
                    decision={deciding.decision}
                    onClose={() => setDeciding(null)}
                />
            )}
        </>
    );
}

Leaves.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard().url },
        { title: 'Leave', href: index().url },
    ],
};
