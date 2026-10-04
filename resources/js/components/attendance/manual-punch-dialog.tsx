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
import type { EmployeeOption } from '@/types';
import { storePunch } from '@/actions/App/Http/Controllers/Attendance/AttendanceAdjustmentController';

type ManualPunchDialogProps = {
    employees: EmployeeOption[];
    employeeId?: number;
    date: string;
    onClose: () => void;
};

/**
 * Add a scan the device missed. Attendance for that day is rebuilt straight away.
 */
export function ManualPunchDialog({
    employees,
    employeeId,
    date,
    onClose,
}: ManualPunchDialogProps) {
    const { data, setData, transform, post, processing, errors } = useForm({
        employee_ids: employeeId ? [employeeId] : ([] as number[]),
        date,
        time: '09:00',
        reason: '',
    });

    transform((values) => ({
        employee_id: values.employee_ids[0] ?? null,
        punched_at: `${values.date} ${values.time}`,
        reason: values.reason,
    }));

    function handleSubmit(e: FormEvent) {
        e.preventDefault();
        post(storePunch().url, {
            preserveScroll: true,
            onSuccess: () => onClose(),
        });
    }

    const fieldErrors = errors as Record<string, string | undefined>;

    return (
        <Dialog open onOpenChange={(isOpen) => !isOpen && onClose()}>
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Add Missed Punch</DialogTitle>
                    <DialogDescription>
                        Use when someone forgot to scan or the device was down.
                        It is matched to their shift like a device scan.
                    </DialogDescription>
                </DialogHeader>
                <form onSubmit={handleSubmit} className="space-y-4">
                    {!employeeId && (
                        <FormField
                            label="Employee"
                            htmlFor="punch-employee"
                            error={fieldErrors.employee_id}
                            required
                        >
                            <EmployeeMultiSelect
                                id="punch-employee"
                                single
                                employees={employees}
                                value={data.employee_ids}
                                onChange={(ids) => setData('employee_ids', ids)}
                                placeholder="Select employee..."
                            />
                        </FormField>
                    )}
                    <div className="grid gap-4 sm:grid-cols-2">
                        <FormField
                            label="Date"
                            htmlFor="punch-date"
                            error={fieldErrors.punched_at}
                            required
                        >
                            <DatePicker
                                id="punch-date"
                                value={data.date}
                                onChange={(value) => setData('date', value)}
                            />
                        </FormField>
                        <FormField label="Time" htmlFor="punch-time" required>
                            <Input
                                id="punch-time"
                                type="time"
                                value={data.time}
                                onChange={(e) =>
                                    setData('time', e.target.value)
                                }
                                required
                            />
                        </FormField>
                    </div>
                    <FormField
                        label="Reason"
                        htmlFor="punch-reason"
                        error={errors.reason}
                        required
                    >
                        <Input
                            id="punch-reason"
                            placeholder="Forgot to scan out"
                            value={data.reason}
                            onChange={(e) => setData('reason', e.target.value)}
                            required
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
                            Add Punch
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}
