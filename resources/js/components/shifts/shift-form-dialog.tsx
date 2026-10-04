import { useForm } from '@inertiajs/react';
import type { FormEvent } from 'react';
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
import { formatMinutes } from '@/lib/shifts';
import type { Shift, ShiftColor } from '@/types';
import {
    store,
    update,
} from '@/actions/App/Http/Controllers/Shifts/ShiftController';

export type ShiftPolicyDefaults = {
    late_grace_minutes: number;
    early_window_minutes: number;
    checkout_grace_minutes: number;
    half_day_minutes: number;
    min_overtime_minutes: number;
};

type ShiftFormDialogProps = {
    shift: Shift | null;
    defaults: ShiftPolicyDefaults;
    onClose: () => void;
};

const COLORS: ShiftColor[] = [
    'sky',
    'emerald',
    'amber',
    'violet',
    'rose',
    'slate',
];

const POLICY_FIELDS: {
    key: keyof ShiftPolicyDefaults;
    label: string;
    hint: string;
}[] = [
    {
        key: 'late_grace_minutes',
        label: 'Late after (min)',
        hint: 'Check-in later than start + this is late.',
    },
    {
        key: 'early_window_minutes',
        label: 'Early check-in window (min)',
        hint: 'Scans this long before start count for the shift.',
    },
    {
        key: 'checkout_grace_minutes',
        label: 'Check-out grace (min)',
        hint: 'Scans this long after the end still check out.',
    },
    {
        key: 'half_day_minutes',
        label: 'Half day below (min)',
        hint: 'Working less than this is a half day.',
    },
    {
        key: 'min_overtime_minutes',
        label: 'Minimum overtime (min)',
        hint: 'Extra time below this is not overtime.',
    },
];

function toMinutes(time: string): number {
    const [hours, minutes] = time.split(':').map(Number);

    return (hours || 0) * 60 + (minutes || 0);
}

export function ShiftFormDialog({
    shift,
    defaults,
    onClose,
}: ShiftFormDialogProps) {
    const { data, setData, post, put, processing, errors } = useForm({
        name: shift?.name ?? '',
        start_time: shift?.start_time ?? '09:00',
        end_time: shift?.end_time ?? '17:00',
        break_minutes: String(shift?.break_minutes ?? 60),
        late_grace_minutes: String(shift?.late_grace_minutes ?? ''),
        early_window_minutes: String(shift?.early_window_minutes ?? ''),
        checkout_grace_minutes: String(shift?.checkout_grace_minutes ?? ''),
        half_day_minutes: String(shift?.half_day_minutes ?? ''),
        min_overtime_minutes: String(shift?.min_overtime_minutes ?? ''),
        color: shift?.color ?? 'sky',
        is_active: shift?.is_active ?? true,
    });

    const start = toMinutes(data.start_time);
    const end = toMinutes(data.end_time);
    const span = end > start ? end - start : end + 1440 - start;
    const isOvernight = end <= start;

    function handleSubmit(e: FormEvent) {
        e.preventDefault();

        const options = { onSuccess: () => onClose() };

        if (shift) {
            put(update(shift).url, options);
        } else {
            post(store().url, options);
        }
    }

    return (
        <Dialog open onOpenChange={(isOpen) => !isOpen && onClose()}>
            <DialogContent className="sm:max-w-2xl">
                <DialogHeader>
                    <DialogTitle>
                        {shift ? 'Edit Shift' : 'Add Shift'}
                    </DialogTitle>
                    <DialogDescription>
                        An end time earlier than the start makes an overnight
                        shift; it belongs to the day it starts.
                    </DialogDescription>
                </DialogHeader>
                <form onSubmit={handleSubmit} className="space-y-4">
                    <div className="grid gap-4 sm:grid-cols-4">
                        <FormField
                            label="Name"
                            htmlFor="shift-name"
                            error={errors.name}
                            required
                            className="sm:col-span-2"
                        >
                            <Input
                                id="shift-name"
                                placeholder="Morning"
                                value={data.name}
                                onChange={(e) =>
                                    setData('name', e.target.value)
                                }
                                required
                            />
                        </FormField>
                        <FormField
                            label="Start"
                            htmlFor="shift-start"
                            error={errors.start_time}
                            required
                        >
                            <Input
                                id="shift-start"
                                type="time"
                                value={data.start_time}
                                onChange={(e) =>
                                    setData('start_time', e.target.value)
                                }
                                required
                            />
                        </FormField>
                        <FormField
                            label="End"
                            htmlFor="shift-end"
                            error={errors.end_time}
                            required
                        >
                            <Input
                                id="shift-end"
                                type="time"
                                value={data.end_time}
                                onChange={(e) =>
                                    setData('end_time', e.target.value)
                                }
                                required
                            />
                        </FormField>
                        <FormField
                            label="Unpaid break (min)"
                            htmlFor="shift-break"
                            error={errors.break_minutes}
                            required
                            className="sm:col-span-2"
                        >
                            <Input
                                id="shift-break"
                                type="number"
                                min="0"
                                value={data.break_minutes}
                                onChange={(e) =>
                                    setData('break_minutes', e.target.value)
                                }
                            />
                        </FormField>
                        <FormField
                            label="Colour"
                            htmlFor="shift-color"
                            error={errors.color}
                            className="sm:col-span-2"
                        >
                            <OptionSelect
                                id="shift-color"
                                value={data.color}
                                onChange={(value) =>
                                    setData('color', value as ShiftColor)
                                }
                                options={COLORS.map((color) => ({
                                    value: color,
                                    label:
                                        color.charAt(0).toUpperCase() +
                                        color.slice(1),
                                }))}
                            />
                        </FormField>
                    </div>
                    <p className="rounded-md bg-muted/50 p-2 text-sm">
                        {isOvernight ? 'Overnight · ' : ''}
                        {formatMinutes(span)} long, expected work{' '}
                        <strong>
                            {formatMinutes(
                                span - (Number(data.break_minutes) || 0),
                            )}
                        </strong>
                    </p>

                    <div>
                        <p className="mb-2 text-sm font-medium">
                            Rules for this shift
                        </p>
                        <p className="mb-3 text-xs text-muted-foreground">
                            Leave blank to use the company setting (shown as the
                            placeholder).
                        </p>
                        <div className="grid gap-4 sm:grid-cols-3">
                            {POLICY_FIELDS.map((field) => (
                                <FormField
                                    key={field.key}
                                    label={field.label}
                                    htmlFor={`shift-${field.key}`}
                                    error={errors[field.key]}
                                    hint={field.hint}
                                >
                                    <Input
                                        id={`shift-${field.key}`}
                                        type="number"
                                        min="0"
                                        placeholder={String(
                                            defaults[field.key],
                                        )}
                                        value={data[field.key]}
                                        onChange={(e) =>
                                            setData(field.key, e.target.value)
                                        }
                                    />
                                </FormField>
                            ))}
                        </div>
                    </div>

                    <div className="flex items-center gap-2">
                        <Checkbox
                            id="shift-active"
                            checked={data.is_active}
                            onCheckedChange={(checked) =>
                                setData('is_active', checked === true)
                            }
                        />
                        <Label htmlFor="shift-active">
                            Active (can be assigned)
                        </Label>
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
                            {processing ? 'Saving...' : 'Save'}
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}
