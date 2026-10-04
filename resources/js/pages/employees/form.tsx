import { Head, Link, useForm } from '@inertiajs/react';
import type { FormEvent } from 'react';
import { DatePicker } from '@/components/date-picker';
import type { SalaryComponentInput } from '@/components/employees/salary-breakdown-fields';
import { SalaryBreakdownFields } from '@/components/employees/salary-breakdown-fields';
import { FormField } from '@/components/form-field';
import Heading from '@/components/heading';
import type { SelectOption } from '@/components/option-select';
import { OptionSelect, toOptions } from '@/components/option-select';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Textarea } from '@/components/ui/textarea';
import type {
    Employee,
    EmployeeOption,
    Option,
    SalaryComponent,
} from '@/types';
import {
    index,
    show,
    store,
    update,
} from '@/actions/App/Http/Controllers/Employees/EmployeeController';
import { dashboard } from '@/routes';

type EmployeeFormPageProps = {
    employee: Employee | null;
    nextEmployeeCode: string | null;
    salaryComponents: Pick<SalaryComponent, 'id' | 'name' | 'type'>[];
    branches: Option[];
    departments: Option[];
    designations: Option[];
    managers: EmployeeOption[];
    genders: SelectOption[];
    employmentTypes: SelectOption[];
    employmentStatuses: SelectOption[];
    paymentMethods: SelectOption[];
};

export default function EmployeeForm({
    employee,
    nextEmployeeCode,
    salaryComponents,
    branches,
    departments,
    designations,
    managers,
    genders,
    employmentTypes,
    employmentStatuses,
    paymentMethods,
}: EmployeeFormPageProps) {
    const isEditing = employee !== null;

    const { data, setData, post, put, processing, errors } = useForm({
        employee_code: employee?.employee_code ?? '',
        name: employee?.name ?? '',
        father_name: employee?.father_name ?? '',
        cnic: employee?.cnic ?? '',
        gender: employee?.gender ?? 'male',
        date_of_birth: employee?.date_of_birth ?? '',
        phone: employee?.phone ?? '',
        email: employee?.email ?? '',
        address: employee?.address ?? '',
        branch_id: String(
            employee?.branch_id ??
                (branches.length === 1 ? branches[0].id : ''),
        ),
        department_id: String(employee?.department_id ?? ''),
        designation_id: String(employee?.designation_id ?? ''),
        reports_to_id: String(employee?.reports_to_id ?? ''),
        employment_type: employee?.employment_type ?? 'permanent',
        status: employee?.status ?? 'active',
        joining_date: employee?.joining_date ?? '',
        confirmation_date: employee?.confirmation_date ?? '',
        exit_date: employee?.exit_date ?? '',
        payment_method: employee?.payment_method ?? 'bank',
        bank_name: employee?.bank_name ?? '',
        account_title: employee?.account_title ?? '',
        account_number: employee?.account_number ?? '',
        device_pin: employee?.device_pin ?? '',
        components: salaryComponents.map((component) => ({
            salary_component_id: component.id,
            amount: '',
        })) as SalaryComponentInput[],
    });

    const errorMap = errors as Partial<Record<string, string>>;

    function handleSubmit(e: FormEvent) {
        e.preventDefault();

        if (employee) {
            put(update(employee).url);
        } else {
            post(store().url);
        }
    }

    const showsExitDate =
        data.status === 'resigned' || data.status === 'terminated';

    return (
        <>
            <Head
                title={isEditing ? `Edit ${employee.name}` : 'Add Employee'}
            />

            <form
                onSubmit={handleSubmit}
                className="flex h-full flex-1 flex-col gap-4 rounded-xl p-4"
            >
                <Heading
                    title={isEditing ? `Edit ${employee.name}` : 'Add Employee'}
                    description={
                        isEditing
                            ? 'Salary changes are made from the employee page, so the history is kept.'
                            : 'The salary entered here becomes the initial salary record, effective from the joining date.'
                    }
                />

                <Card>
                    <CardHeader>
                        <CardTitle>Personal details</CardTitle>
                    </CardHeader>
                    <CardContent className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                        <FormField
                            label="Full name"
                            htmlFor="name"
                            error={errors.name}
                            required
                        >
                            <Input
                                id="name"
                                value={data.name}
                                onChange={(e) =>
                                    setData('name', e.target.value)
                                }
                                required
                            />
                        </FormField>
                        <FormField
                            label="Father's name"
                            htmlFor="father_name"
                            error={errors.father_name}
                        >
                            <Input
                                id="father_name"
                                value={data.father_name}
                                onChange={(e) =>
                                    setData('father_name', e.target.value)
                                }
                            />
                        </FormField>
                        <FormField
                            label="CNIC"
                            htmlFor="cnic"
                            error={errors.cnic}
                        >
                            <Input
                                id="cnic"
                                placeholder="35202-1234567-1"
                                value={data.cnic}
                                onChange={(e) =>
                                    setData('cnic', e.target.value)
                                }
                            />
                        </FormField>
                        <FormField
                            label="Gender"
                            htmlFor="gender"
                            error={errors.gender}
                            required
                        >
                            <OptionSelect
                                id="gender"
                                value={data.gender}
                                onChange={(value) =>
                                    setData(
                                        'gender',
                                        value as Employee['gender'],
                                    )
                                }
                                options={genders}
                            />
                        </FormField>
                        <FormField
                            label="Date of birth"
                            htmlFor="date_of_birth"
                            error={errors.date_of_birth}
                        >
                            <DatePicker
                                id="date_of_birth"
                                value={data.date_of_birth}
                                onChange={(value) =>
                                    setData('date_of_birth', value)
                                }
                                clearable
                            />
                        </FormField>
                        <FormField
                            label="Phone"
                            htmlFor="phone"
                            error={errors.phone}
                        >
                            <Input
                                id="phone"
                                value={data.phone}
                                onChange={(e) =>
                                    setData('phone', e.target.value)
                                }
                            />
                        </FormField>
                        <FormField
                            label="Email"
                            htmlFor="email"
                            error={errors.email}
                        >
                            <Input
                                id="email"
                                type="email"
                                value={data.email}
                                onChange={(e) =>
                                    setData('email', e.target.value)
                                }
                            />
                        </FormField>
                        <FormField
                            label="Address"
                            htmlFor="address"
                            error={errors.address}
                            className="sm:col-span-2"
                        >
                            <Textarea
                                id="address"
                                rows={2}
                                value={data.address}
                                onChange={(e) =>
                                    setData('address', e.target.value)
                                }
                            />
                        </FormField>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle>Employment</CardTitle>
                    </CardHeader>
                    <CardContent className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                        <FormField
                            label="Employee code"
                            htmlFor="employee_code"
                            error={errors.employee_code}
                            required={isEditing}
                            hint={
                                isEditing
                                    ? undefined
                                    : `Leave blank to use ${nextEmployeeCode}.`
                            }
                        >
                            <Input
                                id="employee_code"
                                placeholder={nextEmployeeCode ?? ''}
                                value={data.employee_code}
                                onChange={(e) =>
                                    setData('employee_code', e.target.value)
                                }
                            />
                        </FormField>
                        <FormField
                            label="Branch"
                            htmlFor="branch_id"
                            error={errors.branch_id}
                            required
                        >
                            <OptionSelect
                                id="branch_id"
                                value={data.branch_id}
                                onChange={(value) =>
                                    setData('branch_id', value)
                                }
                                options={toOptions(branches)}
                            />
                        </FormField>
                        <FormField
                            label="Department"
                            htmlFor="department_id"
                            error={errors.department_id}
                        >
                            <OptionSelect
                                id="department_id"
                                value={data.department_id}
                                onChange={(value) =>
                                    setData('department_id', value)
                                }
                                options={toOptions(departments)}
                                noneLabel="None"
                            />
                        </FormField>
                        <FormField
                            label="Designation"
                            htmlFor="designation_id"
                            error={errors.designation_id}
                        >
                            <OptionSelect
                                id="designation_id"
                                value={data.designation_id}
                                onChange={(value) =>
                                    setData('designation_id', value)
                                }
                                options={toOptions(designations)}
                                noneLabel="None"
                            />
                        </FormField>
                        <FormField
                            label="Reports to"
                            htmlFor="reports_to_id"
                            error={errors.reports_to_id}
                        >
                            <OptionSelect
                                id="reports_to_id"
                                value={data.reports_to_id}
                                onChange={(value) =>
                                    setData('reports_to_id', value)
                                }
                                options={managers.map((manager) => ({
                                    value: String(manager.id),
                                    label: `${manager.name} (${manager.employee_code})`,
                                }))}
                                noneLabel="Nobody"
                            />
                        </FormField>
                        <FormField
                            label="Employment type"
                            htmlFor="employment_type"
                            error={errors.employment_type}
                            required
                        >
                            <OptionSelect
                                id="employment_type"
                                value={data.employment_type}
                                onChange={(value) =>
                                    setData(
                                        'employment_type',
                                        value as Employee['employment_type'],
                                    )
                                }
                                options={employmentTypes}
                            />
                        </FormField>
                        <FormField
                            label="Joining date"
                            htmlFor="joining_date"
                            error={errors.joining_date}
                            required
                        >
                            <DatePicker
                                id="joining_date"
                                value={data.joining_date}
                                onChange={(value) =>
                                    setData('joining_date', value)
                                }
                            />
                        </FormField>
                        <FormField
                            label="Confirmation date"
                            htmlFor="confirmation_date"
                            error={errors.confirmation_date}
                        >
                            <DatePicker
                                id="confirmation_date"
                                value={data.confirmation_date}
                                onChange={(value) =>
                                    setData('confirmation_date', value)
                                }
                                clearable
                            />
                        </FormField>
                        <FormField
                            label="Status"
                            htmlFor="status"
                            error={errors.status}
                            required
                        >
                            <OptionSelect
                                id="status"
                                value={data.status}
                                onChange={(value) =>
                                    setData(
                                        'status',
                                        value as Employee['status'],
                                    )
                                }
                                options={employmentStatuses}
                            />
                        </FormField>
                        {(showsExitDate || data.exit_date) && (
                            <FormField
                                label="Exit date"
                                htmlFor="exit_date"
                                error={errors.exit_date}
                                required={showsExitDate}
                                hint="Last working day. Payroll stops after it."
                            >
                                <DatePicker
                                    id="exit_date"
                                    value={data.exit_date}
                                    onChange={(value) =>
                                        setData('exit_date', value)
                                    }
                                    clearable
                                />
                            </FormField>
                        )}
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle>Payment & device</CardTitle>
                        <CardDescription>
                            The device PIN is the user ID on every ZKTeco
                            device. Scans with this PIN are recorded for this
                            employee.
                        </CardDescription>
                    </CardHeader>
                    <CardContent className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                        <FormField
                            label="Payment method"
                            htmlFor="payment_method"
                            error={errors.payment_method}
                            required
                        >
                            <OptionSelect
                                id="payment_method"
                                value={data.payment_method}
                                onChange={(value) =>
                                    setData(
                                        'payment_method',
                                        value as Employee['payment_method'],
                                    )
                                }
                                options={paymentMethods}
                            />
                        </FormField>
                        {data.payment_method === 'bank' && (
                            <>
                                <FormField
                                    label="Bank"
                                    htmlFor="bank_name"
                                    error={errors.bank_name}
                                    required
                                >
                                    <Input
                                        id="bank_name"
                                        value={data.bank_name}
                                        onChange={(e) =>
                                            setData('bank_name', e.target.value)
                                        }
                                    />
                                </FormField>
                                <FormField
                                    label="Account title"
                                    htmlFor="account_title"
                                    error={errors.account_title}
                                >
                                    <Input
                                        id="account_title"
                                        value={data.account_title}
                                        onChange={(e) =>
                                            setData(
                                                'account_title',
                                                e.target.value,
                                            )
                                        }
                                    />
                                </FormField>
                                <FormField
                                    label="Account / IBAN"
                                    htmlFor="account_number"
                                    error={errors.account_number}
                                    required
                                >
                                    <Input
                                        id="account_number"
                                        value={data.account_number}
                                        onChange={(e) =>
                                            setData(
                                                'account_number',
                                                e.target.value,
                                            )
                                        }
                                    />
                                </FormField>
                            </>
                        )}
                        <FormField
                            label="Device PIN"
                            htmlFor="device_pin"
                            error={errors.device_pin}
                            hint="Up to 14 letters or digits."
                        >
                            <Input
                                id="device_pin"
                                className="font-mono"
                                value={data.device_pin}
                                onChange={(e) =>
                                    setData('device_pin', e.target.value)
                                }
                            />
                        </FormField>
                    </CardContent>
                </Card>

                {!isEditing && (
                    <Card>
                        <CardHeader>
                            <CardTitle>Monthly salary</CardTitle>
                            <CardDescription>
                                Leave components blank if they don't apply. At
                                least one earning needs an amount.
                            </CardDescription>
                        </CardHeader>
                        <CardContent>
                            <SalaryBreakdownFields
                                salaryComponents={salaryComponents}
                                value={data.components}
                                onChange={(value) =>
                                    setData('components', value)
                                }
                                errors={errorMap}
                            />
                        </CardContent>
                    </Card>
                )}

                <div className="flex justify-end gap-2">
                    <Button variant="outline" asChild>
                        <Link
                            href={employee ? show(employee).url : index().url}
                        >
                            Cancel
                        </Link>
                    </Button>
                    <Button type="submit" disabled={processing}>
                        {processing
                            ? 'Saving...'
                            : isEditing
                              ? 'Save Changes'
                              : 'Add Employee'}
                    </Button>
                </div>
            </form>
        </>
    );
}

EmployeeForm.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard().url },
        { title: 'Employees', href: index().url },
        { title: 'Employee', href: index().url },
    ],
};
