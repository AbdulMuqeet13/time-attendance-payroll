import { useForm } from '@inertiajs/react';
import type { FormEvent } from 'react';
import { DatePicker } from '@/components/date-picker';
import { FormField } from '@/components/form-field';
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
import type { Holiday, Option } from '@/types';
import {
    store,
    update,
} from '@/actions/App/Http/Controllers/Shifts/HolidayController';

type HolidayFormDialogProps = {
    holiday: Holiday | null;
    branches: Option[];
    onClose: () => void;
};

export function HolidayFormDialog({
    holiday,
    branches,
    onClose,
}: HolidayFormDialogProps) {
    const { data, setData, transform, post, put, processing, errors } = useForm(
        {
            date: holiday?.date ?? '',
            name: holiday?.name ?? '',
            branch_id: String(holiday?.branch_id ?? ''),
        },
    );

    transform((values) => ({
        ...values,
        branch_id: values.branch_id || null,
    }));

    function handleSubmit(e: FormEvent) {
        e.preventDefault();

        const options = { onSuccess: () => onClose() };

        if (holiday) {
            put(update(holiday).url, options);
        } else {
            post(store().url, options);
        }
    }

    return (
        <Dialog open onOpenChange={(isOpen) => !isOpen && onClose()}>
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>
                        {holiday ? 'Edit Holiday' : 'Add Holiday'}
                    </DialogTitle>
                    <DialogDescription>
                        Holidays are paid. Anyone who works on a holiday gets
                        the time as holiday overtime.
                    </DialogDescription>
                </DialogHeader>
                <form onSubmit={handleSubmit} className="space-y-4">
                    <FormField
                        label="Date"
                        htmlFor="holiday-date"
                        error={errors.date}
                        required
                    >
                        <DatePicker
                            id="holiday-date"
                            value={data.date}
                            onChange={(value) => setData('date', value)}
                        />
                    </FormField>
                    <FormField
                        label="Name"
                        htmlFor="holiday-name"
                        error={errors.name}
                        required
                    >
                        <Input
                            id="holiday-name"
                            placeholder="Pakistan Day"
                            value={data.name}
                            onChange={(e) => setData('name', e.target.value)}
                            required
                        />
                    </FormField>
                    <FormField
                        label="Branch"
                        htmlFor="holiday-branch"
                        error={errors.branch_id}
                    >
                        <OptionSelect
                            id="holiday-branch"
                            value={data.branch_id}
                            onChange={(value) => setData('branch_id', value)}
                            options={toOptions(branches)}
                            noneLabel="All branches"
                            placeholder="All branches"
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
