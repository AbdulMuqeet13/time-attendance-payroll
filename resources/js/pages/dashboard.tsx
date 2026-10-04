import { Head, Link } from '@inertiajs/react';
import {
    AlertTriangle,
    CalendarClock,
    CheckCircle2,
    Clock,
    Fingerprint,
    Plane,
    UserX,
    Wallet,
} from 'lucide-react';
import type { ReactNode } from 'react';
import type { TrendPoint } from '@/components/dashboard/attendance-trend-chart';
import { AttendanceTrendChart } from '@/components/dashboard/attendance-trend-chart';
import { PayrollStatusBadge } from '@/components/payroll/payroll-status-badge';
import { StatCard } from '@/components/stat-card';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Skeleton } from '@/components/ui/skeleton';
import { formatDate } from '@/lib/dates';
import { formatAmount } from '@/lib/utils';
import type { PayrollRun } from '@/types';
import AttendanceController from '@/actions/App/Http/Controllers/Attendance/AttendanceController';
import OvertimeController from '@/actions/App/Http/Controllers/Attendance/OvertimeController';
import DeviceController from '@/actions/App/Http/Controllers/Devices/DeviceController';
import UnmatchedPunchController from '@/actions/App/Http/Controllers/Devices/UnmatchedPunchController';
import LeaveRequestController from '@/actions/App/Http/Controllers/Leaves/LeaveRequestController';
import PayrollRunController from '@/actions/App/Http/Controllers/Payroll/PayrollRunController';
import ShiftAssignmentController from '@/actions/App/Http/Controllers/Shifts/ShiftAssignmentController';
import { dashboard } from '@/routes';

type DashboardProps = {
    today: {
        employees: number;
        in: number;
        late: number;
        absent: number;
        leave: number;
        not_in_yet: number;
        off: number;
    } | null;
    trend?: TrendPoint[] | null;
    devices: {
        online: number;
        total: number;
        unclaimed: number;
        unmatched_scans: number;
    } | null;
    pending: {
        leave: number | null;
        overtime: number | null;
        missing_checkouts: number | null;
        without_roster: number | null;
    };
    payroll: Pick<
        PayrollRun,
        | 'id'
        | 'reference'
        | 'period_start'
        | 'period_end'
        | 'status'
        | 'net_total'
        | 'employee_count'
    > | null;
};

function TaskLink({
    href,
    icon,
    label,
    count,
}: {
    href: string;
    icon: ReactNode;
    label: string;
    count: number;
}) {
    return (
        <Link
            href={href}
            className="flex items-center gap-3 rounded-md px-2 py-2 text-sm hover:bg-muted"
        >
            <span className="text-muted-foreground">{icon}</span>
            <span className="flex-1">{label}</span>
            <span
                className={
                    count > 0
                        ? 'font-semibold tabular-nums'
                        : 'text-muted-foreground tabular-nums'
                }
            >
                {count}
            </span>
        </Link>
    );
}

export default function Dashboard({
    today,
    trend,
    devices,
    pending,
    payroll,
}: DashboardProps) {
    return (
        <>
            <Head title="Dashboard" />
            <div className="flex h-full flex-1 flex-col gap-4 overflow-x-auto rounded-xl p-4">
                {today && (
                    <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-5">
                        <StatCard
                            label="In today"
                            value={today.in}
                            hint={`of ${today.employees} active employees`}
                        />
                        <StatCard label="Late" value={today.late} />
                        <StatCard
                            label="Absent"
                            value={today.absent}
                            hint={
                                today.not_in_yet > 0
                                    ? `${today.not_in_yet} shifts not started yet`
                                    : undefined
                            }
                        />
                        <StatCard label="On leave" value={today.leave} />
                        <StatCard label="Off / holiday" value={today.off} />
                    </div>
                )}

                <div className="grid gap-4 lg:grid-cols-3">
                    {today && (
                        <Card className="lg:col-span-2">
                            <CardHeader>
                                <CardTitle>Attendance, last 30 days</CardTitle>
                                <CardDescription>
                                    Employees per status each day.{' '}
                                    <Link
                                        href={
                                            AttendanceController.register().url
                                        }
                                        className="underline"
                                    >
                                        See the register
                                    </Link>{' '}
                                    for the numbers.
                                </CardDescription>
                            </CardHeader>
                            <CardContent>
                                {trend ? (
                                    <AttendanceTrendChart data={trend} />
                                ) : (
                                    <Skeleton className="h-[260px] w-full animate-pulse" />
                                )}
                            </CardContent>
                        </Card>
                    )}

                    <div className="flex flex-col gap-4">
                        <Card>
                            <CardHeader>
                                <CardTitle>Needs attention</CardTitle>
                            </CardHeader>
                            <CardContent className="space-y-1">
                                {pending.leave !== null && (
                                    <TaskLink
                                        href={
                                            LeaveRequestController.index().url
                                        }
                                        icon={<Plane className="size-4" />}
                                        label="Leave to approve"
                                        count={pending.leave}
                                    />
                                )}
                                {pending.overtime !== null && (
                                    <TaskLink
                                        href={OvertimeController.index().url}
                                        icon={<Clock className="size-4" />}
                                        label="Overtime to approve"
                                        count={pending.overtime}
                                    />
                                )}
                                {pending.missing_checkouts !== null && (
                                    <TaskLink
                                        href={AttendanceController.index().url}
                                        icon={<UserX className="size-4" />}
                                        label="Missing check-outs (7 days)"
                                        count={pending.missing_checkouts}
                                    />
                                )}
                                {pending.without_roster !== null && (
                                    <TaskLink
                                        href={
                                            ShiftAssignmentController.index()
                                                .url
                                        }
                                        icon={
                                            <CalendarClock className="size-4" />
                                        }
                                        label="Employees without a shift"
                                        count={pending.without_roster}
                                    />
                                )}
                                {devices && (
                                    <TaskLink
                                        href={
                                            UnmatchedPunchController.index().url
                                        }
                                        icon={
                                            <AlertTriangle className="size-4" />
                                        }
                                        label="Unmatched scans"
                                        count={devices.unmatched_scans}
                                    />
                                )}
                            </CardContent>
                        </Card>

                        {devices && (
                            <Card>
                                <CardHeader>
                                    <CardTitle className="flex items-center gap-2">
                                        <Fingerprint className="size-4" />{' '}
                                        Devices
                                    </CardTitle>
                                </CardHeader>
                                <CardContent className="text-sm">
                                    <Link
                                        href={DeviceController.index().url}
                                        className="flex items-center gap-2 hover:underline"
                                    >
                                        {devices.online === devices.total ? (
                                            <CheckCircle2
                                                className="size-4 text-emerald-600"
                                                aria-hidden
                                            />
                                        ) : (
                                            <AlertTriangle
                                                className="size-4 text-amber-600"
                                                aria-hidden
                                            />
                                        )}
                                        {devices.online} of {devices.total}{' '}
                                        online
                                    </Link>
                                    {devices.unclaimed > 0 && (
                                        <p className="mt-1 text-muted-foreground">
                                            {devices.unclaimed} new device(s)
                                            waiting to be claimed
                                        </p>
                                    )}
                                </CardContent>
                            </Card>
                        )}

                        {payroll && (
                            <Card>
                                <CardHeader>
                                    <CardTitle className="flex items-center gap-2">
                                        <Wallet className="size-4" /> Latest
                                        payroll
                                    </CardTitle>
                                </CardHeader>
                                <CardContent className="space-y-1 text-sm">
                                    <Link
                                        href={
                                            PayrollRunController.show(payroll)
                                                .url
                                        }
                                        className="font-mono font-medium hover:underline"
                                    >
                                        {payroll.reference}
                                    </Link>{' '}
                                    <PayrollStatusBadge
                                        status={payroll.status}
                                    />
                                    <p className="text-muted-foreground">
                                        {formatDate(payroll.period_start)} →{' '}
                                        {formatDate(payroll.period_end)} ·{' '}
                                        {payroll.employee_count} employees
                                    </p>
                                    <p className="font-semibold tabular-nums">
                                        Net {formatAmount(payroll.net_total)}
                                    </p>
                                </CardContent>
                            </Card>
                        )}
                    </div>
                </div>
            </div>
        </>
    );
}

Dashboard.layout = {
    breadcrumbs: [{ title: 'Dashboard', href: dashboard() }],
};
