import { Head } from '@inertiajs/react';
import { Download } from 'lucide-react';
import { DatePicker } from '@/components/date-picker';
import Heading from '@/components/heading';
import { OptionSelect, toOptions } from '@/components/option-select';
import { Button } from '@/components/ui/button';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { Tabs, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { useDataTable } from '@/hooks/use-data-table';
import type { Option } from '@/types';
import {
    exportMethod as exportReport,
    index,
} from '@/actions/App/Http/Controllers/Reports/ReportController';
import { dashboard } from '@/routes';

type ReportsPageProps = {
    report: 'attendance' | 'lates' | 'overtime' | 'leave';
    from: string;
    to: string;
    result: {
        columns: { key: string; label: string }[];
        rows: Record<string, string | number | null>[];
    };
    branches: Option[];
    departments: Option[];
};

const REPORTS = [
    { value: 'attendance', label: 'Attendance summary' },
    { value: 'lates', label: 'Lates & early leaving' },
    { value: 'overtime', label: 'Overtime' },
    { value: 'leave', label: 'Leave taken' },
];

export default function Reports({
    report,
    from,
    to,
    result,
    branches,
    departments,
}: ReportsPageProps) {
    const table = useDataTable({ only: ['result', 'report', 'from', 'to'] });
    const query = {
        report,
        from,
        to,
        branch_id: table.filters.branch_id,
        department_id: table.filters.department_id,
    } as Record<string, string | undefined>;

    return (
        <>
            <Head title="Reports" />
            <div className="flex h-full flex-1 flex-col gap-4 overflow-x-auto rounded-xl p-4">
                <Heading
                    title="Reports"
                    description="Per-employee summaries for any period. Payroll registers and bank sheets are on each payroll run."
                />

                <Tabs
                    value={report}
                    onValueChange={(value) => table.setFilter('report', value)}
                >
                    <TabsList>
                        {REPORTS.map((item) => (
                            <TabsTrigger key={item.value} value={item.value}>
                                {item.label}
                            </TabsTrigger>
                        ))}
                    </TabsList>
                </Tabs>

                <div className="flex flex-wrap items-center gap-2">
                    <DatePicker
                        size="sm"
                        className="w-[150px]"
                        value={from}
                        onChange={(value) =>
                            table.setFilter('from', value || undefined)
                        }
                    />
                    <span className="text-sm text-muted-foreground">to</span>
                    <DatePicker
                        size="sm"
                        className="w-[150px]"
                        value={to}
                        onChange={(value) =>
                            table.setFilter('to', value || undefined)
                        }
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
                    <div className="flex-1" />
                    <Button size="sm" variant="outline" asChild>
                        <a href={exportReport({ query }).url}>
                            <Download className="mr-2 size-4" /> Export Excel
                        </a>
                    </Button>
                </div>

                <div className="overflow-x-auto rounded-md border">
                    <Table>
                        <TableHeader>
                            <TableRow>
                                {result.columns.map((column, position) => (
                                    <TableHead
                                        key={column.key}
                                        className={
                                            position > 2
                                                ? 'text-right'
                                                : undefined
                                        }
                                    >
                                        {column.label}
                                    </TableHead>
                                ))}
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {result.rows.length === 0 && (
                                <TableRow>
                                    <TableCell
                                        colSpan={result.columns.length}
                                        className="h-24 text-center text-muted-foreground"
                                    >
                                        Nothing to report for this period.
                                    </TableCell>
                                </TableRow>
                            )}
                            {result.rows.map((row, rowIndex) => (
                                <TableRow key={`${row.code}-${rowIndex}`}>
                                    {result.columns.map((column, position) => (
                                        <TableCell
                                            key={column.key}
                                            className={
                                                position > 2
                                                    ? 'text-right tabular-nums'
                                                    : position === 1
                                                      ? 'font-medium'
                                                      : undefined
                                            }
                                        >
                                            {row[column.key] ?? '—'}
                                        </TableCell>
                                    ))}
                                </TableRow>
                            ))}
                        </TableBody>
                    </Table>
                </div>
            </div>
        </>
    );
}

Reports.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard().url },
        { title: 'Reports', href: index().url },
    ],
};
