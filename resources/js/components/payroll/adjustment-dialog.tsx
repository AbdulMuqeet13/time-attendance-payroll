import { useForm } from '@inertiajs/react';
import type { FormEvent } from 'react';
import { EmployeeMultiSelect } from '@/components/employee-multi-select';
import { FormField } from '@/components/form-field';
import type { SelectOption } from '@/components/option-select';
import { OptionSelect, toOptions } from '@/components/option-select';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { ToggleGroup, ToggleGroupItem } from '@/components/ui/toggle-group';
import type { EmployeeOption, Option } from '@/types';
import { store } from '@/actions/App/Http/Controllers/Payroll/PayrollAdjustmentController';

type AdjustmentDialogProps = {
    month: string;
    kinds: SelectOption[];
    employees: EmployeeOption[];
    branches: Option[];
    departments: Option[];
    onClose: () => void;
};

export function AdjustmentDialog({
    month,
    kinds,
    employees,
    branches,
    departments,
    onClose,
}: AdjustmentDialogProps) {
    const { data, setData, transform, post, processing, errors } = useForm({
        kind: 'bonus',
        name: '',
        period: month,
        amount_type: 'fixed',
        amount: '',
        percent_of: 'basic',
        notes: '',
        scope: 'employees',
        employee_ids: [] as number[],
        branch_id: '',
        department_id: '',
    });

    transform((values) => ({
        ...values,
        branch_id: values.branch_id || null,
        department_id: values.department_id || null,
    }));

    const fieldErrors = errors as Record<string, string | undefined>;

    function handleSubmit(e: FormEvent) {
        e.preventDefault();
        post(store().url, { preserveScroll: true, onSuccess: onClose });
    }

    return (
        <Dialog open onOpenChange={(isOpen) => !isOpen && onClose()}>
            <DialogContent className="sm:max-w-xl">
                <DialogHeader>
                    <DialogTitle>Add Bonus / Deduction</DialogTitle>
                    <DialogDescription>
                        Paid or deducted in the payroll for that month.
                    </DialogDescription>
                </DialogHeader>
                <form onSubmit={handleSubmit} className="space-y-4">
                    <div className="grid gap-4 sm:grid-cols-3">
                        <FormField
                            label="Type"
                            htmlFor="adj-kind"
                            error={errors.kind}
                            required
                        >
                            <OptionSelect
                                id="adj-kind"
                                value={data.kind}
                                onChange={(value) => setData('kind', value)}
                                options={kinds}
                            />
                        </FormField>
                        <FormField
                            label="Description"
                            htmlFor="adj-name"
                            error={errors.name}
                            required
                            className="sm:col-span-2"
                        >
                            <Input
                                id="adj-name"
                                placeholder="Eid bonus"
                                value={data.name}
                                onChange={(e) =>
                                    setData('name', e.target.value)
                                }
                                required
                            />
                        </FormField>
                        <FormField
                            label="Month"
                            htmlFor="adj-period"
                            error={errors.period}
                            required
                        >
                            <Input
                                id="adj-period"
                                type="month"
                                value={data.period}
                                onChange={(e) =>
                                    setData('period', e.target.value)
                                }
                                required
                            />
                        </FormField>
                        <FormField
                            label="Amount"
                            htmlFor="adj-amount"
                            error={errors.amount}
                            required
                        >
                            <Input
                                id="adj-amount"
                                type="number"
                                min="0"
                                step="0.01"
                                value={data.amount}
                                onChange={(e) =>
                                    setData('amount', e.target.value)
                                }
                                required
                            />
                        </FormField>
                        <FormField
                            label="As"
                            htmlFor="adj-type"
                            error={errors.amount_type}
                        >
                            <OptionSelect
                                id="adj-type"
                                value={
                                    data.amount_type === 'fixed'
                                        ? 'fixed'
                                        : `percent_${data.percent_of}`
                                }
                                onChange={(value) => {
                                    setData((current) => ({
                                        ...current,
                                        amount_type:
                                            value === 'fixed'
                                                ? 'fixed'
                                                : 'percent',
                                        percent_of:
                                            value === 'percent_gross'
                                                ? 'gross'
                                                : 'basic',
                                    }));
                                }}
                                options={[
                                    { value: 'fixed', label: 'Fixed amount' },
                                    {
                                        value: 'percent_basic',
                                        label: '% of basic',
                                    },
                                    {
                                        value: 'percent_gross',
                                        label: '% of gross',
                                    },
                                ]}
                            />
                        </FormField>
                    </div>

                    <FormField label="For" error={fieldErrors.employee_ids}>
                        <ToggleGroup
                            type="single"
                            variant="outline"
                            size="sm"
                            value={data.scope}
                            onValueChange={(value) =>
                                value && setData('scope', value)
                            }
                        >
                            <ToggleGroupItem value="employees" className="px-3">
                                Selected employees
                            </ToggleGroupItem>
                            <ToggleGroupItem value="group" className="px-3">
                                Everyone in a branch / department
                            </ToggleGroupItem>
                        </ToggleGroup>
                    </FormField>
                    {data.scope === 'employees' ? (
                        <EmployeeMultiSelect
                            employees={employees}
                            value={data.employee_ids}
                            onChange={(ids) => setData('employee_ids', ids)}
                        />
                    ) : (
                        <div className="grid gap-4 sm:grid-cols-2">
                            <OptionSelect
                                value={data.branch_id}
                                onChange={(value) =>
                                    setData('branch_id', value)
                                }
                                options={toOptions(branches)}
                                noneLabel="All branches"
                                placeholder="All branches"
                            />
                            <OptionSelect
                                value={data.department_id}
                                onChange={(value) =>
                                    setData('department_id', value)
                                }
                                options={toOptions(departments)}
                                noneLabel="All departments"
                                placeholder="All departments"
                            />
                        </div>
                    )}
                    <FormField
                        label="Notes"
                        htmlFor="adj-notes"
                        error={errors.notes}
                    >
                        <Input
                            id="adj-notes"
                            value={data.notes}
                            onChange={(e) => setData('notes', e.target.value)}
                        />
                    </FormField>
                    <DialogFooter>
                        <Button
                            type="button"
                            variant="outline"
                            onClick={onClose}
                            disabled={processing}
                        >
                            Cancel
                        </Button>
                        <Button type="submit" disabled={processing}>
                            Add
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}
