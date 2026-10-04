<?php

namespace App\Actions\Organisation;

use Illuminate\Database\Eloquent\Model;

/**
 * Creates or updates a simple organisation record (branch, department, designation, salary component).
 */
class SaveOrganisationRecord
{
    /**
     * @template TModel of Model
     *
     * @param  TModel  $model
     * @param  array<string, mixed>  $data
     * @return TModel
     */
    public function handle(Model $model, array $data): Model
    {
        $model->fill($data)->save();

        return $model;
    }
}
