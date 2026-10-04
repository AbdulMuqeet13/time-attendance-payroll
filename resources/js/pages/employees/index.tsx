import { Head, Link } from '@inertiajs/react';
import { Plus } from 'lucide-react';
import { useMemo } from 'react';
import {
    DataTable,
    DataTableSearch,
    DataTableToolbar,
} from '@/components/data-table';
import { getEmployeeColumns } from '@/components/employees/employee-columns';
import Heading from '@/components/heading';
import type { SelectOption } from '@/components/option-select';
import { OptionSelect, toOptions } from '@/components/option-select';
import { Button } from '@/components/ui/button';
import { useCan } from '@/hooks/use-can';
import { useDataTable } from '@/hooks/use-data-table';
import type { Employee, Option, Paginated } from '@/types';
import {
    create,
    index,
} from '@/actions/App/Http/Controllers/Employees/EmployeeController';
import { dashboard } from '@/routes';

type EmployeesPageProps = {
    employees: Paginated<Employee>;
    branches: Option[];
    departments: Option[];
    employmentStatuses: SelectOption[];
};

export default function Employees({
    employees,
    branches,
    departments,
    employmentStatuses,
}: EmployeesPageProps) {
    const { can } = useCan();
    const table = useDataTable({ only: ['employees'], defaultPerPage: 15 });

    const columns = useMemo(
        () =>
            getEmployeeColumns({
                sort: table.sort,
                onSort: table.setSort,
                showSalary: can('salaries.view'),
            }),
        [table.sort, table.setSort, can],
    );

    return (
        <>
            <Head title="Employees" />

            <div className="flex h-full flex-1 flex-col gap-4 overflow-x-auto rounded-xl p-4">
                <Heading
                    title="Employees"
                    description="Everyone on the payroll, with their branch, device PIN and current salary."
                />

                <DataTable
                    columns={columns}
                    data={employees.data}
                    meta={employees}
                    onPageChange={table.setPage}
                    onPerPageChange={table.setPerPage}
                    emptyMessage="No employees found."
                    toolbar={
                        <DataTableToolbar>
                            <div className="flex flex-1 flex-wrap items-center gap-2">
                                <DataTableSearch
                                    value={table.search}
                                    onChange={table.setSearch}
                                    placeholder="Name, code, CNIC, phone or PIN..."
                                    className="w-full sm:w-72"
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
                                    options={employmentStatuses}
                                    noneLabel="All statuses"
                                    placeholder="All statuses"
                                />
                            </div>
                            {can('employees.create') && (
                                <Button size="sm" asChild>
                                    <Link href={create().url}>
                                        <Plus className="mr-2 size-4" />
                                        Add Employee
                                    </Link>
                                </Button>
                            )}
                        </DataTableToolbar>
                    }
                />
            </div>
        </>
    );
}

Employees.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard().url },
        { title: 'Employees', href: index().url },
    ],
};
