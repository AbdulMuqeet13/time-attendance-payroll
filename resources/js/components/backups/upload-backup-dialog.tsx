import { useForm } from '@inertiajs/react';
import type { FormEvent } from 'react';
import { FormField } from '@/components/form-field';
import { OptionSelect } from '@/components/option-select';
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
import type { BackupDevice } from '@/types';
import { upload } from '@/actions/App/Http/Controllers/Backups/DeviceBackupController';

export function UploadBackupDialog({
    devices,
    onClose,
}: {
    devices: BackupDevice[];
    onClose: () => void;
}) {
    const { data, setData, transform, post, processing, errors, progress } =
        useForm({
            file: null as File | null,
            device_id: '',
        });

    transform((values) => ({ ...values, device_id: values.device_id || null }));

    function handleSubmit(e: FormEvent) {
        e.preventDefault();
        post(upload().url, {
            forceFormData: true,
            preserveScroll: true,
            onSuccess: onClose,
        });
    }

    return (
        <Dialog open onOpenChange={(isOpen) => !isOpen && onClose()}>
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Upload Backup File</DialogTitle>
                    <DialogDescription>
                        A .zkb file downloaded from this system (same app key).
                    </DialogDescription>
                </DialogHeader>
                <form onSubmit={handleSubmit} className="space-y-4">
                    <FormField
                        label="Backup file"
                        htmlFor="upload-file"
                        error={errors.file}
                        required
                    >
                        <Input
                            id="upload-file"
                            type="file"
                            accept=".zkb"
                            onChange={(e) =>
                                setData('file', e.target.files?.[0] ?? null)
                            }
                        />
                    </FormField>
                    <FormField
                        label="Link to device"
                        htmlFor="upload-device"
                        error={errors.device_id}
                        hint="Optional."
                    >
                        <OptionSelect
                            id="upload-device"
                            value={data.device_id}
                            onChange={(value) => setData('device_id', value)}
                            options={devices.map((device) => ({
                                value: String(device.id),
                                label: device.name,
                            }))}
                            noneLabel="None"
                            placeholder="None"
                        />
                    </FormField>
                    {progress && (
                        <progress
                            value={progress.percentage}
                            max="100"
                            className="w-full"
                        />
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
                            disabled={processing || !data.file}
                        >
                            Upload
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}
