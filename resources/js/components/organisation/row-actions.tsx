import { MoreHorizontal, Pencil, Trash2 } from 'lucide-react';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';

type RowActionsProps = {
    onEdit?: () => void;
    onDelete?: () => void;
};

/**
 * The three-dot menu with Edit / Delete used on list rows.
 */
export function RowActions({ onEdit, onDelete }: RowActionsProps) {
    return (
        <DropdownMenu>
            <DropdownMenuTrigger asChild>
                <Button variant="ghost" size="icon" className="size-8">
                    <MoreHorizontal className="size-4" />
                    <span className="sr-only">Open menu</span>
                </Button>
            </DropdownMenuTrigger>
            <DropdownMenuContent align="end">
                {onEdit && (
                    <DropdownMenuItem onClick={onEdit}>
                        <Pencil className="mr-2 size-4" />
                        Edit
                    </DropdownMenuItem>
                )}
                {onDelete && (
                    <DropdownMenuItem
                        onClick={onDelete}
                        className="text-destructive focus:text-destructive"
                    >
                        <Trash2 className="mr-2 size-4" />
                        Delete
                    </DropdownMenuItem>
                )}
            </DropdownMenuContent>
        </DropdownMenu>
    );
}
