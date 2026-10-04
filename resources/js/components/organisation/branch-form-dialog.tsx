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
import type { Branch } from '@/types';
import {
    store,
    update,
} from '@/actions/App/Http/Controllers/Organisation/BranchController';

type BranchFormDialogProps = {
    open: boolean;
    onClose: () => void;
    branch?: Branch | null;
};

export function BranchFormDialog({
    open,
    onClose,
    branch,
}: BranchFormDialogProps) {
    const { data, setData, post, put, processing, errors } = useForm({
        name: branch?.name ?? '',
        code: branch?.code ?? '',
        address: branch?.address ?? '',
        phone: branch?.phone ?? '',
        is_active: branch?.is_active ?? true,
    });

    function handleSubmit(e: FormEvent) {
        e.preventDefault();

        const options = { onSuccess: () => onClose() };

        if (branch) {
            put(update(branch).url, options);
        } else {
            post(store().url, options);
        }
    }

    return (
        <Dialog open={open} onOpenChange={(isOpen) => !isOpen && onClose()}>
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>
                        {branch ? 'Edit Branch' : 'Add Branch'}
                    </DialogTitle>
                </DialogHeader>

                <form onSubmit={handleSubmit} className="space-y-4">
                    <div className="grid gap-4 sm:grid-cols-3">
                        <FormField
                            label="Name"
                            htmlFor="branch-name"
                            error={errors.name}
                            required
                            className="sm:col-span-2"
                        >
                            <Input
                                id="branch-name"
                                value={data.name}
                                onChange={(e) =>
                                    setData('name', e.target.value)
                                }
                                required
                            />
                        </FormField>
                        <FormField
                            label="Code"
                            htmlFor="branch-code"
                            error={errors.code}
                            required
                        >
                            <Input
                                id="branch-code"
                                value={data.code}
                                onChange={(e) =>
                                    setData('code', e.target.value)
                                }
                                required
                            />
                        </FormField>
                    </div>
                    <FormField
                        label="Address"
                        htmlFor="branch-address"
                        error={errors.address}
                    >
                        <Input
                            id="branch-address"
                            value={data.address}
                            onChange={(e) => setData('address', e.target.value)}
                        />
                    </FormField>
                    <FormField
                        label="Phone"
                        htmlFor="branch-phone"
                        error={errors.phone}
                    >
                        <Input
                            id="branch-phone"
                            value={data.phone}
                            onChange={(e) => setData('phone', e.target.value)}
                        />
                    </FormField>
                    <div className="flex items-center gap-2">
                        <Checkbox
                            id="branch-active"
                            checked={data.is_active}
                            onCheckedChange={(checked) =>
                                setData('is_active', checked === true)
                            }
                        />
                        <Label htmlFor="branch-active">Active</Label>
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
