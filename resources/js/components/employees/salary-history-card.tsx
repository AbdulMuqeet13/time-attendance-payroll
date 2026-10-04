import { Plus, Trash2 } from 'lucide-react';
import { useState } from 'react';
import { ConfirmDialog } from '@/components/confirm-dialog';
import { StatusBadge, headline } from '@/components/status-badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { formatDate } from '@/lib/dates';
import { formatAmount } from '@/lib/utils';
import type { Employee, EmployeeSalary } from '@/types';
import { destroy } from '@/actions/App/Http/Controllers/Employees/EmployeeSalaryController';

type SalaryHistoryCardProps = {
    employee: Employee;
    salaries: EmployeeSalary[];
    currentSalaryId: number | null;
    canManage: boolean;
    onAdd: () => void;
};

export function SalaryHistoryCard({
    employee,
    salaries,
    currentSalaryId,
    canManage,
    onAdd,
}: SalaryHistoryCardProps) {
    const [deleting, setDeleting] = useState<EmployeeSalary | null>(null);
    const today = new Date().toISOString().slice(0, 10);
    const current = salaries.find((salary) => salary.id === currentSalaryId);

    return (
        <Card>
            <CardHeader className="flex flex-row items-start justify-between gap-4">
                <div className="space-y-1.5">
                    <CardTitle>Salary</CardTitle>
                    <CardDescription>
                        {current
                            ? `Current gross ${formatAmount(current.gross_salary)} · fixed deductions ${formatAmount(current.fixed_deductions)}`
                            : 'No salary in effect yet.'}
                    </CardDescription>
                </div>
                {canManage && (
                    <Button size="sm" onClick={onAdd}>
                        <Plus className="mr-2 size-4" />
                        Add Increment / Revision
                    </Button>
                )}
            </CardHeader>
            <CardContent className="space-y-4">
                {current && (
                    <div className="grid gap-2 sm:grid-cols-2 lg:grid-cols-4">
                        {current.components.map((component) => (
                            <div
                                key={component.id}
                                className="rounded-lg border p-3"
                            >
                                <div className="text-xs text-muted-foreground">
                                    {component.salary_component.name}
                                </div>
                                <div
                                    className={
                                        component.salary_component.type ===
                                        'deduction'
                                            ? 'font-semibold text-amber-700 dark:text-amber-400'
                                            : 'font-semibold'
                                    }
                                >
                                    {component.salary_component.type ===
                                        'deduction' && '− '}
                                    {formatAmount(component.amount)}
                                </div>
                            </div>
                        ))}
                    </div>
                )}

                <Table>
                    <TableHeader>
                        <TableRow>
                            <TableHead>Effective</TableHead>
                            <TableHead>Type</TableHead>
                            <TableHead className="text-right">Gross</TableHead>
                            <TableHead className="text-right">Change</TableHead>
                            <TableHead className="text-right">
                                Deductions
                            </TableHead>
                            <TableHead>Remarks</TableHead>
                            {canManage && <TableHead />}
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        {salaries.map((salary, index) => {
                            const previous = salaries[index + 1];
                            const change = previous
                                ? Number(salary.gross_salary) -
                                  Number(previous.gross_salary)
                                : null;

                            return (
                                <TableRow key={salary.id}>
                                    <TableCell>
                                        {formatDate(salary.effective_date)}
                                        {salary.effective_date > today && (
                                            <StatusBadge
                                                tone="info"
                                                className="ml-2"
                                            >
                                                Upcoming
                                            </StatusBadge>
                                        )}
                                    </TableCell>
                                    <TableCell>
                                        {headline(salary.change_type)}
                                    </TableCell>
                                    <TableCell className="text-right font-medium">
                                        {formatAmount(salary.gross_salary)}
                                    </TableCell>
                                    <TableCell className="text-right">
                                        {change === null ? (
                                            '—'
                                        ) : (
                                            <span
                                                className={
                                                    change >= 0
                                                        ? 'text-emerald-700 dark:text-emerald-400'
                                                        : 'text-red-700 dark:text-red-400'
                                                }
                                            >
                                                {change >= 0 ? '+' : '−'}
                                                {formatAmount(Math.abs(change))}
                                                {Number(previous.gross_salary) >
                                                    0 &&
                                                    ` (${((change / Number(previous.gross_salary)) * 100).toFixed(1)}%)`}
                                            </span>
                                        )}
                                    </TableCell>
                                    <TableCell className="text-right">
                                        {formatAmount(salary.fixed_deductions)}
                                    </TableCell>
                                    <TableCell className="max-w-48 truncate text-muted-foreground">
                                        {salary.remarks ?? '—'}
                                    </TableCell>
                                    {canManage && (
                                        <TableCell className="text-right">
                                            <Button
                                                variant="ghost"
                                                size="icon"
                                                className="size-8"
                                                onClick={() =>
                                                    setDeleting(salary)
                                                }
                                            >
                                                <Trash2 className="size-4" />
                                                <span className="sr-only">
                                                    Delete salary record
                                                </span>
                                            </Button>
                                        </TableCell>
                                    )}
                                </TableRow>
                            );
                        })}
                    </TableBody>
                </Table>
            </CardContent>

            {deleting && (
                <ConfirmDialog
                    open
                    onClose={() => setDeleting(null)}
                    title="Delete Salary Record"
                    description={`Delete the ${headline(deleting.change_type).toLowerCase()} effective ${formatDate(deleting.effective_date)}? Records used by payroll, or an employee's only record, cannot be deleted.`}
                    url={destroy([employee, deleting]).url}
                />
            )}
        </Card>
    );
}
