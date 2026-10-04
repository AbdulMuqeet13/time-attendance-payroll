<?php

namespace App\Services\Adms;

use Carbon\CarbonImmutable;
use Throwable;

/**
 * Parses the plain-text bodies ZKTeco devices send over ADMS (PUSH SDK).
 *
 * Firmware differs in how it formats the same data, so parsing is deliberately tolerant:
 *  - ATTLOG: positional, tab separated: PIN, time, status, verify, work code, …
 *  - OPERLOG / USERINFO / FINGERTMP / BIODATA: "USER PIN=1\tName=Ali…", "FP PIN=1\tFID=6…",
 *    "BIODATA Pin=1\tNo=0…", or bare "PIN=1\tName=…" lines
 *  - PUSH 3.x query results: "user uid=1\tpin=1…", "templatev10 pin=1\tfingerid=6…", "biodata pin=1…"
 */
class AdmsParser
{
    /**
     * @return array<int, array{pin: string, punched_at: string, state: int|null, verify: int|null, work_code: string|null}>
     */
    public function attendanceLogs(string $body): array
    {
        $logs = [];

        foreach ($this->lines($body) as $line) {
            if (str_contains($line, '=') && ($log = $this->keyedAttendanceLog($line))) {
                $logs[] = $log;

                continue;
            }

            $parts = array_map('trim', explode("\t", $line));

            if (count($parts) < 2 || $parts[0] === '') {
                continue;
            }

            $punchedAt = $this->dateTime($parts[1]);

            if ($punchedAt === null) {
                continue;
            }

            $logs[] = [
                'pin' => strtoupper($parts[0]),
                'punched_at' => $punchedAt,
                'state' => isset($parts[2]) && is_numeric($parts[2]) ? (int) $parts[2] : null,
                'verify' => isset($parts[3]) && is_numeric($parts[3]) ? (int) $parts[3] : null,
                'work_code' => isset($parts[4]) && $parts[4] !== '' && $parts[4] !== '0' ? $parts[4] : null,
            ];
        }

        return $logs;
    }

    /**
     * PUSH 3.x query results list scans as key=value pairs ("transaction pin=7\ttime=…\tverified=1…").
     *
     * @return array{pin: string, punched_at: string, state: int|null, verify: int|null, work_code: string|null}|null
     */
    private function keyedAttendanceLog(string $line): ?array
    {
        $line = (string) preg_replace('/^[A-Za-z]+\s+(?=\S+=)/', '', $line);
        $fields = $this->keyValues($line, "\t");
        $time = $fields['time'] ?? $fields['checktime'] ?? $fields['time_second'] ?? null;
        $pin = strtoupper(trim($fields['pin'] ?? ''));

        if ($pin === '' || $time === null || ($punchedAt = $this->dateTime($time)) === null) {
            return null;
        }

        return [
            'pin' => $pin,
            'punched_at' => $punchedAt,
            'state' => isset($fields['inoutstate']) && is_numeric($fields['inoutstate']) ? (int) $fields['inoutstate'] : (isset($fields['status']) && is_numeric($fields['status']) ? (int) $fields['status'] : null),
            'verify' => isset($fields['verified']) && is_numeric($fields['verified']) ? (int) $fields['verified'] : (isset($fields['verify']) && is_numeric($fields['verify']) ? (int) $fields['verify'] : null),
            'work_code' => in_array($fields['workcode'] ?? '', ['', '0'], true) ? null : $fields['workcode'],
        ];
    }

    /**
     * Records of the form "[PREFIX ]key=value\tkey=value…". Keys are lower-cased.
     *
     * The record type comes from the line prefix (USER, FP, BIODATA, FACE, user, templatev10, …) and
     * falls back to the table the device posted to.
     *
     * @return array<int, array{type: string, fields: array<string, string>}>
     */
    public function records(string $body, string $table): array
    {
        $records = [];

        foreach ($this->lines($body) as $line) {
            $type = $this->normaliseType($table);

            if (preg_match('/^([A-Za-z][A-Za-z0-9]*)\s+(?=\S+=)/', $line, $match)) {
                $type = $this->normaliseType($match[1]);
                $line = substr($line, strlen($match[0]));
            }

            $fields = $this->keyValues($line, "\t");

            // Some firmware sends FINGERTMP positionally: PIN, FID, Size, Valid, TMP.
            if ($fields === [] && $type === 'fingerprint') {
                $parts = array_map('trim', explode("\t", $line));

                if (count($parts) >= 5) {
                    $fields = ['pin' => $parts[0], 'fid' => $parts[1], 'size' => $parts[2], 'valid' => $parts[3], 'tmp' => $parts[4]];
                }
            }

            if ($fields !== []) {
                $records[] = ['type' => $type, 'fields' => $fields];
            }
        }

        return $records;
    }

    /**
     * Command results from POST /iclock/devicecmd: one "ID=12&Return=0&CMD=DATA" per line.
     *
     * @return array<int, array{id: int, return: int, cmd: string|null}>
     */
    public function commandResults(string $body): array
    {
        $results = [];

        foreach ($this->lines($body) as $line) {
            $fields = $this->keyValues($line, '&');

            if (! isset($fields['id']) || ! is_numeric($fields['id'])) {
                continue;
            }

            $results[] = [
                'id' => (int) $fields['id'],
                'return' => isset($fields['return']) && is_numeric($fields['return']) ? (int) $fields['return'] : 0,
                'cmd' => $fields['cmd'] ?? null,
            ];
        }

        return $results;
    }

    /**
     * The INFO value the device sends with GET /iclock/getrequest:
     * firmware, users, fingerprints, attendance logs, IP, fingerprint algorithm, face algorithm, faces needed, faces, …
     *
     * @return array{firmware?: string, user_count?: int, fp_count?: int, att_count?: int, ip_address?: string, fp_algorithm?: string, face_algorithm?: string, face_count?: int}
     */
    public function info(?string $info): array
    {
        if ($info === null || trim($info) === '') {
            return [];
        }

        $parts = array_map('trim', explode(',', $info));
        $int = fn (int $index): ?int => isset($parts[$index]) && is_numeric($parts[$index]) ? (int) $parts[$index] : null;
        $string = fn (int $index): ?string => isset($parts[$index]) && $parts[$index] !== '' ? $parts[$index] : null;

        return array_filter([
            'firmware' => $string(0),
            'user_count' => $int(1),
            'fp_count' => $int(2),
            'att_count' => $int(3),
            'ip_address' => $string(4),
            'fp_algorithm' => $string(5),
            'face_algorithm' => $string(6),
            'face_count' => $int(8),
        ], fn (mixed $value) => $value !== null);
    }

    /**
     * @param  non-empty-string  $separator
     * @return array<string, string>
     */
    public function keyValues(string $line, string $separator): array
    {
        $fields = [];

        foreach (explode($separator, $line) as $pair) {
            $position = strpos($pair, '=');

            if ($position === false) {
                continue;
            }

            $key = strtolower(trim(substr($pair, 0, $position)));

            if ($key !== '') {
                $fields[$key] = trim(substr($pair, $position + 1));
            }
        }

        return $fields;
    }

    /**
     * Map the many names devices use for the same kind of record to one type.
     */
    private function normaliseType(string $name): string
    {
        return match (strtolower($name)) {
            'user', 'userinfo' => 'user',
            'fp', 'fingertmp', 'templatev10', 'template' => 'fingerprint',
            'biodata' => 'biodata',
            'face', 'facetmp' => 'face',
            'oplog', 'operlog' => 'oplog',
            'attlog', 'transaction' => 'attlog',
            'biophoto', 'userpic' => 'photo',
            default => strtolower($name),
        };
    }

    /**
     * @return array<int, string>
     */
    private function lines(string $body): array
    {
        return array_values(array_filter(
            array_map(fn (string $line) => trim($line, "\r\n "), preg_split('/\r\n|\r|\n/', $body) ?: []),
            fn (string $line) => $line !== '',
        ));
    }

    private function dateTime(string $value): ?string
    {
        try {
            return CarbonImmutable::parse($value)->format('Y-m-d H:i:s');
        } catch (Throwable) {
            return null;
        }
    }
}
