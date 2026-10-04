import { router } from '@inertiajs/react';
import type { ReactNode } from 'react';
import { useState } from 'react';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';

type ConfirmDialogProps = {
    open: boolean;
    onClose: () => void;
    title: string;
    description: ReactNode;
    /** The URL the confirmed request goes to. */
    url: string;
    method?: 'delete' | 'post' | 'put' | 'patch';
    data?: Record<string, string | number | boolean | null>;
    confirmLabel?: string;
    destructive?: boolean;
};

/**
 * A confirmation dialog that sends one request when confirmed (deletes, approvals, cancellations).
 */
export function ConfirmDialog({
    open,
    onClose,
    title,
    description,
    url,
    method = 'delete',
    data = {},
    confirmLabel = 'Delete',
    destructive = true,
}: ConfirmDialogProps) {
    const [processing, setProcessing] = useState(false);

    function confirm() {
        router.visit(url, {
            method,
            data,
            preserveScroll: true,
            onStart: () => setProcessing(true),
            onFinish: () => setProcessing(false),
            onSuccess: () => onClose(),
        });
    }

    return (
        <Dialog open={open} onOpenChange={(isOpen) => !isOpen && onClose()}>
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>{title}</DialogTitle>
                    <DialogDescription asChild>
                        <div>{description}</div>
                    </DialogDescription>
                </DialogHeader>
                <DialogFooter>
                    <Button
                        variant="outline"
                        onClick={onClose}
                        disabled={processing}
                    >
                        Cancel
                    </Button>
                    <Button
                        variant={destructive ? 'destructive' : 'default'}
                        onClick={confirm}
                        disabled={processing}
                    >
                        {processing ? 'Please wait...' : confirmLabel}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}
