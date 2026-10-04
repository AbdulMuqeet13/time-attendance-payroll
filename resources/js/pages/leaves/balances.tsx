import { Head, router } from '@inertiajs/react';
import { ChevronLeft, ChevronRight } from 'lucide-react';
import { DataTablePagination, DataTableSearch } from '@/components/data-table';
import Heading from '@/components/heading';
import { Button } from '@/components/ui/button';
import { useDataTable } from '@/hooks/use-data-table';
import type { LeaveBalanceSummary, PaginationMeta } from '@/types';
import {
    adjust,
    index,
} from '@/actions/App/Http/Controllers/Leaves/LeaveBalanceController';
import { dashboard } from '@/routes';

type BalancesPageProps = {
    year: number;
    types: { id: number; name: string; code: string }[];
    rows: {
        employee: { id: number; name: string; employee_code: string };
        balances: Record<number, LeaveBalanceSummary>;
    }[];
    pagination: PaginationMeta;
    canAdjust: boolean;
};

export default function LeaveBalances({
    year,
    types,
    rows,
    pagination,
    canAdjust,
}: BalancesPageProps) {
    const table = useDataTable({
        only: ['rows', 'pagination', 'year'],
        defaultPerPage: 25,
    });

    const adjustBalance = (
        balance: LeaveBalanceSummary,
        typeName: string,
        employeeName: string,
    ) => {
        const value = window.prompt(
            `Adjust ${employeeName}'s ${typeName} for ${year} by how many days? Use a negative number to remove days.`,
            '0',
        );

        if (value !== null && value.trim() !== '') {
            router.put(
                adjust(balance.balance_id).url,
                { adjustment: value },
                { preserveScroll: true },
            );
        }
    };

    return (
        <>
            <Head title="Leave Balances" />

            <div className="flex h-full flex-1 flex-col gap-4 overflow-x-auto rounded-xl p-4">
                <Heading
                    title="Leave Balances"
                    description="Available = entitlement + carried forward + adjustments − approved − pending."
                />

                <div className="flex flex-wrap items-center gap-2">
                    <div className="flex items-center gap-1">
                        <Button
                            variant="outline"
                            size="icon"
                            className="size-8"
                            onClick={() =>
                                table.setFilter('year', String(year - 1))
                            }
                        >
                            <ChevronLeft className="size-4" />
                            <span className="sr-only">Previous year</span>
                        </Button>
                        <span className="w-14 text-center font-semibold">
                            {year}
                        </span>
                        <Button
                            variant="outline"
                            size="icon"
                            className="size-8"
                            onClick={() =>
                                table.setFilter('year', String(year + 1))
                            }
                        >
                            <ChevronRight className="size-4" />
                            <span className="sr-only">Next year</span>
                        </Button>
                    </div>
                    <DataTableSearch
                        value={table.search}
                        onChange={table.setSearch}
                        placeholder="Employee..."
                        className="w-full sm:w-60"
                    />
                </div>

                <div className="overflow-x-auto rounded-md border">
                    <table className="w-full text-sm">
                        <thead className="bg-muted/50">
                            <tr>
                                <th className="px-3 py-2 text-left font-medium">
                                    Employee
                                </th>
                                {types.map((type) => (
                                    <th
                                        key={type.id}
                                        className="px-3 py-2 text-right font-medium"
                                    >
                                        {type.name}
                                        <div className="text-xs font-normal text-muted-foreground">
                                            available / total
                                        </div>
                                    </th>
                                ))}
                            </tr>
                        </thead>
                        <tbody>
                            {rows.map((row) => (
                                <tr key={row.employee.id} className="border-t">
                                    <td className="px-3 py-2">
                                        <div className="font-medium">
                                            {row.employee.name}
                                        </div>
                                        <div className="text-xs text-muted-foreground">
                                            {row.employee.employee_code}
                                        </div>
                                    </td>
                                    {types.map((type) => {
                                        const balance = row.balances[type.id];

                                        return (
                                            <td
                                                key={type.id}
                                                className="px-3 py-2 text-right tabular-nums"
                                            >
                                                <button
                                                    type="button"
                                                    disabled={!canAdjust}
                                                    onClick={() =>
                                                        adjustBalance(
                                                            balance,
                                                            type.name,
                                                            row.employee.name,
                                                        )
                                                    }
                                                    className="rounded px-1 enabled:hover:bg-muted"
                                                    title={`Used ${balance.used}, pending ${balance.pending}`}
                                                >
                                                    <span
                                                        className={
                                                            balance.available <=
                                                            0
                                                                ? 'font-semibold text-red-600'
                                                                : 'font-semibold'
                                                        }
                                                    >
                                                        {balance.available}
                                                    </span>{' '}
                                                    <span className="text-muted-foreground">
                                                        / {balance.total}
                                                    </span>
                                                </button>
                                            </td>
                                        );
                                    })}
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

LeaveBalances.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard().url },
        { title: 'Leave Balances', href: index().url },
    ],
};
