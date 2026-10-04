import { useForm } from '@inertiajs/react';
import type { FormEvent } from 'react';
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
import type { EmployeeOption, UnmatchedPin } from '@/types';
import { assign } from '@/actions/App/Http/Controllers/Devices/UnmatchedPunchController';

type AssignPinDialogProps = {
    unmatched: UnmatchedPin;
    employees: EmployeeOption[];
    onClose: () => void;
};

export function AssignPinDialog({
    unmatched,
    employees,
    onClose,
}: AssignPinDialogProps) {
    const { data, setData, transform, post, processing, errors } = useForm({
        employee_ids: [] as number[],
    });

    transform((values) => ({
        pin: unmatched.pin,
        employee_id: values.employee_ids[0] ?? null,
    }));

    function handleSubmit(e: FormEvent) {
        e.preventDefault();
        post(assign().url, { onSuccess: () => onClose() });
    }

    const fieldErrors = errors as Record<string, string | undefined>;

    return (
        <Dialog open onOpenChange={(isOpen) => !isOpen && onClose()}>
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Who is PIN {unmatched.pin}?</DialogTitle>
                    <DialogDescription>
                        The employee gets this device PIN. Any earlier scans
                        with it are added to their attendance.
                    </DialogDescription>
                </DialogHeader>
                <form onSubmit={handleSubmit} className="space-y-4">
                    <FormField
                        label="Employee"
                        htmlFor="pin-employee"
                        error={fieldErrors.employee_id ?? fieldErrors.pin}
                        hint="Only employees without a device PIN are listed."
                    >
                        <EmployeeMultiSelect
                            id="pin-employee"
                            single
                            employees={employees}
                            value={data.employee_ids}
                            onChange={(ids) => setData('employee_ids', ids)}
                            placeholder="Select employee..."
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
                        <Button
                            type="submit"
                            disabled={
                                processing || data.employee_ids.length === 0
                            }
                        >
                            Assign PIN
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}
