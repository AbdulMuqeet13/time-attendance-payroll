<?php

namespace App\Concerns;

use Inertia\Inertia;

/**
 * Flash toast messages as Inertia flash data so the frontend's `flash` router
 * event (see `useFlashToast`) receives them on the next page visit.
 */
trait FlashesToast
{
    protected function flashSuccess(string $message): void
    {
        Inertia::flash('toast', ['type' => 'success', 'message' => $message]);
    }

    protected function flashError(string $message): void
    {
        Inertia::flash('toast', ['type' => 'error', 'message' => $message]);
    }

    protected function flashInfo(string $message): void
    {
        Inertia::flash('toast', ['type' => 'info', 'message' => $message]);
    }

    protected function flashWarning(string $message): void
    {
        Inertia::flash('toast', ['type' => 'warning', 'message' => $message]);
    }
}
