import { useForm } from '@inertiajs/react';
import type { FormEvent } from 'react';
import { DatePicker } from '@/components/date-picker';
import { EmployeeMultiSelect } from '@/components/employee-multi-select';
import { FormField } from '@/components/form-field';
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
import type { EmployeeOption } from '@/types';
import { store } from '@/actions/App/Http/Controllers/Payroll/SalaryAdvanceController';

export function AdvanceDialog({
    employees,
    onClose,
}: {
    employees: EmployeeOption[];
    onClose: () => void;
}) {
    const today = new Date();
    const nextMonth = new Date(today.getFullYear(), today.getMonth() + 1, 1);

    const { data, setData, transform, post, processing, errors } = useForm({
        employee_ids: [] as number[],
        amount: '',
        issued_on: toIsoDate(today),
        installment_amount: '',
        start_period: `${nextMonth.getFullYear()}-${String(nextMonth.getMonth() + 1).padStart(2, '0')}`,
        notes: '',
    });

    transform((values) => ({
        ...values,
        employee_id: values.employee_ids[0] ?? null,
    }));

    const months =
        Number(data.amount) > 0 && Number(data.installment_amount) > 0
            ? Math.ceil(Number(data.amount) / Number(data.installment_amount))
            : null;

    function handleSubmit(e: FormEvent) {
        e.preventDefault();
        post(store().url, { preserveScroll: true, onSuccess: onClose });
    }

    return (
        <Dialog open onOpenChange={(isOpen) => !isOpen && onClose()}>
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Record Advance / Loan</DialogTitle>
                    <DialogDescription>
                        Recovered from payroll in monthly installments, never
                        taking pay below zero.
                    </DialogDescription>
                </DialogHeader>
                <form onSubmit={handleSubmit} className="space-y-4">
                    <FormField
                        label="Employee"
                        htmlFor="advance-employee"
                        error={(errors as Record<string, string>).employee_id}
                        required
                    >
                        <EmployeeMultiSelect
                            id="advance-employee"
                            single
                            employees={employees}
                            value={data.employee_ids}
                            onChange={(ids) => setData('employee_ids', ids)}
                            placeholder="Select employee..."
                        />
                    </FormField>
                    <div className="grid gap-4 sm:grid-cols-2">
                        <FormField
                            label="Amount"
                            htmlFor="advance-amount"
                            error={errors.amount}
                            required
                        >
                            <Input
                                id="advance-amount"
                                type="number"
                                min="0"
                                step="0.01"
                                value={data.amount}
                                onChange={(e) =>
                                    setData('amount', e.target.value)
                                }
                            />
                        </FormField>
                        <FormField
                            label="Given on"
                            htmlFor="advance-issued"
                            error={errors.issued_on}
                            required
                        >
                            <DatePicker
                                id="advance-issued"
                                value={data.issued_on}
                                onChange={(value) =>
                                    setData('issued_on', value)
                                }
                            />
                        </FormField>
                        <FormField
                            label="Monthly installment"
                            htmlFor="advance-installment"
                            error={errors.installment_amount}
                            required
                            hint={
                                months ? `About ${months} month(s)` : undefined
                            }
                        >
                            <Input
                                id="advance-installment"
                                type="number"
                                min="0"
                                step="0.01"
                                value={data.installment_amount}
                                onChange={(e) =>
                                    setData(
                                        'installment_amount',
                                        e.target.value,
                                    )
                                }
                            />
                        </FormField>
                        <FormField
                            label="First installment"
                            htmlFor="advance-start"
                            error={errors.start_period}
                            required
                        >
                            <Input
                                id="advance-start"
                                type="month"
                                value={data.start_period}
                                onChange={(e) =>
                                    setData('start_period', e.target.value)
                                }
                            />
                        </FormField>
                    </div>
                    <FormField
                        label="Notes"
                        htmlFor="advance-notes"
                        error={errors.notes}
                    >
                        <Input
                            id="advance-notes"
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
                            Save
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}
