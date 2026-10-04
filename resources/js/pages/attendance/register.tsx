import { Head } from '@inertiajs/react';
import { ChevronLeft, ChevronRight } from 'lucide-react';
import { attendanceTones } from '@/components/attendance/attendance-status-badge';
import { DataTablePagination, DataTableSearch } from '@/components/data-table';
import Heading from '@/components/heading';
import { OptionSelect, toOptions } from '@/components/option-select';
import type { BadgeTone } from '@/components/status-badge';
import { headline } from '@/components/status-badge';
import { Button } from '@/components/ui/button';
import {
    Tooltip,
    TooltipContent,
    TooltipTrigger,
} from '@/components/ui/tooltip';
import { useDataTable } from '@/hooks/use-data-table';
import { formatMinutes } from '@/lib/shifts';
import { cn } from '@/lib/utils';
import type { AttendanceStatus, Option, PaginationMeta } from '@/types';
import { register } from '@/actions/App/Http/Controllers/Attendance/AttendanceController';
import { dashboard } from '@/routes';

type RegisterRow = {
    employee: { id: number; name: string; employee_code: string };
    cells: Record<
        string,
        { status: AttendanceStatus; missing_checkout: boolean }
    >;
    totals: {
        present: number;
        late: number;
        half_day: number;
        absent: number;
        leave: number;
        worked_minutes: number;
        overtime_minutes: number;
        pending_overtime_minutes: number;
    };
};

type RegisterPageProps = {
    month: string;
    dates: { date: string; day: string; weekday: string }[];
    rows: RegisterRow[];
    pagination: PaginationMeta;
    branches: Option[];
    departments: Option[];
};

const CODES: Record<AttendanceStatus, string> = {
    present: 'P',
    late: 'L',
    half_day: 'H',
    absent: 'A',
    leave: 'LV',
    holiday: 'HO',
    weekly_off: 'W',
    scheduled: '·',
    unscheduled: '–',
};

const CELL_CLASSES: Record<BadgeTone, string> = {
    success:
        'bg-emerald-100 text-emerald-800 dark:bg-emerald-950 dark:text-emerald-300',
    warning:
        'bg-amber-100 text-amber-800 dark:bg-amber-950 dark:text-amber-300',
    danger: 'bg-red-100 text-red-800 dark:bg-red-950 dark:text-red-300',
    info: 'bg-sky-100 text-sky-800 dark:bg-sky-950 dark:text-sky-300',
    neutral: 'text-muted-foreground',
};

export default function AttendanceRegister({
    month,
    dates,
    rows,
    pagination,
    branches,
    departments,
}: RegisterPageProps) {
    const table = useDataTable({
        only: ['rows', 'pagination', 'dates', 'month'],
        defaultPerPage: 25,
    });

    const goToMonth = (offset: number) => {
        const [year, monthNumber] = month.split('-').map(Number);
        const target = new Date(year, monthNumber - 1 + offset, 1);
        table.setFilter(
            'month',
            `${target.getFullYear()}-${String(target.getMonth() + 1).padStart(2, '0')}`,
        );
    };

    const monthLabel = new Date(`${month}-01T00:00:00`).toLocaleDateString(
        'en-GB',
        {
            month: 'long',
            year: 'numeric',
        },
    );

    return (
        <>
            <Head title="Attendance Register" />

            <div className="flex h-full flex-1 flex-col gap-4 overflow-x-auto rounded-xl p-4">
                <Heading
                    title="Attendance Register"
                    description="One status per day: P present, L late, H half day, A absent, LV leave, HO holiday, W weekly off."
                />

                <div className="flex flex-wrap items-center gap-2">
                    <div className="flex items-center gap-1">
                        <Button
                            variant="outline"
                            size="icon"
                            className="size-8"
                            onClick={() => goToMonth(-1)}
                        >
                            <ChevronLeft className="size-4" />
                            <span className="sr-only">Previous month</span>
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
                            <span className="sr-only">Next month</span>
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
                            value={String(table.filters.branch_id ?? '')}
                            onChange={(value) =>
                                table.setFilter('branch_id', value || undefined)
                            }
                            options={toOptions(branches)}
                            noneLabel="All branches"
                            placeholder="All branches"
                        />
                    )}
                    <OptionSelect
                        size="sm"
                        className="w-44"
                        value={String(table.filters.department_id ?? '')}
                        onChange={(value) =>
                            table.setFilter('department_id', value || undefined)
                        }
                        options={toOptions(departments)}
                        noneLabel="All departments"
                        placeholder="All departments"
                    />
                </div>

                <div className="overflow-x-auto rounded-md border">
                    <table className="w-full border-collapse text-xs">
                        <thead>
                            <tr className="bg-muted/50">
                                <th className="sticky left-0 z-10 min-w-48 border-b bg-background px-3 py-2 text-left font-medium">
                                    Employee
                                </th>
                                {dates.map((date) => (
                                    <th
                                        key={date.date}
                                        className="border-b px-1 py-2 text-center font-medium"
                                    >
                                        <div>{date.day}</div>
                                        <div className="font-normal text-muted-foreground">
                                            {date.weekday[0]}
                                        </div>
                                    </th>
                                ))}
                                {['P', 'L', 'H', 'A', 'LV', 'Worked', 'OT'].map(
                                    (label) => (
                                        <th
                                            key={label}
                                            className="border-b px-2 py-2 text-right font-medium"
                                        >
                                            {label}
                                        </th>
                                    ),
                                )}
                            </tr>
                        </thead>
                        <tbody>
                            {rows.length === 0 && (
                                <tr>
                                    <td
                                        colSpan={dates.length + 8}
                                        className="h-24 text-center text-muted-foreground"
                                    >
                                        No employees for this month.
                                    </td>
                                </tr>
                            )}
                            {rows.map((row) => (
                                <tr
                                    key={row.employee.id}
                                    className="hover:bg-muted/30"
                                >
                                    <td className="sticky left-0 z-10 border-b bg-background px-3 py-1.5">
                                        <div className="font-medium">
                                            {row.employee.name}
                                        </div>
                                        <div className="text-muted-foreground">
                                            {row.employee.employee_code}
                                        </div>
                                    </td>
                                    {dates.map((date) => {
                                        const cell = row.cells[date.date];

                                        if (!cell) {
                                            return (
                                                <td
                                                    key={date.date}
                                                    className="border-b"
                                                />
                                            );
                                        }

                                        return (
                                            <td
                                                key={date.date}
                                                className="border-b p-0.5 text-center"
                                            >
                                                <Tooltip>
                                                    <TooltipTrigger asChild>
                                                        <span
                                                            className={cn(
                                                                'inline-block min-w-6 rounded px-1 py-0.5 font-semibold',
                                                                CELL_CLASSES[
                                                                    attendanceTones[
                                                                        cell
                                                                            .status
                                                                    ]
                                                                ],
                                                                cell.missing_checkout &&
                                                                    'ring-1 ring-red-500',
                                                            )}
                                                        >
                                                            {CODES[cell.status]}
                                                        </span>
                                                    </TooltipTrigger>
                                                    <TooltipContent>
                                                        {headline(cell.status)}
                                                        {cell.missing_checkout &&
                                                            ' · no check-out'}
                                                    </TooltipContent>
                                                </Tooltip>
                                            </td>
                                        );
                                    })}
                                    <td className="border-b px-2 text-right tabular-nums">
                                        {row.totals.present}
                                    </td>
                                    <td className="border-b px-2 text-right tabular-nums">
                                        {row.totals.late}
                                    </td>
                                    <td className="border-b px-2 text-right tabular-nums">
                                        {row.totals.half_day}
                                    </td>
                                    <td className="border-b px-2 text-right tabular-nums">
                                        {row.totals.absent}
                                    </td>
                                    <td className="border-b px-2 text-right tabular-nums">
                                        {row.totals.leave}
                                    </td>
                                    <td className="border-b px-2 text-right whitespace-nowrap tabular-nums">
                                        {formatMinutes(
                                            row.totals.worked_minutes,
                                        )}
                                    </td>
                                    <td className="border-b px-2 text-right whitespace-nowrap tabular-nums">
                                        {formatMinutes(
                                            row.totals.overtime_minutes,
                                        )}
                                        {row.totals.pending_overtime_minutes >
                                            0 && (
                                            <div className="text-amber-600">
                                                +
                                                {formatMinutes(
                                                    row.totals
                                                        .pending_overtime_minutes,
                                                )}{' '}
                                                pending
                                            </div>
                                        )}
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>

                <DataTablePagination
                    meta={pagination}
                    onPageChange={table.setPage}
                    onPerPageChange={table.setPerPage}
                />
            </div>
        </>
    );
}

AttendanceRegister.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard().url },
        { title: 'Attendance Register', href: register().url },
    ],
};
