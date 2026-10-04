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
import { Input } from '@/components/ui/input';
import { toIsoDate } from '@/lib/dates';
import type { PayrollRun } from '@/types';
import { markPaid } from '@/actions/App/Http/Controllers/Payroll/PayrollRunController';

type MarkPaidDialogProps = {
    run: PayrollRun;
    /** Only these payslips; omit for every unpaid payslip. */
    payslipIds?: number[];
    label: string;
    onClose: () => void;
};

export function MarkPaidDialog({
    run,
    payslipIds,
    label,
    onClose,
}: MarkPaidDialogProps) {
    const { data, setData, post, processing, errors } = useForm({
        payslip_ids: payslipIds ?? null,
        paid_on: toIsoDate(new Date()),
        reference: '',
    });

    function handleSubmit(e: FormEvent) {
        e.preventDefault();
        post(markPaid(run).url, { preserveScroll: true, onSuccess: onClose });
    }

    return (
        <Dialog open onOpenChange={(isOpen) => !isOpen && onClose()}>
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Record Payment</DialogTitle>
                    <DialogDescription>{label}</DialogDescription>
                </DialogHeader>
                <form onSubmit={handleSubmit} className="space-y-4">
                    <FormField
                        label="Paid on"
                        htmlFor="paid-on"
                        error={errors.paid_on}
                        required
                    >
                        <DatePicker
                            id="paid-on"
                            value={data.paid_on}
                            onChange={(value) => setData('paid_on', value)}
                        />
                    </FormField>
                    <FormField
                        label="Reference"
                        htmlFor="paid-ref"
                        error={errors.reference}
                        hint="Bank batch, cheque or voucher number."
                    >
                        <Input
                            id="paid-ref"
                            value={data.reference}
                            onChange={(e) =>
                                setData('reference', e.target.value)
                            }
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
                            Mark Paid
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}
