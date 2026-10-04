import { useForm } from '@inertiajs/react';
import type { FormEvent } from 'react';
import { FormField } from '@/components/form-field';
import type { SelectOption } from '@/components/option-select';
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
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import type { SalaryComponent } from '@/types';
import {
    store,
    update,
} from '@/actions/App/Http/Controllers/Organisation/SalaryComponentController';

type SalaryComponentFormDialogProps = {
    onClose: () => void;
    salaryComponent: SalaryComponent | null;
    componentTypes: SelectOption[];
    nextSortOrder: number;
};

export function SalaryComponentFormDialog({
    onClose,
    salaryComponent,
    componentTypes,
    nextSortOrder,
}: SalaryComponentFormDialogProps) {
    const { data, setData, post, put, processing, errors } = useForm({
        name: salaryComponent?.name ?? '',
        type: salaryComponent?.type ?? 'earning',
        sort_order: String(salaryComponent?.sort_order ?? nextSortOrder),
        is_active: salaryComponent?.is_active ?? true,
    });

    function handleSubmit(e: FormEvent) {
        e.preventDefault();

        const options = { onSuccess: () => onClose() };

        if (salaryComponent) {
            put(update(salaryComponent).url, options);
        } else {
            post(store().url, options);
        }
    }

    return (
        <Dialog open onOpenChange={(isOpen) => !isOpen && onClose()}>
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>
                        {salaryComponent
                            ? 'Edit Salary Component'
                            : 'Add Salary Component'}
                    </DialogTitle>
                    <DialogDescription>
                        Earnings add up to the gross salary. Deductions (e.g.
                        income tax) are taken off every month.
                    </DialogDescription>
                </DialogHeader>
                <form onSubmit={handleSubmit} className="space-y-4">
                    <FormField
                        label="Name"
                        htmlFor="component-name"
                        error={errors.name}
                        required
                    >
                        <Input
                            id="component-name"
                            value={data.name}
                            onChange={(e) => setData('name', e.target.value)}
                            required
                        />
                    </FormField>
                    <div className="grid gap-4 sm:grid-cols-2">
                        <FormField
                            label="Type"
                            htmlFor="component-type"
                            error={errors.type}
                            required
                        >
                            <OptionSelect
                                id="component-type"
                                value={data.type}
                                onChange={(value) =>
                                    setData(
                                        'type',
                                        value as SalaryComponent['type'],
                                    )
                                }
                                options={componentTypes}
                            />
                        </FormField>
                        <FormField
                            label="Order"
                            htmlFor="component-order"
                            error={errors.sort_order}
                            required
                        >
                            <Input
                                id="component-order"
                                type="number"
                                min="0"
                                value={data.sort_order}
                                onChange={(e) =>
                                    setData('sort_order', e.target.value)
                                }
                                required
                            />
                        </FormField>
                    </div>
                    <div className="flex items-center gap-2">
                        <Checkbox
                            id="component-active"
                            checked={data.is_active}
                            onCheckedChange={(checked) =>
                                setData('is_active', checked === true)
                            }
                        />
                        <Label htmlFor="component-active">
                            Active (shown on salary forms)
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
                            {processing ? 'Saving...' : 'Save'}
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}
