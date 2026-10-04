<?php

namespace App\Services\Adms;

use App\Enums\DeviceCommandType;
use App\Models\Device;
use Carbon\CarbonInterface;

/**
 * The only place ADMS command strings are written. Fields inside a command are separated by tabs.
 *
 * Each method returns the command type and text; DeviceCommandQueue stores and delivers them.
 */
class AdmsCommandBuilder
{
    private const TAB = "\t";

    /**
     * Create or update a user on the device.
     *
     * @return array{DeviceCommandType, string}
     */
    public function updateUser(string $pin, string $name, int $privilege = 0, ?string $card = null, ?string $password = null): array
    {
        return [DeviceCommandType::UserUpdate, 'DATA UPDATE USERINFO '.$this->fields([
            'PIN' => $pin,
            'Name' => $this->clean($name, 24),
            'Pri' => (string) $privilege,
            'Passwd' => $password ?? '',
            'Card' => $card ?? '',
        ])];
    }

    /**
     * Delete a user and all their biometrics from the device.
     *
     * @return array{DeviceCommandType, string}
     */
    public function deleteUser(string $pin): array
    {
        return [DeviceCommandType::UserDelete, 'DATA DELETE USERINFO PIN='.$pin];
    }

    /**
     * Write a fingerprint template (classic FINGERTMP table).
     *
     * @return array{DeviceCommandType, string}
     */
    public function updateFingerprint(string $pin, int $fingerIndex, string $template, int $size, string $valid = '1'): array
    {
        return [DeviceCommandType::FingerprintUpdate, 'DATA UPDATE FINGERTMP '.$this->fields([
            'PIN' => $pin,
            'FID' => (string) $fingerIndex,
            'Size' => (string) $size,
            'Valid' => $valid,
            'TMP' => $template,
        ])];
    }

    /**
     * Write a biometric template in the unified BIODATA table (faces, palms and newer fingerprint formats).
     *
     * @return array{DeviceCommandType, string}
     */
    public function updateBiodata(string $pin, int $type, int $number, int $index, string $template, string $majorVersion = '0', string $minorVersion = '0', string $format = '0', string $valid = '1'): array
    {
        return [DeviceCommandType::BiodataUpdate, 'DATA UPDATE BIODATA '.$this->fields([
            'Pin' => $pin,
            'No' => (string) $number,
            'Index' => (string) $index,
            'Valid' => $valid,
            'Duress' => '0',
            'Type' => (string) $type,
            'MajorVer' => $majorVersion,
            'MinorVer' => $minorVersion,
            'Format' => $format,
            'Tmp' => $template,
        ])];
    }

    /**
     * Ask the device to upload its user list. PUSH 3.x firmware takes a table query; older firmware
     * re-uploads its operation log (users and fingerprints) after CHECK once the OPERLOG stamp is reset.
     *
     * @return array{DeviceCommandType, string}
     */
    public function queryUsers(Device $device): array
    {
        return $device->usesPushV3()
            ? [DeviceCommandType::QueryUsers, 'DATA QUERY tablename=user,fielddesc=*,filter=*']
            : [DeviceCommandType::Check, 'CHECK'];
    }

    /**
     * Ask a PUSH 3.x device to upload its biometric templates.
     *
     * @return array<int, array{DeviceCommandType, string}>
     */
    public function queryTemplates(Device $device): array
    {
        if (! $device->usesPushV3()) {
            return [];
        }

        return [
            [DeviceCommandType::QueryTemplates, 'DATA QUERY tablename=templatev10,fielddesc=*,filter=*'],
            [DeviceCommandType::QueryTemplates, 'DATA QUERY tablename=biodata,fielddesc=*,filter=*'],
        ];
    }

    /**
     * Ask the device to upload attendance logs between two times.
     *
     * @return array{DeviceCommandType, string}
     */
    public function queryAttendance(CarbonInterface $from, CarbonInterface $to): array
    {
        return [DeviceCommandType::QueryAttendance, 'DATA QUERY ATTLOG '.$this->fields([
            'StartTime' => $from->format('Y-m-d H:i:s'),
            'EndTime' => $to->format('Y-m-d H:i:s'),
        ])];
    }

    /**
     * @return array{DeviceCommandType, string}
     */
    public function clearAttendance(): array
    {
        return [DeviceCommandType::ClearAttendance, 'CLEAR LOG'];
    }

    /**
     * Wipes users, biometrics and logs from the device.
     *
     * @return array{DeviceCommandType, string}
     */
    public function clearAllData(): array
    {
        return [DeviceCommandType::ClearAllData, 'CLEAR DATA'];
    }

    /**
     * @return array{DeviceCommandType, string}
     */
    public function reboot(): array
    {
        return [DeviceCommandType::Reboot, 'REBOOT'];
    }

    /**
     * @return array{DeviceCommandType, string}
     */
    public function info(): array
    {
        return [DeviceCommandType::Info, 'INFO'];
    }

    /**
     * @return array{DeviceCommandType, string}
     */
    public function check(): array
    {
        return [DeviceCommandType::Check, 'CHECK'];
    }

    /**
     * @param  array<string, string>  $fields
     */
    private function fields(array $fields): string
    {
        return implode(self::TAB, array_map(
            fn (string $key, string $value) => "{$key}={$value}",
            array_keys($fields),
            $fields,
        ));
    }

    /**
     * Names may not contain tabs or line breaks (they would break the command) and are length limited.
     */
    private function clean(string $value, int $maxLength): string
    {
        $value = trim((string) preg_replace('/[\t\r\n=]+/', ' ', $value));

        return mb_substr($value, 0, $maxLength);
    }
}
