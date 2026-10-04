import { useForm } from '@inertiajs/react';
import type { FormEvent } from 'react';
import { FormField } from '@/components/form-field';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import {
    Dialog,
    DialogContent,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import type { NamedRecord } from '@/types';

type NamedRecordFormDialogProps = {
    noun: string;
    record: NamedRecord | null;
    url: string;
    onClose: () => void;
};

export function NamedRecordFormDialog({
    noun,
    record,
    url,
    onClose,
}: NamedRecordFormDialogProps) {
    const { data, setData, post, put, processing, errors } = useForm({
        name: record?.name ?? '',
        is_active: record?.is_active ?? true,
    });

    function handleSubmit(e: FormEvent) {
        e.preventDefault();

        const options = { onSuccess: () => onClose() };

        if (record) {
            put(url, options);
        } else {
            post(url, options);
        }
    }

    return (
        <Dialog open onOpenChange={(isOpen) => !isOpen && onClose()}>
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>
                        {record ? `Edit ${noun}` : `Add ${noun}`}
                    </DialogTitle>
                </DialogHeader>
                <form onSubmit={handleSubmit} className="space-y-4">
                    <FormField
                        label="Name"
                        htmlFor="record-name"
                        error={errors.name}
                        required
                    >
                        <Input
                            id="record-name"
                            value={data.name}
                            onChange={(e) => setData('name', e.target.value)}
                            required
                        />
                    </FormField>
                    <div className="flex items-center gap-2">
                        <Checkbox
                            id="record-active"
                            checked={data.is_active}
                            onCheckedChange={(checked) =>
                                setData('is_active', checked === true)
                            }
                        />
                        <Label htmlFor="record-active">Active</Label>
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
                            {processing ? 'Saving...' : 'Save'}
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}
