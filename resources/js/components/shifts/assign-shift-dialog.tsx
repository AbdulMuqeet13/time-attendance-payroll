import { useForm } from '@inertiajs/react';
import type { FormEvent } from 'react';
import { DatePicker } from '@/components/date-picker';
import { EmployeeMultiSelect } from '@/components/employee-multi-select';
import { FormField } from '@/components/form-field';
import { OptionSelect } from '@/components/option-select';
import { WeekdayPicker } from '@/components/shifts/weekday-picker';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { toIsoDate } from '@/lib/dates';
import { shiftLabel } from '@/lib/shifts';
import type { EmployeeOption, ShiftAssignment, ShiftOption } from '@/types';
import {
    store,
    update,
} from '@/actions/App/Http/Controllers/Shifts/ShiftAssignmentController';

type AssignShiftDialogProps = {
    /** Edit this assignment instead of creating new ones. */
    assignment?: ShiftAssignment | null;
    employees: EmployeeOption[];
    shifts: ShiftOption[];
    /** Pre-select employees, e.g. from an employee page. */
    employeeIds?: number[];
    onClose: () => void;
};

export function AssignShiftDialog({
    assignment = null,
    employees,
    shifts,
    employeeIds = [],
    onClose,
}: AssignShiftDialogProps) {
    const { data, setData, post, put, processing, errors } = useForm({
        employee_ids: assignment ? [assignment.employee_id] : employeeIds,
        shift_id: String(assignment?.shift_id ?? shifts[0]?.id ?? ''),
        days: assignment?.days ?? [1, 2, 3, 4, 5, 6],
        effective_from: assignment?.effective_from ?? toIsoDate(new Date()),
        effective_to: assignment?.effective_to ?? '',
    });

    function handleSubmit(e: FormEvent) {
        e.preventDefault();

        const options = { onSuccess: () => onClose() };

        if (assignment) {
            put(update(assignment).url, options);
        } else {
            post(store().url, options);
        }
    }

    return (
        <Dialog open onOpenChange={(isOpen) => !isOpen && onClose()}>
            <DialogContent className="sm:max-w-xl">
                <DialogHeader>
                    <DialogTitle>
                        {assignment
                            ? `Edit assignment · ${assignment.employee.name}`
                            : 'Assign Shift'}
                    </DialogTitle>
                    <DialogDescription>
                        Days without a shift count as weekly offs. To change
                        someone's shift from a date, end the old assignment the
                        day before.
                    </DialogDescription>
                </DialogHeader>
                <form onSubmit={handleSubmit} className="space-y-4">
                    {!assignment && (
                        <FormField
                            label="Employees"
                            htmlFor="employee_ids"
                            error={
                                errors.employee_ids ??
                                (errors as Record<string, string>)[
                                    'employee_ids.0'
                                ]
                            }
                            required
                        >
                            <EmployeeMultiSelect
                                id="employee_ids"
                                employees={employees}
                                value={data.employee_ids}
                                onChange={(ids) => setData('employee_ids', ids)}
                            />
                        </FormField>
                    )}
                    <FormField
                        label="Shift"
                        htmlFor="shift_id"
                        error={errors.shift_id}
                        required
                    >
                        <OptionSelect
                            id="shift_id"
                            value={data.shift_id}
                            onChange={(value) => setData('shift_id', value)}
                            options={shifts.map((shift) => ({
                                value: String(shift.id),
                                label: shiftLabel(shift),
                            }))}
                        />
                    </FormField>
                    <FormField
                        label="Working days"
                        error={errors.days}
                        hint="Leave all unselected for every day."
                    >
                        <WeekdayPicker
                            value={data.days}
                            onChange={(days) => setData('days', days)}
                        />
                    </FormField>
                    <div className="grid gap-4 sm:grid-cols-2">
                        <FormField
                            label="From"
                            htmlFor="effective_from"
                            error={errors.effective_from}
                            required
                        >
                            <DatePicker
                                id="effective_from"
                                value={data.effective_from}
                                onChange={(value) =>
                                    setData('effective_from', value)
                                }
                            />
                        </FormField>
                        <FormField
                            label="Until"
                            htmlFor="effective_to"
                            error={errors.effective_to}
                            hint="Leave blank for no end date."
                        >
                            <DatePicker
                                id="effective_to"
                                value={data.effective_to}
                                onChange={(value) =>
                                    setData('effective_to', value)
                                }
                                clearable
                            />
                        </FormField>
                    </div>
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
                            {processing
                                ? 'Saving...'
                                : assignment
                                  ? 'Save'
                                  : `Assign${data.employee_ids.length > 1 ? ` to ${data.employee_ids.length}` : ''}`}
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}
