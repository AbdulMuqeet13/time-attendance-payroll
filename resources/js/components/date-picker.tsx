import { CalendarIcon, X } from 'lucide-react';
import { useState } from 'react';
import { Button } from '@/components/ui/button';
import { Calendar } from '@/components/ui/calendar';
import {
    Popover,
    PopoverContent,
    PopoverTrigger,
} from '@/components/ui/popover';
import { formatDate, parseIsoDate, toIsoDate } from '@/lib/dates';
import { cn } from '@/lib/utils';

type DatePickerProps = {
    id?: string;
    /** ISO `YYYY-MM-DD` value, or an empty string when unset. */
    value: string;
    onChange: (value: string) => void;
    placeholder?: string;
    clearable?: boolean;
    disabled?: boolean;
    /** `sm` matches the compact filter toolbars. */
    size?: 'sm' | 'default';
    className?: string;
};

export function DatePicker({
    id,
    value,
    onChange,
    placeholder = 'dd-mm-yyyy',
    clearable = false,
    disabled = false,
    size = 'default',
    className,
}: DatePickerProps) {
    const [open, setOpen] = useState(false);
    const selected = parseIsoDate(value);
    const currentYear = new Date().getFullYear();

    return (
        <Popover open={open} onOpenChange={setOpen}>
            <div className={cn('relative w-full', className)}>
                <PopoverTrigger asChild>
                    <Button
                        id={id}
                        type="button"
                        variant="outline"
                        size={size}
                        disabled={disabled}
                        className={cn(
                            'w-full justify-start px-3 text-left font-normal',
                            !selected && 'text-muted-foreground',
                            clearable && selected && 'pr-8',
                        )}
                    >
                        <CalendarIcon className="size-4 shrink-0" />
                        <span className="truncate">
                            {selected ? formatDate(selected) : placeholder}
                        </span>
                    </Button>
                </PopoverTrigger>
                {clearable && selected && !disabled && (
                    <button
                        type="button"
                        aria-label="Clear date"
                        onClick={() => onChange('')}
                        className="absolute top-1/2 right-2 -translate-y-1/2 text-muted-foreground hover:text-foreground"
                    >
                        <X className="size-4" />
                    </button>
                )}
            </div>
            <PopoverContent className="w-auto p-0" align="start">
                <Calendar
                    mode="single"
                    captionLayout="dropdown"
                    selected={selected}
                    defaultMonth={selected}
                    startMonth={new Date(currentYear - 50, 0)}
                    endMonth={new Date(currentYear + 10, 11)}
                    onSelect={(date) => {
                        onChange(date ? toIsoDate(date) : '');
                        setOpen(false);
                    }}
                />
            </PopoverContent>
        </Popover>
    );
}
