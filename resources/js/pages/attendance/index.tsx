import { Head, usePoll } from '@inertiajs/react';
import { ChevronLeft, ChevronRight, Plus, RefreshCw } from 'lucide-react';
import { useMemo, useState } from 'react';
import { AttendanceStatusBadge } from '@/components/attendance/attendance-status-badge';
import { DayDetailSheet } from '@/components/attendance/day-detail-sheet';
import { ManualPunchDialog } from '@/components/attendance/manual-punch-dialog';
import { RebuildDialog } from '@/components/attendance/rebuild-dialog';
import type { TableColumn } from '@/components/data-table';
import {
    DataTable,
    DataTableSearch,
    DataTableToolbar,
} from '@/components/data-table';
import { DatePicker } from '@/components/date-picker';
import Heading from '@/components/heading';
import type { SelectOption } from '@/components/option-select';
import { OptionSelect, toOptions } from '@/components/option-select';
import { StatusBadge, headline } from '@/components/status-badge';
import { Button } from '@/components/ui/button';
import { useDataTable } from '@/hooks/use-data-table';
import { parseIsoDate, toIsoDate } from '@/lib/dates';
import { formatMinutes } from '@/lib/shifts';
import { formatTime } from '@/lib/time';
import { cn } from '@/lib/utils';
import type { AttendanceDay, EmployeeOption, Option } from '@/types';
import { index } from '@/actions/App/Http/Controllers/Attendance/AttendanceController';
import { dashboard } from '@/routes';

type AttendancePageProps = {
    date: string;
    days: AttendanceDay[];
    summary: Record<string, number>;
    branches: Option[];
    departments: Option[];
    statuses: SelectOption[];
    employees?: EmployeeOption[];
    canManage: boolean;
};

const SUMMARY_ORDER = [
    'present',
    'late',
    'half_day',
    'absent',
    'leave',
    'scheduled',
    'holiday',
    'weekly_off',
];

export default function AttendanceIndex({
    date,
    days,
    summary,
    branches,
    departments,
    statuses,
    employees = [],
    canManage,
}: AttendancePageProps) {
    const table = useDataTable({ only: ['days', 'summary', 'date'] });
    const isToday = date === toIsoDate(new Date());

    usePoll(30000, { only: ['days', 'summary'] }, { autoStart: isToday });

    const [selected, setSelected] = useState<AttendanceDay | null>(null);
    const [punchFor, setPunchFor] = useState<{ employeeId?: number } | null>(
        null,
    );
    const [isRebuilding, setIsRebuilding] = useState(false);

    const goTo = (value: string) => table.setFilter('date', value || undefined);
    const shift = (days: number) => {
        const current = parseIsoDate(date) ?? new Date();
        current.setDate(current.getDate() + days);
        goTo(toIsoDate(current));
    };

    const columns = useMemo<TableColumn<AttendanceDay>[]>(
        () => [
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
                            {row.original.employee.department &&
                                ` · ${row.original.employee.department.name}`}
                        </div>
                    </div>
                ),
            },
            {
                id: 'shift',
                header: () => <span>Shift</span>,
                cell: ({ row }) =>
                    row.original.shift ? (
                        <span className="text-sm">
                            {row.original.shift.name}{' '}
                            <span className="font-mono text-xs text-muted-foreground">
                                {row.original.shift.start_time}–
                                {row.original.shift.end_time}
                            </span>
                        </span>
                    ) : (
                        <span className="text-sm text-muted-foreground">
                            {headline(row.original.day_type)}
                        </span>
                    ),
            },
            {
                id: 'in-out',
                header: () => <span>In / Out</span>,
                cell: ({ row }) => (
                    <span className="font-mono text-sm">
                        {formatTime(row.original.first_in, row.original.date)} –{' '}
                        {formatTime(row.original.last_out, row.original.date)}
                        {(row.original.sessions?.length ?? 0) > 1 && (
                            <span className="ml-1 font-sans text-xs text-muted-foreground">
                                ({row.original.sessions?.length} sessions)
                            </span>
                        )}
                    </span>
                ),
            },
            {
                id: 'worked',
                header: () => <span>Worked</span>,
                cell: ({ row }) => formatMinutes(row.original.worked_minutes),
            },
            {
                id: 'late',
                header: () => <span>Late / Early</span>,
                cell: ({ row }) => (
                    <span className="text-sm">
                        {row.original.late_minutes > 0
                            ? formatMinutes(row.original.late_minutes)
                            : '—'}{' '}
                        /{' '}
                        {row.original.early_leave_minutes > 0
                            ? formatMinutes(row.original.early_leave_minutes)
                            : '—'}
                    </span>
                ),
            },
            {
                id: 'overtime',
                header: () => <span>Overtime</span>,
                cell: ({ row }) =>
                    row.original.overtime_minutes > 0 ? (
                        <span className="text-sm">
                            {formatMinutes(row.original.overtime_minutes)}
                            {row.original.approved_overtime_minutes ===
                                null && (
                                <StatusBadge tone="warning" className="ml-1">
                                    Pending
                                </StatusBadge>
                            )}
                        </span>
                    ) : (
                        '—'
                    ),
            },
            {
                id: 'status',
                header: () => <span>Status</span>,
                cell: ({ row }) => (
                    <div className="flex flex-wrap gap-1">
                        <AttendanceStatusBadge status={row.original.status} />
                        {row.original.is_missing_checkout && (
                            <StatusBadge tone="danger">
                                No check-out
                            </StatusBadge>
                        )}
                        {row.original.is_overridden && (
                            <StatusBadge tone="neutral">Corrected</StatusBadge>
                        )}
                    </div>
                ),
            },
            {
                id: 'open',
                header: () => <span className="sr-only">Details</span>,
                cell: ({ row }) => (
                    <Button
                        variant="ghost"
                        size="sm"
                        onClick={() => setSelected(row.original)}
                    >
                        Details
                    </Button>
                ),
            },
        ],
        [],
    );

    return (
        <>
            <Head title="Attendance" />

            <div className="flex h-full flex-1 flex-col gap-4 overflow-x-auto rounded-xl p-4">
                <div className="flex flex-wrap items-start justify-between gap-4">
                    <Heading
                        title="Attendance"
                        description="Check-ins, check-outs, lates and overtime per employee and shift."
                    />
                    {canManage && (
                        <div className="flex gap-2">
                            <Button
                                size="sm"
                                variant="outline"
                                onClick={() => setIsRebuilding(true)}
                            >
                                <RefreshCw className="mr-2 size-4" />
                                Recalculate
                            </Button>
                            <Button size="sm" onClick={() => setPunchFor({})}>
                                <Plus className="mr-2 size-4" />
                                Add Punch
                            </Button>
                        </div>
                    )}
                </div>

                <div className="flex flex-wrap gap-2">
                    {SUMMARY_ORDER.filter((status) => summary[status]).map(
                        (status) => (
                            <button
                                key={status}
                                type="button"
                                onClick={() =>
                                    table.setFilter(
                                        'status',
                                        table.filters.status === status
                                            ? undefined
                                            : status,
                                    )
                                }
                                className={cn(
                                    'rounded-lg border px-3 py-1.5 text-left text-sm transition-colors',
                                    table.filters.status === status
                                        ? 'border-primary bg-primary/5'
                                        : 'hover:bg-muted',
                                )}
                            >
                                <span className="text-muted-foreground">
                                    {headline(status)}
                                </span>{' '}
                                <span className="font-semibold tabular-nums">
                                    {summary[status]}
                                </span>
                            </button>
                        ),
                    )}
                </div>

                <DataTable
                    columns={columns}
                    data={days}
                    emptyMessage="No attendance for this day yet."
                    emptyDescription="Days are calculated from scans every hour, or right away when a scan arrives."
                    toolbar={
                        <DataTableToolbar>
                            <div className="flex flex-1 flex-wrap items-center gap-2">
                                <div className="flex items-center gap-1">
                                    <Button
                                        variant="outline"
                                        size="icon"
                                        className="size-8"
                                        onClick={() => shift(-1)}
                                    >
                                        <ChevronLeft className="size-4" />
                                        <span className="sr-only">
                                            Previous day
                                        </span>
                                    </Button>
                                    <DatePicker
                                        size="sm"
                                        className="w-[150px]"
                                        value={date}
                                        onChange={goTo}
                                    />
                                    <Button
                                        variant="outline"
                                        size="icon"
                                        className="size-8"
                                        onClick={() => shift(1)}
                                        disabled={isToday}
                                    >
                                        <ChevronRight className="size-4" />
                                        <span className="sr-only">
                                            Next day
                                        </span>
                                    </Button>
                                </div>
                                <DataTableSearch
                                    value={table.search}
                                    onChange={table.setSearch}
                                    placeholder="Employee name or code..."
                                    className="w-full sm:w-60"
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
                                <OptionSelect
                                    size="sm"
                                    className="w-44"
                                    value={String(
                                        table.filters.department_id ?? '',
                                    )}
                                    onChange={(value) =>
                                        table.setFilter(
                                            'department_id',
                                            value || undefined,
                                        )
                                    }
                                    options={toOptions(departments)}
                                    noneLabel="All departments"
                                    placeholder="All departments"
                                />
                                <OptionSelect
                                    size="sm"
                                    className="w-36"
                                    value={String(table.filters.status ?? '')}
                                    onChange={(value) =>
                                        table.setFilter(
                                            'status',
                                            value || undefined,
                                        )
                                    }
                                    options={statuses}
                                    noneLabel="All statuses"
                                    placeholder="All statuses"
                                />
                            </div>
                        </DataTableToolbar>
                    }
                />
            </div>

            {selected && (
                <DayDetailSheet
                    day={selected}
                    canManage={canManage}
                    onClose={() => setSelected(null)}
                    onAddPunch={() => {
                        setPunchFor({ employeeId: selected.employee_id });
                        setSelected(null);
                    }}
                />
            )}

            {punchFor && (
                <ManualPunchDialog
                    employees={employees}
                    employeeId={punchFor.employeeId}
                    date={date}
                    onClose={() => setPunchFor(null)}
                />
            )}

            {isRebuilding && (
                <RebuildDialog
                    date={date}
                    onClose={() => setIsRebuilding(false)}
                />
            )}
        </>
    );
}

AttendanceIndex.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard().url },
        { title: 'Attendance', href: index().url },
    ],
};
