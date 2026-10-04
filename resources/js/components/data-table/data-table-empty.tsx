import { Inbox } from 'lucide-react';

type DataTableEmptyProps = {
    message?: string;
    description?: string;
};

export function DataTableEmpty({
    message = 'No results found.',
    description = 'Try adjusting your search or filters.',
}: DataTableEmptyProps) {
    return (
        <div className="flex flex-col items-center justify-center py-8 text-center">
            <Inbox className="mb-3 size-10 text-muted-foreground/50" />
            <p className="text-sm font-medium text-muted-foreground">
                {message}
            </p>
            {description && (
                <p className="mt-1 text-xs text-muted-foreground/70">
                    {description}
                </p>
            )}
        </div>
    );
}
