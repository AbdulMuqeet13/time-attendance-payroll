import { useForm } from '@inertiajs/react';
import { AlertTriangle } from 'lucide-react';
import type { FormEvent } from 'react';
import { FormField } from '@/components/form-field';
import { OptionSelect } from '@/components/option-select';
import { Alert, AlertDescription } from '@/components/ui/alert';
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
import { formatDateTime } from '@/lib/dates';
import type { BackupDevice, DeviceBackup } from '@/types';
import { restore } from '@/actions/App/Http/Controllers/Backups/DeviceBackupController';

type RestoreDialogProps = {
    /** null = restore from the app's current employees and templates. */
    backup: DeviceBackup | null;
    devices: BackupDevice[];
    onClose: () => void;
};

export function RestoreDialog({
    backup,
    devices,
    onClose,
}: RestoreDialogProps) {
    const { data, setData, transform, post, processing, errors } = useForm({
        target_device_id: String(backup?.device_id ?? devices[0]?.id ?? ''),
        users: true,
        templates: true,
        clear_first: false,
        confirmation: '',
    });

    transform((values) => ({ ...values, backup_id: backup?.id ?? null }));

    const target = devices.find(
        (device) => String(device.id) === data.target_device_id,
    );
    const algorithmMismatch =
        backup &&
        target &&
        backup.fp_algorithm &&
        target.fp_algorithm &&
        Number(backup.fp_algorithm) !== Number(target.fp_algorithm);

    function handleSubmit(e: FormEvent) {
        e.preventDefault();
        post(restore().url, { preserveScroll: true, onSuccess: onClose });
    }

    return (
        <Dialog open onOpenChange={(isOpen) => !isOpen && onClose()}>
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Restore to a Device</DialogTitle>
                    <DialogDescription>
                        {backup
                            ? `From the backup of ${backup.device_name} taken ${formatDateTime(backup.created_at)}.`
                            : "From the app's current employees (with device PINs) and their stored fingerprints and faces."}{' '}
                        Attendance logs can't be written to a device; use
                        "Import logs" to add them to the app.
                    </DialogDescription>
                </DialogHeader>
                <form onSubmit={handleSubmit} className="space-y-4">
                    <FormField
                        label="Target device"
                        htmlFor="restore-target"
                        error={errors.target_device_id}
                        required
                    >
                        <OptionSelect
                            id="restore-target"
                            value={data.target_device_id}
                            onChange={(value) =>
                                setData('target_device_id', value)
                            }
                            options={devices.map((device) => ({
                                value: String(device.id),
                                label: `${device.name} (${device.serial_number})`,
                            }))}
                        />
                    </FormField>
                    {algorithmMismatch && (
                        <Alert>
                            <AlertTriangle className="size-4" />
                            <AlertDescription>
                                The backup's fingerprints were made with
                                algorithm v{backup.fp_algorithm} but this device
                                uses v{target.fp_algorithm}. Those templates
                                will be skipped; users must re-enrol.
                            </AlertDescription>
                        </Alert>
                    )}
                    <div className="space-y-2">
                        {(['users', 'templates'] as const).map((key) => (
                            <div key={key} className="flex items-center gap-2">
                                <Checkbox
                                    id={`restore-${key}`}
                                    checked={data[key]}
                                    onCheckedChange={(checked) =>
                                        setData(key, checked === true)
                                    }
                                />
                                <Label htmlFor={`restore-${key}`}>
                                    {key === 'users'
                                        ? 'Users'
                                        : 'Fingerprints and faces'}
                                </Label>
                            </div>
                        ))}
                        <div className="flex items-center gap-2">
                            <Checkbox
                                id="restore-clear"
                                checked={data.clear_first}
                                onCheckedChange={(checked) =>
                                    setData('clear_first', checked === true)
                                }
                            />
                            <Label htmlFor="restore-clear">
                                Wipe the device first (users, biometrics and
                                logs)
                            </Label>
                        </div>
                    </div>
                    {data.clear_first && (
                        <FormField
                            label="Type CLEAR to confirm"
                            htmlFor="restore-confirm"
                            error={errors.confirmation}
                        >
                            <Input
                                id="restore-confirm"
                                value={data.confirmation}
                                onChange={(e) =>
                                    setData('confirmation', e.target.value)
                                }
                                autoComplete="off"
                            />
                        </FormField>
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
                            variant={
                                data.clear_first ? 'destructive' : 'default'
                            }
                            disabled={processing || devices.length === 0}
                        >
                            Start Restore
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}
