<?php

namespace App\Policies;

use App\Enums\PermissionEnum;
use App\Models\Device;
use App\Models\User;

class DevicePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can(PermissionEnum::DevicesView->value);
    }

    public function view(User $user, Device $device): bool
    {
        return $user->can(PermissionEnum::DevicesView->value) && $this->inScope($user, $device);
    }

    public function update(User $user, Device $device): bool
    {
        return $user->can(PermissionEnum::DevicesManage->value) && $this->inScope($user, $device);
    }

    public function delete(User $user, Device $device): bool
    {
        return $this->update($user, $device);
    }

    /**
     * Send commands to the device (reboot, re-read users or logs).
     */
    public function command(User $user, Device $device): bool
    {
        return $this->update($user, $device) && $device->acceptsData();
    }

    /**
     * Branch-scoped users see their branch's devices; unclaimed devices are for company-wide users only.
     */
    private function inScope(User $user, Device $device): bool
    {
        return $user->branch_id === null || $user->branch_id === $device->branch_id;
    }
}
