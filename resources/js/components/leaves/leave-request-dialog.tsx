import { useForm } from '@inertiajs/react';
import type { FormEvent } from 'react';
import { DatePicker } from '@/components/date-picker';
import { EmployeeMultiSelect } from '@/components/employee-multi-select';
import { FormField } from '@/components/form-field';
import { OptionSelect } from '@/components/option-select';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import type { EmployeeOption, LeaveType } from '@/types';
import { store } from '@/actions/App/Http/Controllers/Leaves/LeaveRequestController';

type LeaveRequestDialogProps = {
    leaveTypes: LeaveType[];
    /** Omit for self-service: the leave is for the signed-in employee. */
    employees?: EmployeeOption[];
    canApprove?: boolean;
    onClose: () => void;
};

export function LeaveRequestDialog({
    leaveTypes,
    employees,
    canApprove = false,
    onClose,
}: LeaveRequestDialogProps) {
    const { data, setData, transform, post, processing, errors, progress } =
        useForm({
            employee_ids: [] as number[],
            leave_type_id: String(leaveTypes[0]?.id ?? ''),
            start_date: '',
            end_date: '',
            is_half_day: false,
            reason: '',
            attachment: null as File | null,
            approve: canApprove,
        });

    transform((values) => ({
        ...values,
        employee_id: values.employee_ids[0] ?? null,
        end_date: values.is_half_day ? values.start_date : values.end_date,
    }));

    const type = leaveTypes.find(
        (leaveType) => String(leaveType.id) === data.leave_type_id,
    );
    const fieldErrors = errors as Record<string, string | undefined>;

    function handleSubmit(e: FormEvent) {
        e.preventDefault();
        post(store().url, { forceFormData: true, onSuccess: onClose });
    }

    return (
        <Dialog open onOpenChange={(isOpen) => !isOpen && onClose()}>
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>
                        {employees ? 'Record Leave' : 'Apply for Leave'}
                    </DialogTitle>
                    <DialogDescription>
                        Weekly offs and holidays in the period are not counted.
                    </DialogDescription>
                </DialogHeader>
                <form onSubmit={handleSubmit} className="space-y-4">
                    {employees && (
                        <FormField
                            label="Employee"
                            htmlFor="leave-employee"
                            error={fieldErrors.employee_id}
                            required
                        >
                            <EmployeeMultiSelect
                                id="leave-employee"
                                single
                                employees={employees}
                                value={data.employee_ids}
                                onChange={(ids) => setData('employee_ids', ids)}
                                placeholder="Select employee..."
                            />
                        </FormField>
                    )}
                    <FormField
                        label="Leave type"
                        htmlFor="leave-type"
                        error={errors.leave_type_id}
                        required
                    >
                        <OptionSelect
                            id="leave-type"
                            value={data.leave_type_id}
                            onChange={(value) =>
                                setData('leave_type_id', value)
                            }
                            options={leaveTypes.map((leaveType) => ({
                                value: String(leaveType.id),
                                label: `${leaveType.name}${leaveType.is_paid ? '' : ' (unpaid)'}`,
                            }))}
                        />
                    </FormField>
                    {type?.allow_half_day && (
                        <div className="flex items-center gap-2">
                            <Checkbox
                                id="leave-half"
                                checked={data.is_half_day}
                                onCheckedChange={(checked) =>
                                    setData('is_half_day', checked === true)
                                }
                            />
                            <Label htmlFor="leave-half">Half day</Label>
                        </div>
                    )}
                    <div className="grid gap-4 sm:grid-cols-2">
                        <FormField
                            label={data.is_half_day ? 'Date' : 'From'}
                            htmlFor="leave-start"
                            error={errors.start_date}
                            required
                        >
                            <DatePicker
                                id="leave-start"
                                value={data.start_date}
                                onChange={(value) =>
                                    setData('start_date', value)
                                }
                            />
                        </FormField>
                        {!data.is_half_day && (
                            <FormField
                                label="To"
                                htmlFor="leave-end"
                                error={errors.end_date}
                                required
                            >
                                <DatePicker
                                    id="leave-end"
                                    value={data.end_date}
                                    onChange={(value) =>
                                        setData('end_date', value)
                                    }
                                />
                            </FormField>
                        )}
                    </div>
                    <FormField
                        label="Reason"
                        htmlFor="leave-reason"
                        error={errors.reason}
                    >
                        <Textarea
                            id="leave-reason"
                            rows={2}
                            value={data.reason}
                            onChange={(e) => setData('reason', e.target.value)}
                        />
                    </FormField>
                    <FormField
                        label="Supporting document"
                        htmlFor="leave-attachment"
                        error={fieldErrors.attachment}
                        required={type?.requires_attachment}
                        hint="PDF or image, up to 5 MB."
                    >
                        <Input
                            id="leave-attachment"
                            type="file"
                            accept=".pdf,.jpg,.jpeg,.png"
                            onChange={(e) =>
                                setData(
                                    'attachment',
                                    e.target.files?.[0] ?? null,
                                )
                            }
                        />
                    </FormField>
                    {progress && (
                        <progress
                            value={progress.percentage}
                            max="100"
                            className="w-full"
                        />
                    )}
                    {canApprove && (
                        <div className="flex items-center gap-2">
                            <Checkbox
                                id="leave-approve"
                                checked={data.approve}
                                onCheckedChange={(checked) =>
                                    setData('approve', checked === true)
                                }
                            />
                            <Label htmlFor="leave-approve">
                                Approve straight away
                            </Label>
                        </div>
                    )}
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
                            {employees ? 'Save' : 'Submit request'}
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}
