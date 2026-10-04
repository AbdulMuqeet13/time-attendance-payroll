import type { InertiaLinkProps } from '@inertiajs/react';
import { clsx } from 'clsx';
import type { ClassValue } from 'clsx';
import { twMerge } from 'tailwind-merge';

export function cn(...inputs: ClassValue[]) {
    return twMerge(clsx(inputs));
}

export function toUrl(url: NonNullable<InertiaLinkProps['href']>): string {
    return typeof url === 'string' ? url : url.url;
}

/**
 * Convert a server-formatted dd-mm-yyyy date string to the yyyy-mm-dd value
 * used by date pickers and sent back to the server.
 */
export function toInputDate(value: string | null | undefined): string {
    if (!value) return '';
    const parts = value.split('-');
    if (parts.length !== 3) return value;
    return `${parts[2]}-${parts[1]}-${parts[0]}`;
}

/**
 * Format a money amount with thousands separators and two decimals.
 */
export function formatAmount(
    amount: string | number | null | undefined,
): string {
    return Number(amount ?? 0).toLocaleString('en-US', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    });
}
