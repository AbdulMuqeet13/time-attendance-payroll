import { useForm } from '@inertiajs/react';
import type { FormEvent } from 'react';
import { DatePicker } from '@/components/date-picker';
import type { SalaryComponentInput } from '@/components/employees/salary-breakdown-fields';
import { SalaryBreakdownFields } from '@/components/employees/salary-breakdown-fields';
import { FormField } from '@/components/form-field';
import type { SelectOption } from '@/components/option-select';
import { OptionSelect } from '@/components/option-select';
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
import { toIsoDate } from '@/lib/dates';
import type { Employee, EmployeeSalary, SalaryComponent } from '@/types';
import { store } from '@/actions/App/Http/Controllers/Employees/EmployeeSalaryController';

type AddSalaryDialogProps = {
    employee: Employee;
    latestSalary: EmployeeSalary | null;
    salaryComponents: Pick<SalaryComponent, 'id' | 'name' | 'type'>[];
    changeTypes: SelectOption[];
    onClose: () => void;
};

/**
 * Adds an increment / decrement / revision. Pre-filled with the latest salary so only changes need typing.
 */
export function AddSalaryDialog({
    employee,
    latestSalary,
    salaryComponents,
    changeTypes,
    onClose,
}: AddSalaryDialogProps) {
    const amountFor = (componentId: number) =>
        latestSalary?.components.find(
            (component) => component.salary_component_id === componentId,
        )?.amount ?? '';

    const { data, setData, post, processing, errors } = useForm({
        effective_date: toIsoDate(new Date()),
        change_type: 'increment',
        remarks: '',
        components: salaryComponents.map((component) => ({
            salary_component_id: component.id,
            amount: amountFor(component.id),
        })) as SalaryComponentInput[],
    });

    function handleSubmit(e: FormEvent) {
        e.preventDefault();
        post(store(employee).url, { onSuccess: () => onClose() });
    }

    return (
        <Dialog open onOpenChange={(isOpen) => !isOpen && onClose()}>
            <DialogContent className="sm:max-w-3xl">
                <DialogHeader>
                    <DialogTitle>Add Increment / Revision</DialogTitle>
                    <DialogDescription>
                        Payroll uses the latest record effective on or before
                        the end of each pay period. Earlier records are kept.
                    </DialogDescription>
                </DialogHeader>
                <form onSubmit={handleSubmit} className="space-y-4">
                    <div className="grid gap-4 sm:grid-cols-3">
                        <FormField
                            label="Effective date"
                            htmlFor="effective_date"
                            error={errors.effective_date}
                            required
                        >
                            <DatePicker
                                id="effective_date"
                                value={data.effective_date}
                                onChange={(value) =>
                                    setData('effective_date', value)
                                }
                            />
                        </FormField>
                        <FormField
                            label="Type"
                            htmlFor="change_type"
                            error={errors.change_type}
                            required
                        >
                            <OptionSelect
                                id="change_type"
                                value={data.change_type}
                                onChange={(value) =>
                                    setData('change_type', value)
                                }
                                options={changeTypes}
                            />
                        </FormField>
                        <FormField
                            label="Remarks"
                            htmlFor="remarks"
                            error={errors.remarks}
                        >
                            <Input
                                id="remarks"
                                placeholder="Annual increment 2026"
                                value={data.remarks}
                                onChange={(e) =>
                                    setData('remarks', e.target.value)
                                }
                            />
                        </FormField>
                    </div>
                    <SalaryBreakdownFields
                        salaryComponents={salaryComponents}
                        value={data.components}
                        onChange={(value) => setData('components', value)}
                        errors={errors as Partial<Record<string, string>>}
                    />
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
                            {processing ? 'Saving...' : 'Save Salary'}
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}
