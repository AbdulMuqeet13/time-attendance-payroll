<?php

namespace App\Exceptions\Salaries;

use DomainException;

class OnlySalaryRecordException extends DomainException
{
    public function __construct()
    {
        parent::__construct("An employee's only salary record cannot be deleted.");
    }
}
