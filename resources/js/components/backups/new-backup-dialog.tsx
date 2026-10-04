import { useForm } from '@inertiajs/react';
import type { FormEvent } from 'react';
import { DatePicker } from '@/components/date-picker';
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
import { Label } from '@/components/ui/label';
import { ToggleGroup, ToggleGroupItem } from '@/components/ui/toggle-group';
import { toIsoDate } from '@/lib/dates';
import type { BackupDevice } from '@/types';
import { store } from '@/actions/App/Http/Controllers/Backups/DeviceBackupController';

export function NewBackupDialog({
    devices,
    deviceId,
    onClose,
}: {
    devices: BackupDevice[];
    deviceId?: number;
    onClose: () => void;
}) {
    const today = new Date();
    const monthAgo = new Date(today.getTime() - 30 * 86400000);

    const { data, setData, post, processing, errors } = useForm({
        device_id: String(deviceId ?? devices[0]?.id ?? ''),
        source: 'device',
        users: true,
        templates: true,
        logs: true,
        logs_from: toIsoDate(monthAgo),
        logs_to: toIsoDate(today),
    });

    function handleSubmit(e: FormEvent) {
        e.preventDefault();
        post(store().url, { preserveScroll: true, onSuccess: onClose });
    }

    const checkbox = (key: 'users' | 'templates' | 'logs', label: string) => (
        <div className="flex items-center gap-2">
            <Checkbox
                id={`backup-${key}`}
                checked={data[key]}
                onCheckedChange={(checked) => setData(key, checked === true)}
            />
            <Label htmlFor={`backup-${key}`}>{label}</Label>
        </div>
    );

    return (
        <Dialog open onOpenChange={(isOpen) => !isOpen && onClose()}>
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Back Up a Device</DialogTitle>
                    <DialogDescription>
                        Backup files are encrypted with this system's key.
                    </DialogDescription>
                </DialogHeader>
                <form onSubmit={handleSubmit} className="space-y-4">
                    <FormField
                        label="Device"
                        htmlFor="backup-device"
                        error={errors.device_id}
                        required
                    >
                        <OptionSelect
                            id="backup-device"
                            value={data.device_id}
                            onChange={(value) => setData('device_id', value)}
                            options={devices.map((device) => ({
                                value: String(device.id),
                                label: `${device.name} (${device.serial_number})`,
                            }))}
                        />
                    </FormField>
                    <FormField label="Read the data from" error={errors.source}>
                        <ToggleGroup
                            type="single"
                            variant="outline"
                            size="sm"
                            value={data.source}
                            onValueChange={(value) =>
                                value && setData('source', value)
                            }
                        >
                            <ToggleGroupItem value="device" className="px-3">
                                The device
                            </ToggleGroupItem>
                            <ToggleGroupItem value="server" className="px-3">
                                This app (instant)
                            </ToggleGroupItem>
                        </ToggleGroup>
                    </FormField>
                    <p className="text-xs text-muted-foreground">
                        {data.source === 'device'
                            ? 'The device uploads its users, templates and logs over the next few minutes. It must be online.'
                            : 'Built from the employees, templates and scans the app already holds for this device. Works even if the device is broken.'}
                    </p>
                    <div className="space-y-2">
                        {checkbox('users', 'Users')}
                        {checkbox('templates', 'Fingerprints and faces')}
                        {checkbox('logs', 'Attendance logs')}
                    </div>
                    {data.logs && (
                        <div className="grid gap-4 sm:grid-cols-2">
                            <FormField
                                label="Logs from"
                                htmlFor="backup-from"
                                error={errors.logs_from}
                            >
                                <DatePicker
                                    id="backup-from"
                                    value={data.logs_from}
                                    onChange={(value) =>
                                        setData('logs_from', value)
                                    }
                                />
                            </FormField>
                            <FormField
                                label="Logs to"
                                htmlFor="backup-to"
                                error={errors.logs_to}
                            >
                                <DatePicker
                                    id="backup-to"
                                    value={data.logs_to}
                                    onChange={(value) =>
                                        setData('logs_to', value)
                                    }
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
                        <Button type="submit" disabled={processing}>
                            Start Backup
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}
