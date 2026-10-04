import { Head, router } from '@inertiajs/react';
import { ChevronLeft, ChevronRight, Download, Plus, Undo2 } from 'lucide-react';
import { useState } from 'react';
import { AttendanceStatusBadge } from '@/components/attendance/attendance-status-badge';
import { LeaveRequestDialog } from '@/components/leaves/leave-request-dialog';
import { LeaveStatusBadge } from '@/components/leaves/leave-status-badge';
import { StatCard } from '@/components/stat-card';
import { StatusBadge } from '@/components/status-badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { formatDate } from '@/lib/dates';
import { formatMinutes } from '@/lib/shifts';
import { formatTime } from '@/lib/time';
import { formatAmount } from '@/lib/utils';
import type { AttendanceDay, LeaveRequest, LeaveType, Option } from '@/types';
import { cancel } from '@/actions/App/Http/Controllers/Leaves/LeaveRequestController';
import {
    index,
    payslip,
} from '@/actions/App/Http/Controllers/SelfService/MyPortalController';

type MyPortalProps = {
    employee: {
        id: number;
        name: string;
        employee_code: string;
        device_pin: string | null;
        joining_date: string;
        branch: Option | null;
        department: Option | null;
        designation: Option | null;
    } | null;
    month?: string;
    days?: AttendanceDay[];
    balances?: {
        type: Option;
        total: number;
        used: number;
        pending: number;
        available: number;
    }[];
    leaveRequests?: LeaveRequest[];
    leaveTypes?: LeaveType[];
    payslips?: {
        id: number;
        net_pay: string;
        earnings_total: string;
        deductions_total: string;
        payment_status: 'paid' | 'unpaid';
        paid_at: string | null;
        payroll_run: {
            id: number;
            reference: string;
            period_start: string;
            period_end: string;
        };
    }[];
};

export default function MyPortal({
    employee,
    month = '',
    days = [],
    balances = [],
    leaveRequests = [],
    leaveTypes = [],
    payslips = [],
}: MyPortalProps) {
    const [isApplying, setIsApplying] = useState(false);

    if (!employee) {
        return (
            <div className="p-6">
                <Head title="My Portal" />
                <p className="text-muted-foreground">
                    Your login is not linked to an employee record yet. Please
                    contact HR.
                </p>
            </div>
        );
    }

    const goToMonth = (offset: number) => {
        const [year, monthNumber] = month.split('-').map(Number);
        const target = new Date(year, monthNumber - 1 + offset, 1);
        router.get(
            index().url,
            {
                month: `${target.getFullYear()}-${String(target.getMonth() + 1).padStart(2, '0')}`,
            },
            { preserveScroll: true },
        );
    };

    const scheduled = days.filter((day) => day.shift_id !== null);
    const count = (status: string) =>
        scheduled.filter((day) => day.status === status).length;
    const monthLabel = new Date(`${month}-01T00:00:00`).toLocaleDateString(
        'en-GB',
        { month: 'long', year: 'numeric' },
    );

    return (
        <>
            <Head title="My Portal" />
            <div className="flex h-full flex-1 flex-col gap-4 overflow-x-auto rounded-xl p-4">
                <div>
                    <h1 className="text-xl font-semibold">{employee.name}</h1>
                    <p className="text-sm text-muted-foreground">
                        {employee.employee_code} ·{' '}
                        {employee.designation?.name ?? '—'} ·{' '}
                        {employee.branch?.name}
                        {employee.device_pin &&
                            ` · device PIN ${employee.device_pin}`}
                    </p>
                </div>

                <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                    <StatCard
                        label={`Present in ${monthLabel}`}
                        value={count('present') + count('late')}
                    />
                    <StatCard label="Late" value={count('late')} />
                    <StatCard label="Absent" value={count('absent')} />
                    <StatCard
                        label="Approved overtime"
                        value={formatMinutes(
                            days.reduce(
                                (sum, day) =>
                                    sum + (day.approved_overtime_minutes ?? 0),
                                0,
                            ),
                        )}
                    />
                </div>

                <Card>
                    <CardHeader className="flex flex-row items-center justify-between">
                        <CardTitle>My attendance</CardTitle>
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
                            <span className="w-36 text-center text-sm font-semibold">
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
                    </CardHeader>
                    <CardContent>
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead>Date</TableHead>
                                    <TableHead>Shift</TableHead>
                                    <TableHead>In / Out</TableHead>
                                    <TableHead>Worked</TableHead>
                                    <TableHead>Status</TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {days.length === 0 && (
                                    <TableRow>
                                        <TableCell
                                            colSpan={5}
                                            className="h-20 text-center text-muted-foreground"
                                        >
                                            No attendance for this month yet.
                                        </TableCell>
                                    </TableRow>
                                )}
                                {days.map((day) => (
                                    <TableRow key={day.id}>
                                        <TableCell>
                                            {formatDate(day.date)}
                                        </TableCell>
                                        <TableCell>
                                            {day.shift
                                                ? `${day.shift.name} ${day.shift.start_time}–${day.shift.end_time}`
                                                : '—'}
                                        </TableCell>
                                        <TableCell className="font-mono text-sm">
                                            {formatTime(day.first_in, day.date)}{' '}
                                            –{' '}
                                            {formatTime(day.last_out, day.date)}
                                        </TableCell>
                                        <TableCell>
                                            {formatMinutes(day.worked_minutes)}
                                        </TableCell>
                                        <TableCell>
                                            <AttendanceStatusBadge
                                                status={day.status}
                                            />
                                            {day.is_missing_checkout && (
                                                <StatusBadge
                                                    tone="danger"
                                                    className="ml-1"
                                                >
                                                    No check-out
                                                </StatusBadge>
                                            )}
                                        </TableCell>
                                    </TableRow>
                                ))}
                            </TableBody>
                        </Table>
                    </CardContent>
                </Card>

                <div className="grid gap-4 lg:grid-cols-2">
                    <Card>
                        <CardHeader className="flex flex-row items-center justify-between">
                            <CardTitle>My leave</CardTitle>
                            <Button
                                size="sm"
                                onClick={() => setIsApplying(true)}
                            >
                                <Plus className="mr-2 size-4" /> Apply
                            </Button>
                        </CardHeader>
                        <CardContent className="space-y-4">
                            <div className="grid grid-cols-3 gap-2">
                                {balances.map((balance) => (
                                    <div
                                        key={balance.type.id}
                                        className="rounded-lg border p-2 text-sm"
                                    >
                                        <div className="text-xs text-muted-foreground">
                                            {balance.type.name}
                                        </div>
                                        <div className="font-semibold">
                                            {balance.available}{' '}
                                            <span className="font-normal text-muted-foreground">
                                                / {balance.total}
                                            </span>
                                        </div>
                                    </div>
                                ))}
                            </div>
                            <ul className="divide-y rounded-md border text-sm">
                                {leaveRequests.length === 0 && (
                                    <li className="p-3 text-muted-foreground">
                                        No leave requests yet.
                                    </li>
                                )}
                                {leaveRequests.map((request) => (
                                    <li
                                        key={request.id}
                                        className="flex items-center gap-3 p-3"
                                    >
                                        <div className="flex-1">
                                            <div className="font-medium">
                                                {request.leave_type.name}
                                            </div>
                                            <div className="text-xs text-muted-foreground">
                                                {formatDate(request.start_date)}
                                                {request.end_date !==
                                                    request.start_date &&
                                                    ` → ${formatDate(request.end_date)}`}{' '}
                                                · {Number(request.days)} day(s)
                                            </div>
                                        </div>
                                        <LeaveStatusBadge
                                            status={request.status}
                                        />
                                        {request.status === 'pending' && (
                                            <Button
                                                variant="ghost"
                                                size="icon"
                                                className="size-7"
                                                onClick={() =>
                                                    router.post(
                                                        cancel(request).url,
                                                        {},
                                                        {
                                                            preserveScroll: true,
                                                        },
                                                    )
                                                }
                                            >
                                                <Undo2 className="size-3.5" />
                                                <span className="sr-only">
                                                    Withdraw
                                                </span>
                                            </Button>
                                        )}
                                    </li>
                                ))}
                            </ul>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader>
                            <CardTitle>My payslips</CardTitle>
                        </CardHeader>
                        <CardContent>
                            <ul className="divide-y rounded-md border text-sm">
                                {payslips.length === 0 && (
                                    <li className="p-3 text-muted-foreground">
                                        No payslips yet.
                                    </li>
                                )}
                                {payslips.map((item) => (
                                    <li
                                        key={item.id}
                                        className="flex items-center gap-3 p-3"
                                    >
                                        <div className="flex-1">
                                            <div className="font-medium">
                                                {new Date(
                                                    `${item.payroll_run.period_start}T00:00:00`,
                                                ).toLocaleDateString('en-GB', {
                                                    month: 'long',
                                                    year: 'numeric',
                                                })}
                                            </div>
                                            <div className="text-xs text-muted-foreground">
                                                Net {formatAmount(item.net_pay)}{' '}
                                                ·{' '}
                                                {item.payment_status === 'paid'
                                                    ? `paid ${formatDate(item.paid_at?.slice(0, 10))}`
                                                    : 'not paid yet'}
                                            </div>
                                        </div>
                                        <Button
                                            variant="outline"
                                            size="sm"
                                            asChild
                                        >
                                            <a href={payslip(item.id).url}>
                                                <Download className="mr-1 size-3.5" />{' '}
                                                PDF
                                            </a>
                                        </Button>
                                    </li>
                                ))}
                            </ul>
                        </CardContent>
                    </Card>
                </div>
            </div>

            {isApplying && (
                <LeaveRequestDialog
                    leaveTypes={leaveTypes}
                    onClose={() => setIsApplying(false)}
                />
            )}
        </>
    );
}

MyPortal.layout = {
    breadcrumbs: [{ title: 'My Portal', href: index().url }],
};
