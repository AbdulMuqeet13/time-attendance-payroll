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
import type { Device } from '@/types';
import { action as deviceAction } from '@/actions/App/Http/Controllers/Devices/DeviceController';

type DeviceActionDialogProps = {
    device: Device;
    action: 'pull-logs' | 'clear-logs';
    onClose: () => void;
};

/**
 * Device actions that need input: a date range to re-read logs, or a typed confirmation to clear them.
 */
export function DeviceActionDialog({
    device,
    action,
    onClose,
}: DeviceActionDialogProps) {
    const today = new Date();
    const weekAgo = new Date(today.getTime() - 6 * 86400000);

    const { data, setData, post, processing, errors } = useForm({
        action,
        from: toIsoDate(weekAgo),
        to: toIsoDate(today),
        confirmation: '',
    });

    function handleSubmit(e: FormEvent) {
        e.preventDefault();
        post(deviceAction(device).url, { onSuccess: () => onClose() });
    }

    const isClear = action === 'clear-logs';

    return (
        <Dialog open onOpenChange={(isOpen) => !isOpen && onClose()}>
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>
                        {isClear
                            ? 'Clear Attendance Log on Device'
                            : 'Re-read Attendance Logs'}
                    </DialogTitle>
                    <DialogDescription>
                        {isClear
                            ? 'The device deletes every scan it holds. Scans already received stay in the app. Back up the device first if you are unsure.'
                            : 'The device uploads its scans for these dates again. Scans already stored are skipped, missing ones are added and attendance is rebuilt.'}
                    </DialogDescription>
                </DialogHeader>
                <form onSubmit={handleSubmit} className="space-y-4">
                    {isClear ? (
                        <FormField
                            label="Type CLEAR to confirm"
                            htmlFor="confirmation"
                            error={errors.confirmation}
                        >
                            <Input
                                id="confirmation"
                                value={data.confirmation}
                                onChange={(e) =>
                                    setData('confirmation', e.target.value)
                                }
                                autoComplete="off"
                            />
                        </FormField>
                    ) : (
                        <div className="grid gap-4 sm:grid-cols-2">
                            <FormField
                                label="From"
                                htmlFor="from"
                                error={errors.from}
                            >
                                <DatePicker
                                    id="from"
                                    value={data.from}
                                    onChange={(value) => setData('from', value)}
                                />
                            </FormField>
                            <FormField
                                label="To"
                                htmlFor="to"
                                error={errors.to}
                            >
                                <DatePicker
                                    id="to"
                                    value={data.to}
                                    onChange={(value) => setData('to', value)}
                                />
                            </FormField>
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
                        <Button
                            type="submit"
                            variant={isClear ? 'destructive' : 'default'}
                            disabled={processing}
                        >
                            {isClear ? 'Clear Log' : 'Re-read Logs'}
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}
