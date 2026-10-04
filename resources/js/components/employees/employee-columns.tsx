import { Link } from '@inertiajs/react';
import type { TableColumn } from '@/components/data-table';
import { DataTableSortHeader } from '@/components/data-table';
import { EmploymentStatusBadge } from '@/components/employees/employment-status-badge';
import { formatDate } from '@/lib/dates';
import { formatAmount } from '@/lib/utils';
import type { Employee, SortState } from '@/types';
import { show } from '@/actions/App/Http/Controllers/Employees/EmployeeController';

type EmployeeColumnsOptions = {
    sort: SortState | null;
    onSort: (column: string) => void;
    showSalary: boolean;
};

export function getEmployeeColumns({
    sort,
    onSort,
    showSalary,
}: EmployeeColumnsOptions): TableColumn<Employee>[] {
    const columns: TableColumn<Employee>[] = [
        {
            accessorKey: 'employee_code',
            header: () => (
                <DataTableSortHeader
                    column="employee_code"
                    label="Code"
                    sort={sort}
                    onSort={onSort}
                />
            ),
            cell: ({ row }) => (
                <span className="font-mono text-sm">
                    {row.original.employee_code}
                </span>
            ),
        },
        {
            accessorKey: 'name',
            header: () => (
                <DataTableSortHeader
                    column="name"
                    label="Name"
                    sort={sort}
                    onSort={onSort}
                />
            ),
            cell: ({ row }) => (
                <div>
                    <Link
                        href={show(row.original).url}
                        className="font-medium hover:underline"
                    >
                        {row.original.name}
                    </Link>
                    <div className="text-xs text-muted-foreground">
                        {row.original.designation?.name ?? '—'}
                    </div>
                </div>
            ),
        },
        {
            id: 'branch',
            header: () => <span>Branch / Department</span>,
            cell: ({ row }) => (
                <div className="text-sm">
                    <div>{row.original.branch?.name}</div>
                    <div className="text-xs text-muted-foreground">
                        {row.original.department?.name ?? '—'}
                    </div>
                </div>
            ),
        },
        {
            accessorKey: 'device_pin',
            header: () => <span>Device PIN</span>,
            cell: ({ row }) => (
                <span className="font-mono text-sm">
                    {row.original.device_pin ?? '—'}
                </span>
            ),
        },
        {
            accessorKey: 'joining_date',
            header: () => (
                <DataTableSortHeader
                    column="joining_date"
                    label="Joined"
                    sort={sort}
                    onSort={onSort}
                />
            ),
            cell: ({ row }) => formatDate(row.original.joining_date),
        },
    ];

    if (showSalary) {
        columns.push({
            id: 'salary',
            header: () => <span>Gross Salary</span>,
            cell: ({ row }) =>
                row.original.current_salary
                    ? formatAmount(row.original.current_salary.gross_salary)
                    : '—',
        });
    }

    columns.push({
        accessorKey: 'status',
        header: () => <span>Status</span>,
        cell: ({ row }) => (
            <EmploymentStatusBadge status={row.original.status} />
        ),
    });

    return columns;
}
