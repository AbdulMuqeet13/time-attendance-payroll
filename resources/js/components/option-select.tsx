import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { cn } from '@/lib/utils';

export type SelectOption = {
    value: string;
    label: string;
};

type OptionSelectProps = {
    id?: string;
    value: string;
    onChange: (value: string) => void;
    options: SelectOption[];
    placeholder?: string;
    /** Label of an extra option that clears the value (sent as an empty string). */
    noneLabel?: string;
    disabled?: boolean;
    size?: 'sm' | 'default';
    className?: string;
};

const NONE = '__none__';

/**
 * A Select over value/label options. Radix forbids empty item values, so "none" uses a sentinel.
 */
export function OptionSelect({
    id,
    value,
    onChange,
    options,
    placeholder = 'Select...',
    noneLabel,
    disabled = false,
    size = 'default',
    className,
}: OptionSelectProps) {
    return (
        <Select
            value={value === '' ? (noneLabel ? NONE : '') : value}
            onValueChange={(next) => onChange(next === NONE ? '' : next)}
            disabled={disabled}
        >
            <SelectTrigger
                id={id}
                size={size}
                className={cn('w-full min-w-0', className)}
            >
                <SelectValue placeholder={placeholder} />
            </SelectTrigger>
            <SelectContent>
                {noneLabel && <SelectItem value={NONE}>{noneLabel}</SelectItem>}
                {options.map((option) => (
                    <SelectItem key={option.value} value={option.value}>
                        {option.label}
                    </SelectItem>
                ))}
            </SelectContent>
        </Select>
    );
}

/**
 * Convert `{ id, name }` records to select options.
 */
export function toOptions(
    records: { id: number; name: string }[],
): SelectOption[] {
    return records.map((record) => ({
        value: String(record.id),
        label: record.name,
    }));
}
