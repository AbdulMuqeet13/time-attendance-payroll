import { Head } from '@inertiajs/react';
import { useState } from 'react';
import type { TableColumn } from '@/components/data-table';
import { DataTable } from '@/components/data-table';
import { AssignPinDialog } from '@/components/devices/assign-pin-dialog';
import Heading from '@/components/heading';
import { Button } from '@/components/ui/button';
import { formatDateTime } from '@/lib/dates';
import type { EmployeeOption, UnmatchedPin } from '@/types';
import { index as devices } from '@/actions/App/Http/Controllers/Devices/DeviceController';
import { index } from '@/actions/App/Http/Controllers/Devices/UnmatchedPunchController';
import { dashboard } from '@/routes';

type UnmatchedPunchesPageProps = {
    pins: UnmatchedPin[];
    employees: EmployeeOption[];
    canAssign: boolean;
};

export default function UnmatchedPunches({
    pins,
    employees,
    canAssign,
}: UnmatchedPunchesPageProps) {
    const [assigning, setAssigning] = useState<UnmatchedPin | null>(null);

    const columns: TableColumn<UnmatchedPin>[] = [
        {
            accessorKey: 'pin',
            header: () => <span>Device PIN</span>,
            cell: ({ row }) => (
                <span className="font-mono font-medium">
                    {row.original.pin}
                </span>
            ),
        },
        {
            accessorKey: 'punches_count',
            header: () => <span>Scans</span>,
        },
        {
            accessorKey: 'first_punch_at',
            header: () => <span>First scan</span>,
            cell: ({ row }) => formatDateTime(row.original.first_punch_at),
        },
        {
            accessorKey: 'last_punch_at',
            header: () => <span>Last scan</span>,
            cell: ({ row }) => formatDateTime(row.original.last_punch_at),
        },
    ];

    if (canAssign) {
        columns.push({
            id: 'actions',
            header: () => <span className="sr-only">Actions</span>,
            cell: ({ row }) => (
                <Button size="sm" onClick={() => setAssigning(row.original)}>
                    Assign to employee
                </Button>
            ),
        });
    }

    return (
        <>
            <Head title="Unmatched Scans" />

            <div className="flex h-full flex-1 flex-col gap-4 overflow-x-auto rounded-xl p-4">
                <Heading
                    title="Unmatched Scans"
                    description="Scans whose device PIN belongs to no employee. They are kept and added to attendance once the PIN is assigned."
                />

                <DataTable
                    columns={columns}
                    data={pins}
                    emptyMessage="Every scan matches an employee."
                    emptyDescription=""
                />
            </div>

            {assigning && (
                <AssignPinDialog
                    unmatched={assigning}
                    employees={employees}
                    onClose={() => setAssigning(null)}
                />
            )}
        </>
    );
}

UnmatchedPunches.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard().url },
        { title: 'Devices', href: devices().url },
        { title: 'Unmatched Scans', href: index().url },
    ],
};
