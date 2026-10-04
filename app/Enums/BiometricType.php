<?php

namespace App\Enums;

use App\Enums\Concerns\HasOptions;

enum BiometricType: string
{
    use HasOptions;

    case Fingerprint = 'fingerprint';
    case Face = 'face';
    case Palm = 'palm';
    case Other = 'other';

    /**
     * The template type for a BIODATA "Type" code: 1 fingerprint, 2 and 9 face (near-infrared / visible light),
     * 6 and 8 palm (print / vein).
     */
    public static function fromBiodataType(int $code): self
    {
        return match ($code) {
            1 => self::Fingerprint,
            2, 9 => self::Face,
            6, 8 => self::Palm,
            default => self::Other,
        };
    }
}
