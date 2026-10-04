<?php

use App\Services\Attendance\ShiftOverlapChecker;

/**
 * @param  array<int, int>|null  $days
 * @return array{start_time: string, end_time: string, days: array<int, int>|null, effective_from: string, effective_to: string|null}
 */
function assignment(string $start, string $end, ?array $days = null, string $from = '2026-01-01', ?string $to = null): array
{
    return ['start_time' => $start, 'end_time' => $end, 'days' => $days, 'effective_from' => $from, 'effective_to' => $to];
}

it('detects overlapping shifts', function (array $first, array $second, bool $overlaps) {
    expect((new ShiftOverlapChecker)->overlaps($first, $second))->toBe($overlaps);
})->with([
    'same times every day' => [assignment('09:00', '17:00'), assignment('12:00', '20:00'), true],
    'back to back' => [assignment('06:00', '10:00'), assignment('10:00', '14:00'), false],
    'split shift morning and evening' => [assignment('06:00', '10:00'), assignment('17:00', '21:00'), false],
    'different weekdays' => [assignment('09:00', '17:00', [1, 2, 3]), assignment('09:00', '17:00', [4, 5, 6]), false],
    'overnight runs into next morning' => [assignment('22:00', '06:00'), assignment('05:00', '09:00'), true],
    'saturday night into sunday morning' => [assignment('22:00', '06:00', [6]), assignment('05:00', '09:00', [0]), true],
    'saturday night but monday morning' => [assignment('22:00', '06:00', [6]), assignment('05:00', '09:00', [1]), false],
    'one range ends before the other starts' => [
        assignment('09:00', '17:00', null, '2026-01-01', '2026-03-31'),
        assignment('09:00', '17:00', null, '2026-04-01'),
        false,
    ],
    'open-ended ranges overlap' => [assignment('09:00', '17:00', null, '2026-01-01'), assignment('16:00', '18:00', null, '2027-01-01'), true],
]);
