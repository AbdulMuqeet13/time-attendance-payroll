<?php

namespace App\Services\Backups;

use App\Exceptions\Backups\BackupException;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Storage;

/**
 * The backup file: JSON → gzip → encrypted with the app key. Biometric templates are personal data,
 * so a backup can only be read by an installation with the same APP_KEY.
 *
 * Contents:
 *  {format, version, created_at, device: {serial, name, model, firmware, push_version, fp_algorithm, face_algorithm},
 *   users: [{pin, name, privilege, card}],
 *   templates: [{pin, type, storage, index, valid, size, biodata_type, major_version, minor_version, format, template}],
 *   logs: [{pin, punched_at, state, verify, work_code}]}
 */
class BackupFile
{
    public const FORMAT = 'tap-zkteco-backup';

    public const VERSION = 1;

    private const DIRECTORY = 'device-backups';

    /**
     * @param  array<string, mixed>  $contents
     * @return array{path: string, size: int, checksum: string}
     */
    public function write(array $contents, string $name): array
    {
        $json = json_encode(['format' => self::FORMAT, 'version' => self::VERSION, ...$contents], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
        $encrypted = Crypt::encryptString((string) gzencode($json, 6));
        $path = self::DIRECTORY.'/'.$name.'.zkb';

        Storage::put($path, $encrypted);

        return ['path' => $path, 'size' => strlen($encrypted), 'checksum' => hash('sha256', $json)];
    }

    /**
     * @return array<string, mixed>
     *
     * @throws BackupException
     */
    public function read(string $path, ?string $expectedChecksum = null): array
    {
        if (! Storage::exists($path)) {
            throw new BackupException('The backup file is missing.');
        }

        return $this->decode((string) Storage::get($path), $expectedChecksum);
    }

    /**
     * @return array<string, mixed>
     *
     * @throws BackupException
     */
    public function decode(string $encrypted, ?string $expectedChecksum = null): array
    {
        try {
            $json = gzdecode(Crypt::decryptString(trim($encrypted)));
        } catch (DecryptException) {
            throw new BackupException('This file is not a backup from this system, or it was made with a different app key.');
        }

        if ($json === false) {
            throw new BackupException('The backup file is damaged.');
        }

        if ($expectedChecksum !== null && ! hash_equals($expectedChecksum, hash('sha256', $json))) {
            throw new BackupException('The backup file does not match its checksum; it may have been changed.');
        }

        $contents = json_decode($json, true);

        if (! is_array($contents) || ($contents['format'] ?? null) !== self::FORMAT) {
            throw new BackupException('This is not a device backup file.');
        }

        return [...$contents, '_checksum' => hash('sha256', $json)];
    }

    public function delete(?string $path): void
    {
        if ($path !== null) {
            Storage::delete($path);
        }
    }
}
