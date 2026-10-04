import { Head, Link } from '@inertiajs/react';
import { Pencil, Trash2 } from 'lucide-react';
import { useState } from 'react';
import { ConfirmDialog } from '@/components/confirm-dialog';
import { AddSalaryDialog } from '@/components/employees/add-salary-dialog';
import { BiometricsCard } from '@/components/employees/biometrics-card';
import { EmployeeProfileCard } from '@/components/employees/employee-profile-card';
import { EmploymentStatusBadge } from '@/components/employees/employment-status-badge';
import { SalaryHistoryCard } from '@/components/employees/salary-history-card';
import type { SelectOption } from '@/components/option-select';
import { Button } from '@/components/ui/button';
import type {
    Employee,
    EmployeeBiometrics,
    EmployeeSalary,
    SalaryComponent,
} from '@/types';
import {
    destroy,
    edit,
    index,
} from '@/actions/App/Http/Controllers/Employees/EmployeeController';
import { dashboard } from '@/routes';

type EmployeeShowPageProps = {
    employee: Employee;
    salaries: EmployeeSalary[] | null;
    currentSalaryId: number | null;
    salaryComponents?: Pick<SalaryComponent, 'id' | 'name' | 'type'>[];
    salaryChangeTypes: SelectOption[];
    biometrics: EmployeeBiometrics | null;
    can: {
        update: boolean;
        delete: boolean;
        manageSalary: boolean;
    };
};

export default function EmployeeShow({
    employee,
    salaries,
    currentSalaryId,
    salaryComponents = [],
    salaryChangeTypes,
    biometrics,
    can,
}: EmployeeShowPageProps) {
    const [isAddingSalary, setIsAddingSalary] = useState(false);
    const [isDeleting, setIsDeleting] = useState(false);

    return (
        <>
            <Head title={employee.name} />

            <div className="flex h-full flex-1 flex-col gap-4 rounded-xl p-4">
                <div className="flex flex-wrap items-start justify-between gap-4">
                    <div>
                        <div className="flex items-center gap-3">
                            <h1 className="text-xl font-semibold tracking-tight">
                                {employee.name}
                            </h1>
                            <EmploymentStatusBadge status={employee.status} />
                        </div>
                        <p className="text-sm text-muted-foreground">
                            <span className="font-mono">
                                {employee.employee_code}
                            </span>
                            {employee.designation &&
                                ` · ${employee.designation.name}`}
                            {employee.branch && ` · ${employee.branch.name}`}
                        </p>
                    </div>
                    <div className="flex gap-2">
                        {can.update && (
                            <Button variant="outline" size="sm" asChild>
                                <Link href={edit(employee).url}>
                                    <Pencil className="mr-2 size-4" />
                                    Edit
                                </Link>
                            </Button>
                        )}
                        {can.delete && (
                            <Button
                                variant="outline"
                                size="sm"
                                onClick={() => setIsDeleting(true)}
                            >
                                <Trash2 className="mr-2 size-4" />
                                Delete
                            </Button>
                        )}
                    </div>
                </div>

                <EmployeeProfileCard employee={employee} />

                {biometrics && (
                    <BiometricsCard
                        employee={employee}
                        biometrics={biometrics}
                    />
                )}

                {salaries && (
                    <SalaryHistoryCard
                        employee={employee}
                        salaries={salaries}
                        currentSalaryId={currentSalaryId}
                        canManage={can.manageSalary}
                        onAdd={() => setIsAddingSalary(true)}
                    />
                )}
            </div>

            {isAddingSalary && (
                <AddSalaryDialog
                    employee={employee}
                    latestSalary={salaries?.[0] ?? null}
                    salaryComponents={salaryComponents}
                    changeTypes={salaryChangeTypes}
                    onClose={() => setIsAddingSalary(false)}
                />
            )}

            {isDeleting && (
                <ConfirmDialog
                    open
                    onClose={() => setIsDeleting(false)}
                    title="Delete Employee"
                    description={
                        <>
                            Delete <strong>{employee.name}</strong>? Attendance
                            and payroll history are kept. To stop paying someone
                            who left, set their status and exit date instead.
                        </>
                    }
                    url={destroy(employee).url}
                />
            )}
        </>
    );
}

EmployeeShow.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard().url },
        { title: 'Employees', href: index().url },
        { title: 'Profile', href: index().url },
    ],
};
