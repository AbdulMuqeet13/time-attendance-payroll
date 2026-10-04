<?php

namespace App\Exceptions\Attendance;

use DomainException;

class AttendanceLockedException extends DomainException
{
    public function __construct()
    {
        parent::__construct('This attendance is part of an approved payroll and can no longer be changed.');
    }
}
