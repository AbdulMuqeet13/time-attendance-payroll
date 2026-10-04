<?php

namespace App\Policies;

use App\Enums\PermissionEnum;
use App\Models\AttendanceDay;
use App\Models\User;

class AttendanceDayPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can(PermissionEnum::AttendanceView->value);
    }

    public function update(User $user, AttendanceDay $day): bool
    {
        return $user->can(PermissionEnum::AttendanceManage->value)
            && $day->locked_at === null
            && ($user->branch_id === null || $user->branch_id === $day->employee->branch_id);
    }
}
