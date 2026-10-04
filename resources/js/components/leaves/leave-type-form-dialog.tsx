import { useForm } from '@inertiajs/react';
import type { FormEvent } from 'react';
import { FormField } from '@/components/form-field';
import type { SelectOption } from '@/components/option-select';
import { OptionSelect } from '@/components/option-select';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import {
    Dialog,
    DialogContent,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import type { LeaveType } from '@/types';
import {
    store,
    update,
} from '@/actions/App/Http/Controllers/Leaves/LeaveTypeController';

type LeaveTypeFormDialogProps = {
    leaveType: LeaveType | null;
    genders: SelectOption[];
    onClose: () => void;
};

export function LeaveTypeFormDialog({
    leaveType,
    genders,
    onClose,
}: LeaveTypeFormDialogProps) {
    const { data, setData, transform, post, put, processing, errors } = useForm(
        {
            name: leaveType?.name ?? '',
            code: leaveType?.code ?? '',
            is_paid: leaveType?.is_paid ?? true,
            yearly_quota: String(leaveType?.yearly_quota ?? '10'),
            carry_forward_max: String(leaveType?.carry_forward_max ?? '0'),
            allow_half_day: leaveType?.allow_half_day ?? true,
            requires_attachment: leaveType?.requires_attachment ?? false,
            gender: leaveType?.gender ?? '',
            is_active: leaveType?.is_active ?? true,
        },
    );

    transform((values) => ({ ...values, gender: values.gender || null }));

    function handleSubmit(e: FormEvent) {
        e.preventDefault();

        if (leaveType) {
            put(update(leaveType).url, { onSuccess: onClose });
        } else {
            post(store().url, { onSuccess: onClose });
        }
    }

    const checkbox = (
        key: 'is_paid' | 'allow_half_day' | 'requires_attachment' | 'is_active',
        label: string,
    ) => (
        <div className="flex items-center gap-2">
            <Checkbox
                id={`type-${key}`}
                checked={data[key]}
                onCheckedChange={(checked) => setData(key, checked === true)}
            />
            <Label htmlFor={`type-${key}`}>{label}</Label>
        </div>
    );

    return (
        <Dialog open onOpenChange={(isOpen) => !isOpen && onClose()}>
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>
                        {leaveType ? 'Edit Leave Type' : 'Add Leave Type'}
                    </DialogTitle>
                </DialogHeader>
                <form onSubmit={handleSubmit} className="space-y-4">
                    <div className="grid gap-4 sm:grid-cols-3">
                        <FormField
                            label="Name"
                            htmlFor="type-name"
                            error={errors.name}
                            required
                            className="sm:col-span-2"
                        >
                            <Input
                                id="type-name"
                                value={data.name}
                                onChange={(e) =>
                                    setData('name', e.target.value)
                                }
                                required
                            />
                        </FormField>
                        <FormField
                            label="Code"
                            htmlFor="type-code"
                            error={errors.code}
                            required
                        >
                            <Input
                                id="type-code"
                                value={data.code}
                                onChange={(e) =>
                                    setData('code', e.target.value)
                                }
                                required
                            />
                        </FormField>
                        <FormField
                            label="Days per year"
                            htmlFor="type-quota"
                            error={errors.yearly_quota}
                            hint="0 = no limit"
                        >
                            <Input
                                id="type-quota"
                                type="number"
                                min="0"
                                step="0.5"
                                value={data.yearly_quota}
                                onChange={(e) =>
                                    setData('yearly_quota', e.target.value)
                                }
                            />
                        </FormField>
                        <FormField
                            label="Carry forward up to"
                            htmlFor="type-carry"
                            error={errors.carry_forward_max}
                        >
                            <Input
                                id="type-carry"
                                type="number"
                                min="0"
                                step="0.5"
                                value={data.carry_forward_max}
                                onChange={(e) =>
                                    setData('carry_forward_max', e.target.value)
                                }
                            />
                        </FormField>
                        <FormField
                            label="Only for"
                            htmlFor="type-gender"
                            error={errors.gender}
                        >
                            <OptionSelect
                                id="type-gender"
                                value={data.gender}
                                onChange={(value) => setData('gender', value)}
                                options={genders}
                                noneLabel="Everyone"
                                placeholder="Everyone"
                            />
                        </FormField>
                    </div>
                    <div className="grid gap-2 sm:grid-cols-2">
                        {checkbox('is_paid', 'Paid leave')}
                        {checkbox('allow_half_day', 'Half days allowed')}
                        {checkbox('requires_attachment', 'Document required')}
                        {checkbox('is_active', 'Active')}
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
                            Save
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}
