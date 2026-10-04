import type { ReactNode } from 'react';
import InputError from '@/components/input-error';
import { Label } from '@/components/ui/label';
import { cn } from '@/lib/utils';

type FormFieldProps = {
    label: string;
    htmlFor?: string;
    error?: string;
    hint?: string;
    required?: boolean;
    className?: string;
    children: ReactNode;
};

export function FormField({
    label,
    htmlFor,
    error,
    hint,
    required = false,
    className,
    children,
}: FormFieldProps) {
    return (
        <div className={cn('min-w-0 space-y-2', className)}>
            <Label htmlFor={htmlFor}>
                {label}
                {required && <span className="text-destructive">*</span>}
            </Label>
            {children}
            {hint && !error && (
                <p className="text-xs text-muted-foreground">{hint}</p>
            )}
            <InputError message={error} />
        </div>
    );
}
