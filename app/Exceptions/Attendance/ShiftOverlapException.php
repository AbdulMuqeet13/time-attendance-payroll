<?php

namespace App\Exceptions\Attendance;

use DomainException;

class ShiftOverlapException extends DomainException
{
    /**
     * @param  array<int, string>  $employeeNames
     */
    public static function forEmployees(array $employeeNames): self
    {
        return new self('These employees already have a shift at overlapping times: '.implode(', ', $employeeNames).'.');
    }
}
