import type { ReactNode } from 'react';
import { Card, CardContent } from '@/components/ui/card';

export function StatCard({
    label,
    value,
    hint,
}: {
    label: string;
    value: ReactNode;
    hint?: ReactNode;
}) {
    return (
        <Card className="gap-0 py-4">
            <CardContent className="px-4">
                <p className="text-xs text-muted-foreground">{label}</p>
                <p className="mt-1 text-2xl font-semibold tabular-nums">
                    {value}
                </p>
                {hint && (
                    <p className="mt-1 text-xs text-muted-foreground">{hint}</p>
                )}
            </CardContent>
        </Card>
    );
}
