import { Search, X } from 'lucide-react';
import { useCallback, useEffect, useState } from 'react';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';

type DataTableSearchProps = {
    value: string;
    onChange: (value: string) => void;
    placeholder?: string;
    className?: string;
};

export function DataTableSearch({
    value,
    onChange,
    placeholder = 'Search...',
    className,
}: DataTableSearchProps) {
    const [localValue, setLocalValue] = useState(value);

    useEffect(() => {
        setLocalValue(value);
    }, [value]);

    const handleChange = useCallback(
        (newValue: string) => {
            setLocalValue(newValue);
            onChange(newValue);
        },
        [onChange],
    );

    const handleClear = useCallback(() => {
        setLocalValue('');
        onChange('');
    }, [onChange]);

    return (
        <div className={`relative max-w-sm ${className ?? ''}`}>
            <Search className="absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground" />
            <Input
                placeholder={placeholder}
                value={localValue}
                onChange={(e) => handleChange(e.target.value)}
                className="pr-9 pl-9"
            />
            {localValue && (
                <Button
                    variant="ghost"
                    size="icon"
                    className="absolute top-1/2 right-1 size-7 -translate-y-1/2"
                    onClick={handleClear}
                >
                    <X className="size-3.5" />
                    <span className="sr-only">Clear search</span>
                </Button>
            )}
        </div>
    );
}
