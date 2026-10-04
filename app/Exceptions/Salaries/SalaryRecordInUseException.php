<?php

namespace App\Exceptions\Salaries;

use DomainException;

class SalaryRecordInUseException extends DomainException
{
    public function __construct()
    {
        parent::__construct('This salary record was used in a payroll run and cannot be deleted.');
    }
}
