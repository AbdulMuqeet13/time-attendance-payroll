import { Check, Filter } from 'lucide-react';
import { useState } from 'react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Command,
    CommandEmpty,
    CommandGroup,
    CommandInput,
    CommandItem,
    CommandList,
} from '@/components/ui/command';
import {
    Popover,
    PopoverContent,
    PopoverTrigger,
} from '@/components/ui/popover';
import { Separator } from '@/components/ui/separator';
import { cn } from '@/lib/utils';

type FilterOption = {
    label: string;
    value: string;
};

type DataTableFilterProps = {
    title: string;
    options: FilterOption[];
    value?: string[];
    onChange: (value: string[]) => void;
};

export function DataTableFilter({
    title,
    options,
    value = [],
    onChange,
}: DataTableFilterProps) {
    const [open, setOpen] = useState(false);
    const selectedValues = new Set(value);

    const handleSelect = (optionValue: string) => {
        const next = new Set(selectedValues);

        if (next.has(optionValue)) {
            next.delete(optionValue);
        } else {
            next.add(optionValue);
        }

        onChange(Array.from(next));
    };

    const handleClear = () => {
        onChange([]);
    };

    return (
        <Popover open={open} onOpenChange={setOpen}>
            <PopoverTrigger asChild>
                <Button
                    variant="outline"
                    size="sm"
                    className="h-8 border-dashed"
                >
                    <Filter className="mr-2 size-3.5" />
                    {title}
                    {selectedValues.size > 0 && (
                        <>
                            <Separator
                                orientation="vertical"
                                className="mx-2 h-4"
                            />
                            <Badge
                                variant="secondary"
                                className="rounded-sm px-1 font-normal"
                            >
                                {selectedValues.size}
                            </Badge>
                        </>
                    )}
                </Button>
            </PopoverTrigger>
            <PopoverContent className="w-[200px] p-0" align="start">
                <Command>
                    <CommandInput placeholder={`Filter ${title}...`} />
                    <CommandList>
                        <CommandEmpty>No options found.</CommandEmpty>
                        <CommandGroup>
                            {options.map((option) => {
                                const isSelected = selectedValues.has(
                                    option.value,
                                );

                                return (
                                    <CommandItem
                                        key={option.value}
                                        onSelect={() =>
                                            handleSelect(option.value)
                                        }
                                    >
                                        <div
                                            className={cn(
                                                'mr-2 flex size-4 items-center justify-center rounded-sm border border-primary',
                                                isSelected
                                                    ? 'bg-primary text-primary-foreground'
                                                    : 'opacity-50 [&_svg]:invisible',
                                            )}
                                        >
                                            <Check className="size-3" />
                                        </div>
                                        <span>{option.label}</span>
                                    </CommandItem>
                                );
                            })}
                        </CommandGroup>
                    </CommandList>
                    {selectedValues.size > 0 && (
                        <>
                            <Separator />
                            <CommandGroup>
                                <CommandItem
                                    onSelect={handleClear}
                                    className="justify-center text-center"
                                >
                                    Clear filters
                                </CommandItem>
                            </CommandGroup>
                        </>
                    )}
                </Command>
            </PopoverContent>
        </Popover>
    );
}
