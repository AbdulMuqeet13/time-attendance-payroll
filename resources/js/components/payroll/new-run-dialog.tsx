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
import { Textarea } from '@/components/ui/textarea';
import type { Option } from '@/types';
import { store } from '@/actions/App/Http/Controllers/Payroll/PayrollRunController';

type NewRunDialogProps = {
    branches: Option[];
    defaultPeriod: { start: string; end: string };
    onClose: () => void;
};

export function NewRunDialog({
    branches,
    defaultPeriod,
    onClose,
}: NewRunDialogProps) {
    const { data, setData, transform, post, processing, errors } = useForm({
        period_start: defaultPeriod.start,
        period_end: defaultPeriod.end,
        branch_id: branches.length === 1 ? String(branches[0].id) : '',
        notes: '',
    });

    transform((values) => ({ ...values, branch_id: values.branch_id || null }));

    function handleSubmit(e: FormEvent) {
        e.preventDefault();
        post(store().url);
    }

    return (
        <Dialog open onOpenChange={(isOpen) => !isOpen && onClose()}>
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>New Payroll Run</DialogTitle>
                    <DialogDescription>
                        Attendance for the period is recalculated first, then a
                        draft payslip is built for everyone on the payroll.
                        Nothing is final until the run is approved.
                    </DialogDescription>
                </DialogHeader>
                <form onSubmit={handleSubmit} className="space-y-4">
                    <div className="grid gap-4 sm:grid-cols-2">
                        <FormField
                            label="From"
                            htmlFor="run-start"
                            error={errors.period_start}
                            required
                        >
                            <DatePicker
                                id="run-start"
                                value={data.period_start}
                                onChange={(value) =>
                                    setData('period_start', value)
                                }
                            />
                        </FormField>
                        <FormField
                            label="To"
                            htmlFor="run-end"
                            error={errors.period_end}
                            required
                        >
                            <DatePicker
                                id="run-end"
                                value={data.period_end}
                                onChange={(value) =>
                                    setData('period_end', value)
                                }
                            />
                        </FormField>
                    </div>
                    <FormField
                        label="Branch"
                        htmlFor="run-branch"
                        error={errors.branch_id}
                    >
                        <OptionSelect
                            id="run-branch"
                            value={data.branch_id}
                            onChange={(value) => setData('branch_id', value)}
                            options={toOptions(branches)}
                            noneLabel={
                                branches.length > 1 ? 'All branches' : undefined
                            }
                            placeholder="All branches"
                        />
                    </FormField>
                    <FormField
                        label="Notes"
                        htmlFor="run-notes"
                        error={errors.notes}
                    >
                        <Textarea
                            id="run-notes"
                            rows={2}
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
                            {processing ? 'Calculating...' : 'Create Draft'}
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}
