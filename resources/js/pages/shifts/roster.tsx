import { Head } from '@inertiajs/react';
import { CalendarClock, Plus, Trash2 } from 'lucide-react';
import { useMemo, useState } from 'react';
import { ConfirmDialog } from '@/components/confirm-dialog';
import type { TableColumn } from '@/components/data-table';
import {
    DataTable,
    DataTableSearch,
    DataTableToolbar,
} from '@/components/data-table';
import Heading from '@/components/heading';
import { OptionSelect, toOptions } from '@/components/option-select';
import { RowActions } from '@/components/organisation/row-actions';
import { AssignShiftDialog } from '@/components/shifts/assign-shift-dialog';
import { RosterOverrideDialog } from '@/components/shifts/roster-override-dialog';
import { StatusBadge } from '@/components/status-badge';
import { Button } from '@/components/ui/button';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { useDataTable } from '@/hooks/use-data-table';
import { formatDate } from '@/lib/dates';
import { SHIFT_COLORS, formatDays, shiftLabel } from '@/lib/shifts';
import { cn } from '@/lib/utils';
import type {
    EmployeeOption,
    Option,
    Paginated,
    RosterOverride,
    ShiftAssignment,
    ShiftOption,
} from '@/types';
import { destroy as destroyOverride } from '@/actions/App/Http/Controllers/Shifts/RosterOverrideController';
import {
    destroy,
    index,
} from '@/actions/App/Http/Controllers/Shifts/ShiftAssignmentController';
import { dashboard } from '@/routes';

type RosterPageProps = {
    assignments: Paginated<ShiftAssignment>;
    overrides: RosterOverride[];
    shifts: ShiftOption[];
    branches: Option[];
    employees: EmployeeOption[];
    canManage: boolean;
};

export default function Roster({
    assignments,
    overrides,
    shifts,
    branches,
    employees,
    canManage,
}: RosterPageProps) {
    const table = useDataTable({ only: ['assignments'], defaultPerPage: 25 });

    const [isAssigning, setIsAssigning] = useState(false);
    const [editing, setEditing] = useState<ShiftAssignment | null>(null);
    const [deleting, setDeleting] = useState<ShiftAssignment | null>(null);
    const [isOverriding, setIsOverriding] = useState(false);
    const [deletingOverride, setDeletingOverride] =
        useState<RosterOverride | null>(null);

    const today = new Date().toISOString().slice(0, 10);

    const columns = useMemo(() => {
        const list: TableColumn<ShiftAssignment>[] = [
            {
                id: 'employee',
                header: () => <span>Employee</span>,
                cell: ({ row }) => (
                    <div>
                        <div className="font-medium">
                            {row.original.employee.name}
                        </div>
                        <div className="text-xs text-muted-foreground">
                            {row.original.employee.employee_code} ·{' '}
                            {row.original.employee.branch?.name}
                        </div>
                    </div>
                ),
            },
            {
                id: 'shift',
                header: () => <span>Shift</span>,
                cell: ({ row }) => (
                    <div className="flex items-center gap-2">
                        <span
                            className={cn(
                                'size-2.5 rounded-full',
                                SHIFT_COLORS[row.original.shift.color],
                            )}
                        />
                        {shiftLabel(row.original.shift)}
                    </div>
                ),
            },
            {
                accessorKey: 'days',
                header: () => <span>Days</span>,
                cell: ({ row }) => formatDays(row.original.days),
            },
            {
                id: 'period',
                header: () => <span>Period</span>,
                cell: ({ row }) => (
                    <span className="text-sm">
                        {formatDate(row.original.effective_from)} →{' '}
                        {row.original.effective_to
                            ? formatDate(row.original.effective_to)
                            : 'ongoing'}
                        {row.original.effective_from > today && (
                            <StatusBadge tone="info" className="ml-2">
                                Upcoming
                            </StatusBadge>
                        )}
                        {row.original.effective_to &&
                            row.original.effective_to < today && (
                                <StatusBadge tone="neutral" className="ml-2">
                                    Ended
                                </StatusBadge>
                            )}
                    </span>
                ),
            },
        ];

        if (canManage) {
            list.push({
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

        return list;
    }, [canManage, today]);

    return (
        <>
            <Head title="Roster" />

            <div className="flex h-full flex-1 flex-col gap-4 overflow-x-auto rounded-xl p-4">
                <Heading
                    title="Roster"
                    description="Who works which shift and when. Scans are matched to these shifts."
                />

                <Tabs defaultValue="assignments">
                    <TabsList>
                        <TabsTrigger value="assignments">
                            Shift assignments
                        </TabsTrigger>
                        <TabsTrigger value="overrides">
                            One-day changes ({overrides.length})
                        </TabsTrigger>
                    </TabsList>

                    <TabsContent value="assignments" className="mt-4">
                        <DataTable
                            columns={columns}
                            data={assignments.data}
                            meta={assignments}
                            onPageChange={table.setPage}
                            onPerPageChange={table.setPerPage}
                            emptyMessage="No shift assignments."
                            emptyDescription="Assign a shift to employees to start tracking lates and overtime."
                            toolbar={
                                <DataTableToolbar>
                                    <div className="flex flex-1 flex-wrap items-center gap-2">
                                        <DataTableSearch
                                            value={table.search}
                                            onChange={table.setSearch}
                                            placeholder="Employee name or code..."
                                            className="w-full sm:w-64"
                                        />
                                        <OptionSelect
                                            size="sm"
                                            className="w-48"
                                            value={String(
                                                table.filters.shift_id ?? '',
                                            )}
                                            onChange={(value) =>
                                                table.setFilter(
                                                    'shift_id',
                                                    value || undefined,
                                                )
                                            }
                                            options={shifts.map((shift) => ({
                                                value: String(shift.id),
                                                label: shiftLabel(shift),
                                            }))}
                                            noneLabel="All shifts"
                                            placeholder="All shifts"
                                        />
                                        {branches.length > 1 && (
                                            <OptionSelect
                                                size="sm"
                                                className="w-40"
                                                value={String(
                                                    table.filters.branch_id ??
                                                        '',
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
                                            className="w-36"
                                            value={String(
                                                table.filters.scope ??
                                                    'current',
                                            )}
                                            onChange={(value) =>
                                                table.setFilter(
                                                    'scope',
                                                    value === 'current'
                                                        ? undefined
                                                        : value,
                                                )
                                            }
                                            options={[
                                                {
                                                    value: 'current',
                                                    label: 'Current & upcoming',
                                                },
                                                {
                                                    value: 'all',
                                                    label: 'Including ended',
                                                },
                                            ]}
                                        />
                                    </div>
                                    {canManage && (
                                        <Button
                                            size="sm"
                                            onClick={() => setIsAssigning(true)}
                                            disabled={shifts.length === 0}
                                        >
                                            <Plus className="mr-2 size-4" />
                                            Assign Shift
                                        </Button>
                                    )}
                                </DataTableToolbar>
                            }
                        />
                    </TabsContent>

                    <TabsContent value="overrides" className="mt-4 space-y-4">
                        <div className="flex items-center justify-between gap-2">
                            <p className="text-sm text-muted-foreground">
                                Shift swaps and extra days off from the last 30
                                days onwards.
                            </p>
                            {canManage && (
                                <Button
                                    size="sm"
                                    onClick={() => setIsOverriding(true)}
                                >
                                    <CalendarClock className="mr-2 size-4" />
                                    Change One Day
                                </Button>
                            )}
                        </div>
                        <div className="rounded-md border">
                            <Table>
                                <TableHeader>
                                    <TableRow>
                                        <TableHead>Date</TableHead>
                                        <TableHead>Employee</TableHead>
                                        <TableHead>Works</TableHead>
                                        <TableHead>Note</TableHead>
                                        {canManage && <TableHead />}
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    {overrides.length === 0 && (
                                        <TableRow>
                                            <TableCell
                                                colSpan={5}
                                                className="h-24 text-center text-muted-foreground"
                                            >
                                                No one-day changes.
                                            </TableCell>
                                        </TableRow>
                                    )}
                                    {overrides.map((override) => (
                                        <TableRow key={override.id}>
                                            <TableCell>
                                                {formatDate(override.date)}
                                            </TableCell>
                                            <TableCell>
                                                {override.employee.name}
                                            </TableCell>
                                            <TableCell>
                                                {override.shift ? (
                                                    shiftLabel(override.shift)
                                                ) : (
                                                    <StatusBadge tone="neutral">
                                                        Day off
                                                    </StatusBadge>
                                                )}
                                            </TableCell>
                                            <TableCell className="text-muted-foreground">
                                                {override.note ?? '—'}
                                            </TableCell>
                                            {canManage && (
                                                <TableCell className="text-right">
                                                    <Button
                                                        variant="ghost"
                                                        size="icon"
                                                        className="size-8"
                                                        onClick={() =>
                                                            setDeletingOverride(
                                                                override,
                                                            )
                                                        }
                                                    >
                                                        <Trash2 className="size-4" />
                                                        <span className="sr-only">
                                                            Remove
                                                        </span>
                                                    </Button>
                                                </TableCell>
                                            )}
                                        </TableRow>
                                    ))}
                                </TableBody>
                            </Table>
                        </div>
                    </TabsContent>
                </Tabs>
            </div>

            {(isAssigning || editing) && (
                <AssignShiftDialog
                    assignment={editing}
                    employees={employees}
                    shifts={shifts}
                    onClose={() => {
                        setIsAssigning(false);
                        setEditing(null);
                    }}
                />
            )}

            {deleting && (
                <ConfirmDialog
                    open
                    onClose={() => setDeleting(null)}
                    title="Remove Assignment"
                    description={
                        <>
                            Remove {deleting.employee.name}'s{' '}
                            {deleting.shift.name} assignment completely? If they
                            worked it in the past, set an end date instead so
                            past attendance stays correct.
                        </>
                    }
                    url={destroy(deleting).url}
                    confirmLabel="Remove"
                />
            )}

            {isOverriding && (
                <RosterOverrideDialog
                    employees={employees}
                    shifts={shifts}
                    onClose={() => setIsOverriding(false)}
                />
            )}

            {deletingOverride && (
                <ConfirmDialog
                    open
                    onClose={() => setDeletingOverride(null)}
                    title="Remove One-Day Change"
                    description={`${deletingOverride.employee.name} goes back to their assigned shifts on ${formatDate(deletingOverride.date)}.`}
                    url={destroyOverride(deletingOverride).url}
                    confirmLabel="Remove"
                />
            )}
        </>
    );
}

Roster.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard().url },
        { title: 'Roster', href: index().url },
    ],
};
