import { useForm } from '@inertiajs/react';
import type { FormEvent } from 'react';
import { DatePicker } from '@/components/date-picker';
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
import { rebuild } from '@/actions/App/Http/Controllers/Attendance/AttendanceAdjustmentController';

export function RebuildDialog({
    date,
    onClose,
}: {
    date: string;
    onClose: () => void;
}) {
    const { data, setData, post, processing, errors } = useForm({
        from: date,
        to: date,
    });

    function handleSubmit(e: FormEvent) {
        e.preventDefault();
        post(rebuild().url, { preserveScroll: true, onSuccess: onClose });
    }

    return (
        <Dialog open onOpenChange={(isOpen) => !isOpen && onClose()}>
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Recalculate Attendance</DialogTitle>
                    <DialogDescription>
                        Recalculates these days from the scans, roster, holidays
                        and leave, e.g. after changing shift rules. Corrections
                        made by managers are kept; days in an approved payroll
                        are left alone.
                    </DialogDescription>
                </DialogHeader>
                <form onSubmit={handleSubmit} className="space-y-4">
                    <div className="grid gap-4 sm:grid-cols-2">
                        <FormField
                            label="From"
                            htmlFor="rebuild-from"
                            error={errors.from}
                        >
                            <DatePicker
                                id="rebuild-from"
                                value={data.from}
                                onChange={(value) => setData('from', value)}
                            />
                        </FormField>
                        <FormField
                            label="To"
                            htmlFor="rebuild-to"
                            error={errors.to}
                        >
                            <DatePicker
                                id="rebuild-to"
                                value={data.to}
                                onChange={(value) => setData('to', value)}
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
                            Recalculate
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}
