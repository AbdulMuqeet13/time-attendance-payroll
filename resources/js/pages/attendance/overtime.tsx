import { Head, router } from '@inertiajs/react';
import { Check, X } from 'lucide-react';
import { useState } from 'react';
import type { TableColumn } from '@/components/data-table';
import { DataTable } from '@/components/data-table';
import Heading from '@/components/heading';
import { StatusBadge, headline } from '@/components/status-badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { formatDate } from '@/lib/dates';
import { formatMinutes } from '@/lib/shifts';
import { formatTime } from '@/lib/time';
import type { AttendanceDay } from '@/types';
import {
    decide,
    index,
} from '@/actions/App/Http/Controllers/Attendance/OvertimeController';
import { dashboard } from '@/routes';

type OvertimePageProps = {
    pending: AttendanceDay[];
    decided: AttendanceDay[];
};

function ApproveControls({ day }: { day: AttendanceDay }) {
    const [hours, setHours] = useState(
        String(Math.round((day.overtime_minutes / 60) * 100) / 100),
    );

    const send = (minutes: number) =>
        router.post(
            decide(day).url,
            { approved_minutes: minutes },
            { preserveScroll: true },
        );

    return (
        <div className="flex items-center gap-2">
            <Input
                type="number"
                min="0"
                step="0.25"
                max={day.overtime_minutes / 60}
                value={hours}
                onChange={(e) => setHours(e.target.value)}
                className="h-8 w-20"
                aria-label="Approved hours"
            />
            <span className="text-xs text-muted-foreground">h</span>
            <Button
                size="sm"
                onClick={() =>
                    send(
                        Math.min(
                            day.overtime_minutes,
                            Math.round(Number(hours) * 60),
                        ),
                    )
                }
            >
                <Check className="mr-1 size-3.5" />
                Approve
            </Button>
            <Button size="sm" variant="outline" onClick={() => send(0)}>
                <X className="mr-1 size-3.5" />
                Reject
            </Button>
        </div>
    );
}

export default function Overtime({ pending, decided }: OvertimePageProps) {
    const baseColumns: TableColumn<AttendanceDay>[] = [
        {
            accessorKey: 'date',
            header: () => <span>Date</span>,
            cell: ({ row }) => formatDate(row.original.date),
        },
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
            id: 'shift',
            header: () => <span>Shift / day</span>,
            cell: ({ row }) =>
                row.original.shift
                    ? `${row.original.shift.name} ${row.original.shift.start_time}–${row.original.shift.end_time}`
                    : headline(row.original.day_type),
        },
        {
            id: 'out',
            header: () => <span>Checked out</span>,
            cell: ({ row }) =>
                formatTime(row.original.last_out, row.original.date),
        },
        {
            id: 'overtime',
            header: () => <span>Overtime</span>,
            cell: ({ row }) => (
                <span className="font-medium">
                    {formatMinutes(row.original.overtime_minutes)}
                    {row.original.day_type !== 'working' && (
                        <StatusBadge tone="info" className="ml-2">
                            {headline(row.original.day_type)} rate
                        </StatusBadge>
                    )}
                </span>
            ),
        },
    ];

    const pendingColumns: TableColumn<AttendanceDay>[] = [
        ...baseColumns,
        {
            id: 'decide',
            header: () => <span>Decision</span>,
            cell: ({ row }) => <ApproveControls day={row.original} />,
        },
    ];

    const decidedColumns: TableColumn<AttendanceDay>[] = [
        ...baseColumns,
        {
            id: 'approved',
            header: () => <span>Approved</span>,
            cell: ({ row }) =>
                row.original.approved_overtime_minutes === 0 ? (
                    <StatusBadge tone="neutral">Rejected</StatusBadge>
                ) : (
                    <StatusBadge tone="success">
                        {formatMinutes(row.original.approved_overtime_minutes)}
                    </StatusBadge>
                ),
        },
        {
            id: 'change',
            header: () => <span className="sr-only">Change</span>,
            cell: ({ row }) =>
                row.original.locked_at ? null : (
                    <ApproveControls day={row.original} />
                ),
        },
    ];

    return (
        <>
            <Head title="Overtime" />

            <div className="flex h-full flex-1 flex-col gap-4 overflow-x-auto rounded-xl p-4">
                <Heading
                    title="Overtime Approval"
                    description="Overtime is paid in payroll only once approved. Approve all or part of it."
                />

                <Tabs defaultValue="pending">
                    <TabsList>
                        <TabsTrigger value="pending">
                            Waiting ({pending.length})
                        </TabsTrigger>
                        <TabsTrigger value="decided">
                            Decided (last 45 days)
                        </TabsTrigger>
                    </TabsList>
                    <TabsContent value="pending" className="mt-4">
                        <DataTable
                            columns={pendingColumns}
                            data={pending}
                            emptyMessage="No overtime waiting for approval."
                            emptyDescription=""
                        />
                    </TabsContent>
                    <TabsContent value="decided" className="mt-4">
                        <DataTable
                            columns={decidedColumns}
                            data={decided}
                            emptyMessage="Nothing decided yet."
                            emptyDescription=""
                        />
                    </TabsContent>
                </Tabs>
            </div>
        </>
    );
}

Overtime.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard().url },
        { title: 'Overtime', href: index().url },
    ],
};
