import InputError from '@/components/input-error';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { formatAmount } from '@/lib/utils';
import type { SalaryComponent } from '@/types';

export type SalaryComponentInput = {
    salary_component_id: number;
    amount: string;
};

type SalaryBreakdownFieldsProps = {
    salaryComponents: Pick<SalaryComponent, 'id' | 'name' | 'type'>[];
    value: SalaryComponentInput[];
    onChange: (value: SalaryComponentInput[]) => void;
    errors: Partial<Record<string, string>>;
};

/**
 * One amount input per salary component, with live gross / deductions / net totals.
 */
export function SalaryBreakdownFields({
    salaryComponents,
    value,
    onChange,
    errors,
}: SalaryBreakdownFieldsProps) {
    const amountFor = (id: number) =>
        value.find((item) => item.salary_component_id === id)?.amount ?? '';

    const setAmount = (id: number, amount: string) => {
        onChange(
            salaryComponents.map((component) => ({
                salary_component_id: component.id,
                amount: component.id === id ? amount : amountFor(component.id),
            })),
        );
    };

    const total = (type: SalaryComponent['type']) =>
        salaryComponents
            .filter((component) => component.type === type)
            .reduce(
                (sum, component) =>
                    sum + (Number(amountFor(component.id)) || 0),
                0,
            );

    const gross = total('earning');
    const deductions = total('deduction');

    return (
        <div className="space-y-4">
            <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                {salaryComponents.map((component, index) => (
                    <div key={component.id} className="min-w-0 space-y-2">
                        <Label htmlFor={`component-${component.id}`}>
                            {component.name}
                            {component.type === 'deduction' && (
                                <span className="text-xs text-muted-foreground">
                                    (deduction)
                                </span>
                            )}
                        </Label>
                        <Input
                            id={`component-${component.id}`}
                            type="number"
                            min="0"
                            step="0.01"
                            inputMode="decimal"
                            placeholder="0.00"
                            value={amountFor(component.id)}
                            onChange={(e) =>
                                setAmount(component.id, e.target.value)
                            }
                        />
                        <InputError
                            message={errors[`components.${index}.amount`]}
                        />
                    </div>
                ))}
            </div>
            <InputError message={errors.components} />
            <dl className="grid grid-cols-3 gap-4 rounded-lg bg-muted/50 p-3 text-sm">
                <div>
                    <dt className="text-muted-foreground">Gross salary</dt>
                    <dd className="font-semibold">{formatAmount(gross)}</dd>
                </div>
                <div>
                    <dt className="text-muted-foreground">Fixed deductions</dt>
                    <dd className="font-semibold">
                        {formatAmount(deductions)}
                    </dd>
                </div>
                <div>
                    <dt className="text-muted-foreground">
                        Net (full attendance)
                    </dt>
                    <dd className="font-semibold">
                        {formatAmount(gross - deductions)}
                    </dd>
                </div>
            </dl>
        </div>
    );
}
