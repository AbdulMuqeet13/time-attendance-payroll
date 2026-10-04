import { Check, ChevronsUpDown, X } from 'lucide-react';
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
import { cn } from '@/lib/utils';
import type { EmployeeOption } from '@/types';

type EmployeeMultiSelectProps = {
    id?: string;
    employees: EmployeeOption[];
    value: number[];
    onChange: (ids: number[]) => void;
    /** Allow picking only one employee. */
    single?: boolean;
    placeholder?: string;
};

/**
 * Searchable employee picker (by name or code). Used for one or many employees.
 */
export function EmployeeMultiSelect({
    id,
    employees,
    value,
    onChange,
    single = false,
    placeholder = 'Select employees...',
}: EmployeeMultiSelectProps) {
    const [open, setOpen] = useState(false);
    const selected = employees.filter((employee) =>
        value.includes(employee.id),
    );

    function toggle(employeeId: number) {
        if (single) {
            onChange([employeeId]);
            setOpen(false);

            return;
        }

        onChange(
            value.includes(employeeId)
                ? value.filter((existing) => existing !== employeeId)
                : [...value, employeeId],
        );
    }

    return (
        <div className="space-y-2">
            <Popover open={open} onOpenChange={setOpen}>
                <PopoverTrigger asChild>
                    <Button
                        id={id}
                        type="button"
                        variant="outline"
                        role="combobox"
                        className="w-full justify-between font-normal"
                    >
                        <span
                            className={cn(
                                'truncate',
                                selected.length === 0 &&
                                    'text-muted-foreground',
                            )}
                        >
                            {selected.length === 0
                                ? placeholder
                                : single
                                  ? `${selected[0].name} (${selected[0].employee_code})`
                                  : `${selected.length} selected`}
                        </span>
                        <ChevronsUpDown className="size-4 opacity-50" />
                    </Button>
                </PopoverTrigger>
                <PopoverContent
                    className="w-[--radix-popover-trigger-width] p-0"
                    align="start"
                >
                    <Command>
                        <CommandInput placeholder="Search name or code..." />
                        <CommandList>
                            <CommandEmpty>No employees found.</CommandEmpty>
                            <CommandGroup>
                                {!single && employees.length > 0 && (
                                    <CommandItem
                                        onSelect={() =>
                                            onChange(
                                                value.length ===
                                                    employees.length
                                                    ? []
                                                    : employees.map(
                                                          (employee) =>
                                                              employee.id,
                                                      ),
                                            )
                                        }
                                    >
                                        {value.length === employees.length
                                            ? 'Clear all'
                                            : `Select all (${employees.length})`}
                                    </CommandItem>
                                )}
                                {employees.map((employee) => (
                                    <CommandItem
                                        key={employee.id}
                                        value={`${employee.name} ${employee.employee_code}`}
                                        onSelect={() => toggle(employee.id)}
                                    >
                                        <Check
                                            className={cn(
                                                'size-4',
                                                value.includes(employee.id)
                                                    ? 'opacity-100'
                                                    : 'opacity-0',
                                            )}
                                        />
                                        <span className="truncate">
                                            {employee.name}
                                        </span>
                                        <span className="ml-auto font-mono text-xs text-muted-foreground">
                                            {employee.employee_code}
                                        </span>
                                    </CommandItem>
                                ))}
                            </CommandGroup>
                        </CommandList>
                    </Command>
                </PopoverContent>
            </Popover>
            {!single && selected.length > 0 && (
                <div className="flex max-h-24 flex-wrap gap-1 overflow-y-auto">
                    {selected.map((employee) => (
                        <Badge
                            key={employee.id}
                            variant="secondary"
                            className="gap-1"
                        >
                            {employee.name}
                            <button
                                type="button"
                                aria-label={`Remove ${employee.name}`}
                                onClick={() => toggle(employee.id)}
                            >
                                <X className="size-3" />
                            </button>
                        </Badge>
                    ))}
                </div>
            )}
        </div>
    );
}
