import { useForm } from '@inertiajs/react';
import type { FormEvent } from 'react';
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
import { Textarea } from '@/components/ui/textarea';
import { formatDate } from '@/lib/dates';
import type { LeaveRequest } from '@/types';
import {
    approve,
    cancel,
    reject,
} from '@/actions/App/Http/Controllers/Leaves/LeaveRequestController';

type LeaveDecisionDialogProps = {
    request: LeaveRequest;
    decision: 'approve' | 'reject' | 'cancel';
    onClose: () => void;
};

const COPY = {
    approve: {
        title: 'Approve Leave',
        button: 'Approve',
        noteLabel: 'Note (optional)',
    },
    reject: { title: 'Reject Leave', button: 'Reject', noteLabel: 'Reason' },
    cancel: {
        title: 'Cancel Leave',
        button: 'Cancel Leave',
        noteLabel: 'Reason (optional)',
    },
};

export function LeaveDecisionDialog({
    request,
    decision,
    onClose,
}: LeaveDecisionDialogProps) {
    const { data, setData, post, processing, errors } = useForm({ note: '' });
    const copy = COPY[decision];
    const url = { approve, reject, cancel }[decision](request).url;

    function handleSubmit(e: FormEvent) {
        e.preventDefault();
        post(url, { preserveScroll: true, onSuccess: onClose });
    }

    return (
        <Dialog open onOpenChange={(isOpen) => !isOpen && onClose()}>
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>{copy.title}</DialogTitle>
                    <DialogDescription>
                        {request.employee.name} · {request.leave_type.name} ·{' '}
                        {formatDate(request.start_date)}
                        {request.end_date !== request.start_date &&
                            ` to ${formatDate(request.end_date)}`}{' '}
                        ({request.days} days)
                    </DialogDescription>
                </DialogHeader>
                <form onSubmit={handleSubmit} className="space-y-4">
                    <FormField
                        label={copy.noteLabel}
                        htmlFor="decision-note"
                        error={errors.note}
                        required={decision === 'reject'}
                    >
                        <Textarea
                            id="decision-note"
                            rows={2}
                            value={data.note}
                            onChange={(e) => setData('note', e.target.value)}
                        />
                    </FormField>
                    <DialogFooter>
                        <Button
                            type="button"
                            variant="outline"
                            onClick={onClose}
                            disabled={processing}
                        >
                            Back
                        </Button>
                        <Button
                            type="submit"
                            variant={
                                decision === 'approve'
                                    ? 'default'
                                    : 'destructive'
                            }
                            disabled={processing}
                        >
                            {copy.button}
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}
