import type { ReactNode } from 'react';
import { Badge } from '@/components/ui/badge';
import { cn } from '@/lib/utils';

export type BadgeTone = 'success' | 'warning' | 'danger' | 'info' | 'neutral';

const toneClasses: Record<BadgeTone, string> = {
    success:
        'border-emerald-200 bg-emerald-50 text-emerald-700 dark:border-emerald-900 dark:bg-emerald-950 dark:text-emerald-300',
    warning:
        'border-amber-200 bg-amber-50 text-amber-700 dark:border-amber-900 dark:bg-amber-950 dark:text-amber-300',
    danger: 'border-red-200 bg-red-50 text-red-700 dark:border-red-900 dark:bg-red-950 dark:text-red-300',
    info: 'border-sky-200 bg-sky-50 text-sky-700 dark:border-sky-900 dark:bg-sky-950 dark:text-sky-300',
    neutral:
        'border-gray-200 bg-gray-50 text-gray-700 dark:border-gray-800 dark:bg-gray-900 dark:text-gray-300',
};

/**
 * Semantic status badge. Colours carry meaning (success, warning, danger), never the theme primary.
 */
export function StatusBadge({
    tone,
    children,
    className,
}: {
    tone: BadgeTone;
    children: ReactNode;
    className?: string;
}) {
    return (
        <Badge variant="outline" className={cn(toneClasses[tone], className)}>
            {children}
        </Badge>
    );
}

export function ActiveBadge({ active }: { active: boolean }) {
    return (
        <StatusBadge tone={active ? 'success' : 'neutral'}>
            {active ? 'Active' : 'Inactive'}
        </StatusBadge>
    );
}

/**
 * "employment_type" → "Employment Type", matching the server's Str::headline labels.
 */
export function headline(value: string): string {
    return value
        .split('_')
        .map((word) => word.charAt(0).toUpperCase() + word.slice(1))
        .join(' ');
}
