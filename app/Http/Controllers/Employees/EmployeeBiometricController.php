<?php

namespace App\Http\Controllers\Employees;

use App\Concerns\FlashesToast;
use App\Enums\PermissionEnum;
use App\Http\Controllers\Controller;
use App\Models\BiometricTemplate;
use App\Models\Device;
use App\Models\Employee;
use App\Services\Biometrics\BiometricSyncService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;

/**
 * Put an employee on devices (enrol / copy biometrics) or take them off.
 */
class EmployeeBiometricController extends Controller
{
    use FlashesToast;

    public function push(Request $request, Employee $employee, BiometricSyncService $sync): RedirectResponse
    {
        $devices = $this->devices($request, $employee);
        $result = $sync->pushToDevices($employee, $devices, $request->user());

        if ($result['errors'] !== []) {
            $this->flashError(implode(' ', array_unique($result['errors'])));
        } else {
            $this->flashSuccess($result['devices'] === 1
                ? 'Queued for the device. If they have no biometrics yet, they can now scan a finger or face on it to enrol.'
                : "Queued for {$result['devices']} devices.");
        }

        return back();
    }

    public function remove(Request $request, Employee $employee, BiometricSyncService $sync): RedirectResponse
    {
        foreach ($this->devices($request, $employee) as $device) {
            $sync->remove($employee, $device, $request->user());
        }

        $this->flashSuccess('Removal queued. Their scans already received stay in attendance.');

        return back();
    }

    public function destroyTemplate(Request $request, Employee $employee, BiometricTemplate $biometricTemplate): RedirectResponse
    {
        $this->authorizeBiometrics($request, $employee);

        $biometricTemplate->delete();
        $this->flashSuccess('Stored template deleted. Devices that already have it keep it until the employee is removed or re-pushed.');

        return back();
    }

    /**
     * The claimed, enabled devices chosen in the request, within the user's branch.
     *
     * @return Collection<int, Device>
     */
    private function devices(Request $request, Employee $employee): Collection
    {
        $this->authorizeBiometrics($request, $employee);

        $data = $request->validate([
            'device_ids' => ['required', 'array', 'min:1'],
            'device_ids.*' => ['integer', Rule::exists('devices', 'id')->whereNotNull('branch_id')->where('is_active', true)],
        ]);

        return Device::query()->visibleTo($request->user())->whereKey($data['device_ids'])->get();
    }

    private function authorizeBiometrics(Request $request, Employee $employee): void
    {
        abort_unless($request->user()->can(PermissionEnum::BiometricsManage->value), 403);
        $this->authorize('view', $employee);
    }
}
