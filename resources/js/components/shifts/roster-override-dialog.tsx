import { useForm } from '@inertiajs/react';
import type { FormEvent } from 'react';
import { DatePicker } from '@/components/date-picker';
import { EmployeeMultiSelect } from '@/components/employee-multi-select';
import { FormField } from '@/components/form-field';
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
import { shiftLabel } from '@/lib/shifts';
import type { EmployeeOption, ShiftOption } from '@/types';
import { store } from '@/actions/App/Http/Controllers/Shifts/RosterOverrideController';

type RosterOverrideDialogProps = {
    employees: EmployeeOption[];
    shifts: ShiftOption[];
    onClose: () => void;
};

export function RosterOverrideDialog({
    employees,
    shifts,
    onClose,
}: RosterOverrideDialogProps) {
    const { data, setData, transform, post, processing, errors } = useForm({
        employee_ids: [] as number[],
        date: toIsoDate(new Date()),
        shift_id: '',
        note: '',
    });

    transform((values) => ({
        employee_id: values.employee_ids[0] ?? null,
        date: values.date,
        shift_id: values.shift_id || null,
        note: values.note,
    }));

    function handleSubmit(e: FormEvent) {
        e.preventDefault();
        post(store().url, { onSuccess: () => onClose() });
    }

    return (
        <Dialog open onOpenChange={(isOpen) => !isOpen && onClose()}>
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Change One Day</DialogTitle>
                    <DialogDescription>
                        Swap a shift or give a day off for one date. It replaces
                        the employee's assigned shifts on that day.
                    </DialogDescription>
                </DialogHeader>
                <form onSubmit={handleSubmit} className="space-y-4">
                    <FormField
                        label="Employee"
                        htmlFor="override-employee"
                        error={(errors as Record<string, string>).employee_id}
                        required
                    >
                        <EmployeeMultiSelect
                            id="override-employee"
                            single
                            employees={employees}
                            value={data.employee_ids}
                            onChange={(ids) => setData('employee_ids', ids)}
                            placeholder="Select employee..."
                        />
                    </FormField>
                    <FormField
                        label="Date"
                        htmlFor="override-date"
                        error={errors.date}
                        required
                    >
                        <DatePicker
                            id="override-date"
                            value={data.date}
                            onChange={(value) => setData('date', value)}
                        />
                    </FormField>
                    <FormField
                        label="Works"
                        htmlFor="override-shift"
                        error={errors.shift_id}
                    >
                        <OptionSelect
                            id="override-shift"
                            value={data.shift_id}
                            onChange={(value) => setData('shift_id', value)}
                            options={shifts.map((shift) => ({
                                value: String(shift.id),
                                label: shiftLabel(shift),
                            }))}
                            noneLabel="Day off"
                            placeholder="Day off"
                        />
                    </FormField>
                    <FormField
                        label="Note"
                        htmlFor="override-note"
                        error={errors.note}
                    >
                        <Input
                            id="override-note"
                            placeholder="Swapped with Ali"
                            value={data.note}
                            onChange={(e) => setData('note', e.target.value)}
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
                            {processing ? 'Saving...' : 'Save'}
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}
