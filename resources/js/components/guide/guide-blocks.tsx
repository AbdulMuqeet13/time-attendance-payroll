import { Lightbulb, TriangleAlert } from 'lucide-react';
import type { ReactNode } from 'react';

/**
 * Small building blocks for the user guide's prose.
 */
export function Steps({ children }: { children: ReactNode }) {
    return (
        <ol className="ml-5 list-decimal space-y-1.5 marker:text-muted-foreground">
            {children}
        </ol>
    );
}

export function Bullets({ children }: { children: ReactNode }) {
    return (
        <ul className="ml-5 list-disc space-y-1.5 marker:text-muted-foreground">
            {children}
        </ul>
    );
}

export function Tip({ children }: { children: ReactNode }) {
    return (
        <div className="flex gap-3 rounded-lg border border-sky-200 bg-sky-50 p-3 text-sm text-sky-900 dark:border-sky-900/50 dark:bg-sky-950/30 dark:text-sky-200">
            <Lightbulb className="mt-0.5 size-4 shrink-0" />
            <div>{children}</div>
        </div>
    );
}

export function Warning({ children }: { children: ReactNode }) {
    return (
        <div className="flex gap-3 rounded-lg border border-amber-200 bg-amber-50 p-3 text-sm text-amber-900 dark:border-amber-900/50 dark:bg-amber-950/30 dark:text-amber-200">
            <TriangleAlert className="mt-0.5 size-4 shrink-0" />
            <div>{children}</div>
        </div>
    );
}

export function Sub({
    title,
    children,
}: {
    title: string;
    children: ReactNode;
}) {
    return (
        <div className="space-y-2">
            <h3 className="text-base font-semibold">{title}</h3>
            {children}
        </div>
    );
}

export function GuideTable({
    head,
    rows,
}: {
    head: string[];
    rows: ReactNode[][];
}) {
    return (
        <div className="overflow-x-auto rounded-lg border">
            <table className="w-full text-sm">
                <thead className="bg-muted/50 text-left">
                    <tr>
                        {head.map((cell) => (
                            <th key={cell} className="px-3 py-2 font-medium">
                                {cell}
                            </th>
                        ))}
                    </tr>
                </thead>
                <tbody className="divide-y">
                    {rows.map((row, index) => (
                        <tr key={index}>
                            {row.map((cell, cellIndex) => (
                                <td
                                    key={cellIndex}
                                    className="px-3 py-2 align-top"
                                >
                                    {cell}
                                </td>
                            ))}
                        </tr>
                    ))}
                </tbody>
            </table>
        </div>
    );
}

/**
 * A link to another section of the guide.
 */
export function See({ id, children }: { id: string; children: ReactNode }) {
    return (
        <a href={`#${id}`} className="font-medium underline underline-offset-2">
            {children}
        </a>
    );
}

/**
 * Where to click, e.g. <Path>Attendance → Daily</Path>.
 */
export function Path({ children }: { children: ReactNode }) {
    return <strong className="whitespace-nowrap">{children}</strong>;
}
