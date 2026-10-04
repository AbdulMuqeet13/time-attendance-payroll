<?php

namespace App\Actions\Organisation;

use App\Exceptions\Organisation\RecordInUseException;
use App\Models\Employee;
use Illuminate\Database\Eloquent\Model;

/**
 * Deletes a branch, department or designation that no employee uses.
 */
class DeleteOrganisationRecord
{
    /**
     * @throws RecordInUseException
     */
    public function handle(Model $model, string $employeeColumn, string $label): void
    {
        $inUse = Employee::withTrashed()->where($employeeColumn, $model->getKey())->exists();

        if ($inUse) {
            throw new RecordInUseException("This {$label} has employees and cannot be deleted. Mark it inactive instead.");
        }

        $model->delete();
    }
}
