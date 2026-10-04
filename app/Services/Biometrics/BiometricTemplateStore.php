<?php

namespace App\Services\Biometrics;

use App\Enums\BiometricType;
use App\Enums\EnrollmentStatus;
use App\Models\BiometricTemplate;
use App\Models\Device;
use App\Models\DeviceEnrollment;
use App\Models\DeviceUser;
use App\Models\Employee;
use Illuminate\Support\Collection;

/**
 * Stores biometric templates that devices upload, under the employee whose device PIN matches.
 *
 * Accepts every shape devices use:
 *  - FP / FINGERTMP / templatev10: pin, fid|fingerid, size, valid, tmp|template
 *  - FACE:                         pin, fid, size, valid, tmp
 *  - BIODATA:                      pin, no, index, valid, type, majorver, minorver, format, tmp
 */
class BiometricTemplateStore
{
    /**
     * @param  array<int, array<string, string>>  $records
     * @return int The number of templates stored
     */
    public function store(Device $device, string $recordType, array $records): int
    {
        $templates = collect($records)
            ->map(fn (array $fields) => $this->normalise($device, $recordType, $fields))
            ->filter()
            ->values();

        if ($templates->isEmpty()) {
            return 0;
        }

        $employees = Employee::query()->whereIn('device_pin', $templates->pluck('pin')->unique())->get()->keyBy('device_pin');
        $stored = 0;

        foreach ($templates as $template) {
            $employee = $employees->get($template['pin']);

            if (! $employee) {
                continue;
            }

            BiometricTemplate::query()->updateOrCreate(
                ['employee_id' => $employee->id, 'type' => $template['type'], 'finger_index' => $template['finger_index']],
                [...collect($template)->except(['pin', 'type', 'finger_index'])->all(), 'source_device_id' => $device->id, 'captured_at' => now()],
            );

            $stored++;
        }

        $this->markOnDevice($device, $employees);
        $this->updateDeviceUserCounts($device, $templates);

        return $stored;
    }

    /**
     * @param  array<string, string>  $fields
     * @return array<string, mixed>|null
     */
    private function normalise(Device $device, string $recordType, array $fields): ?array
    {
        $pin = strtoupper(trim($fields['pin'] ?? ''));
        $template = $fields['tmp'] ?? $fields['template'] ?? '';

        if ($pin === '' || $template === '') {
            return null;
        }

        if ($recordType === 'biodata') {
            $biodataType = (int) ($fields['type'] ?? 0);
            $type = BiometricType::fromBiodataType($biodataType);

            return [
                'pin' => $pin,
                'type' => $type,
                'finger_index' => $type === BiometricType::Fingerprint ? (int) ($fields['no'] ?? 0) : (int) ($fields['index'] ?? 0),
                'template' => $template,
                'size' => strlen($template),
                'valid_flag' => $fields['valid'] ?? '1',
                'storage' => BiometricTemplate::STORAGE_BIODATA,
                'biodata_type' => $biodataType,
                'major_version' => $fields['majorver'] ?? null,
                'minor_version' => $fields['minorver'] ?? null,
                'format' => $fields['format'] ?? null,
            ];
        }

        $isFace = $recordType === 'face';

        return [
            'pin' => $pin,
            'type' => $isFace ? BiometricType::Face : BiometricType::Fingerprint,
            'finger_index' => (int) ($fields['fid'] ?? $fields['fingerid'] ?? 0),
            'template' => $template,
            'size' => (int) ($fields['size'] ?? strlen($template)),
            'valid_flag' => $fields['valid'] ?? '1',
            'storage' => BiometricTemplate::STORAGE_FINGERTMP,
            'biodata_type' => null,
            // Classic tables do not say which algorithm made the template, so record the device's.
            'major_version' => $isFace ? $device->face_algorithm : $device->fp_algorithm,
            'minor_version' => null,
            'format' => null,
        ];
    }

    /**
     * The device evidently holds these employees.
     *
     * @param  Collection<string, Employee>  $employees
     */
    private function markOnDevice(Device $device, Collection $employees): void
    {
        foreach ($employees as $employee) {
            DeviceEnrollment::query()->updateOrCreate(
                ['employee_id' => $employee->id, 'device_id' => $device->id],
                ['status' => EnrollmentStatus::OnDevice, 'synced_at' => now(), 'message' => null],
            );
        }
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $templates
     */
    private function updateDeviceUserCounts(Device $device, Collection $templates): void
    {
        foreach ($templates->groupBy('pin') as $pin => $userTemplates) {
            $deviceUser = DeviceUser::query()->firstOrNew(['device_id' => $device->id, 'pin' => $pin], ['seen_at' => now()]);
            $deviceUser->fingerprint_count = max(
                $deviceUser->fingerprint_count ?? 0,
                $userTemplates->where('type', BiometricType::Fingerprint)->unique('finger_index')->count(),
            );
            $deviceUser->has_face = $deviceUser->has_face || $userTemplates->contains('type', BiometricType::Face);
            $deviceUser->save();
        }
    }
}
