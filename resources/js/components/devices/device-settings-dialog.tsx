import { useForm } from '@inertiajs/react';
import type { FormEvent } from 'react';
import { FormField } from '@/components/form-field';
import { OptionSelect, toOptions } from '@/components/option-select';
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
import type { Device, Option } from '@/types';
import { update } from '@/actions/App/Http/Controllers/Devices/DeviceController';

type DeviceSettingsDialogProps = {
    device: Device;
    branches: Option[];
    onClose: () => void;
};

export function DeviceSettingsDialog({
    device,
    branches,
    onClose,
}: DeviceSettingsDialogProps) {
    const isClaiming = device.branch_id === null;

    const { data, setData, transform, put, processing, errors } = useForm({
        name: isClaiming ? '' : device.name,
        branch_id: String(device.branch_id ?? ''),
        is_active: device.is_active,
        auto_backup: device.auto_backup,
        backup_retention: String(device.backup_retention),
    });

    transform((values) => ({
        ...values,
        branch_id: values.branch_id || null,
    }));

    function handleSubmit(e: FormEvent) {
        e.preventDefault();
        put(update(device).url, { onSuccess: () => onClose() });
    }

    return (
        <Dialog open onOpenChange={(isOpen) => !isOpen && onClose()}>
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>
                        {isClaiming ? 'Claim Device' : 'Device Settings'}
                    </DialogTitle>
                    <DialogDescription>
                        Serial number{' '}
                        <span className="font-mono">
                            {device.serial_number}
                        </span>
                        .{' '}
                        {isClaiming &&
                            'Scans are only recorded once the device belongs to a branch.'}
                    </DialogDescription>
                </DialogHeader>
                <form onSubmit={handleSubmit} className="space-y-4">
                    <FormField
                        label="Name"
                        htmlFor="device-name"
                        error={errors.name}
                        required
                    >
                        <Input
                            id="device-name"
                            placeholder="Main gate"
                            value={data.name}
                            onChange={(e) => setData('name', e.target.value)}
                            required
                        />
                    </FormField>
                    <FormField
                        label="Branch"
                        htmlFor="device-branch"
                        error={errors.branch_id}
                        required={isClaiming}
                    >
                        <OptionSelect
                            id="device-branch"
                            value={data.branch_id}
                            onChange={(value) => setData('branch_id', value)}
                            options={toOptions(branches)}
                            noneLabel={isClaiming ? undefined : 'Unclaimed'}
                        />
                    </FormField>
                    <div className="grid gap-4 sm:grid-cols-2">
                        <FormField
                            label="Automatic backup"
                            htmlFor="device-backup"
                            error={errors.auto_backup}
                        >
                            <OptionSelect
                                id="device-backup"
                                value={data.auto_backup}
                                onChange={(value) =>
                                    setData(
                                        'auto_backup',
                                        value as Device['auto_backup'],
                                    )
                                }
                                options={[
                                    { value: 'none', label: 'Off' },
                                    { value: 'daily', label: 'Daily' },
                                    { value: 'weekly', label: 'Weekly' },
                                ]}
                            />
                        </FormField>
                        <FormField
                            label="Keep last backups"
                            htmlFor="device-retention"
                            error={errors.backup_retention}
                        >
                            <Input
                                id="device-retention"
                                type="number"
                                min="1"
                                max="60"
                                value={data.backup_retention}
                                onChange={(e) =>
                                    setData('backup_retention', e.target.value)
                                }
                            />
                        </FormField>
                    </div>
                    <div className="flex items-center gap-2">
                        <Checkbox
                            id="device-active"
                            checked={data.is_active}
                            onCheckedChange={(checked) =>
                                setData('is_active', checked === true)
                            }
                        />
                        <Label htmlFor="device-active">
                            Enabled (record scans and send commands)
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
                            {processing
                                ? 'Saving...'
                                : isClaiming
                                  ? 'Claim Device'
                                  : 'Save'}
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}
