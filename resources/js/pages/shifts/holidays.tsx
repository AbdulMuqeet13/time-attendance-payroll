import { Head, router } from '@inertiajs/react';
import { ChevronLeft, ChevronRight, Plus } from 'lucide-react';
import { useState } from 'react';
import { ConfirmDialog } from '@/components/confirm-dialog';
import type { TableColumn } from '@/components/data-table';
import { DataTable, DataTableToolbar } from '@/components/data-table';
import Heading from '@/components/heading';
import { RowActions } from '@/components/organisation/row-actions';
import { HolidayFormDialog } from '@/components/shifts/holiday-form-dialog';
import { Button } from '@/components/ui/button';
import { formatDate, parseIsoDate } from '@/lib/dates';
import type { Holiday, Option } from '@/types';
import {
    destroy,
    index,
} from '@/actions/App/Http/Controllers/Shifts/HolidayController';
import { dashboard } from '@/routes';

type HolidaysPageProps = {
    year: number;
    holidays: Holiday[];
    branches: Option[];
    canManage: boolean;
};

export default function Holidays({
    year,
    holidays,
    branches,
    canManage,
}: HolidaysPageProps) {
    const [isCreateOpen, setIsCreateOpen] = useState(false);
    const [editing, setEditing] = useState<Holiday | null>(null);
    const [deleting, setDeleting] = useState<Holiday | null>(null);

    const goToYear = (target: number) =>
        router.get(index().url, { year: target }, { preserveScroll: true });

    const columns: TableColumn<Holiday>[] = [
        {
            accessorKey: 'date',
            header: () => <span>Date</span>,
            cell: ({ row }) => (
                <div>
                    <div className="font-medium">
                        {formatDate(row.original.date)}
                    </div>
                    <div className="text-xs text-muted-foreground">
                        {parseIsoDate(row.original.date)?.toLocaleDateString(
                            'en-GB',
                            { weekday: 'long' },
                        )}
                    </div>
                </div>
            ),
        },
        {
            accessorKey: 'name',
            header: () => <span>Holiday</span>,
        },
        {
            id: 'branch',
            header: () => <span>Applies to</span>,
            cell: ({ row }) => row.original.branch?.name ?? 'All branches',
        },
    ];

    if (canManage) {
        columns.push({
            id: 'actions',
            header: () => <span className="sr-only">Actions</span>,
            cell: ({ row }) => (
                <RowActions
                    onEdit={() => setEditing(row.original)}
                    onDelete={() => setDeleting(row.original)}
                />
            ),
        });
    }

    return (
        <>
            <Head title="Holidays" />

            <div className="flex h-full flex-1 flex-col gap-4 overflow-x-auto rounded-xl p-4">
                <Heading
                    title="Holidays"
                    description="Paid public holidays. Nobody is marked absent on a holiday."
                />

                <DataTable
                    columns={columns}
                    data={holidays}
                    emptyMessage={`No holidays in ${year}.`}
                    emptyDescription=""
                    toolbar={
                        <DataTableToolbar>
                            <div className="flex items-center gap-2">
                                <Button
                                    variant="outline"
                                    size="icon"
                                    className="size-8"
                                    onClick={() => goToYear(year - 1)}
                                >
                                    <ChevronLeft className="size-4" />
                                    <span className="sr-only">
                                        Previous year
                                    </span>
                                </Button>
                                <span className="w-14 text-center font-semibold">
                                    {year}
                                </span>
                                <Button
                                    variant="outline"
                                    size="icon"
                                    className="size-8"
                                    onClick={() => goToYear(year + 1)}
                                >
                                    <ChevronRight className="size-4" />
                                    <span className="sr-only">Next year</span>
                                </Button>
                            </div>
                            {canManage && (
                                <Button
                                    size="sm"
                                    onClick={() => setIsCreateOpen(true)}
                                >
                                    <Plus className="mr-2 size-4" />
                                    Add Holiday
                                </Button>
                            )}
                        </DataTableToolbar>
                    }
                />
            </div>

            {(isCreateOpen || editing) && (
                <HolidayFormDialog
                    holiday={editing}
                    branches={branches}
                    onClose={() => {
                        setIsCreateOpen(false);
                        setEditing(null);
                    }}
                />
            )}

            {deleting && (
                <ConfirmDialog
                    open
                    onClose={() => setDeleting(null)}
                    title="Delete Holiday"
                    description={`Delete ${deleting.name} on ${formatDate(deleting.date)}?`}
                    url={destroy(deleting).url}
                />
            )}
        </>
    );
}

Holidays.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard().url },
        { title: 'Holidays', href: index().url },
    ],
};
