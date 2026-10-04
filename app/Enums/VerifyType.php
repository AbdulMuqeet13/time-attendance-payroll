<?php

namespace App\Enums;

/**
 * How the employee identified themselves on the device (ATTLOG "verify" column).
 */
enum VerifyType: int
{
    case Password = 0;
    case Fingerprint = 1;
    case Card = 4;
    case Face = 15;
    case Palm = 25;

    public static function labelFor(?int $code): string
    {
        return match (self::tryFrom($code ?? -1)) {
            self::Password => 'Password',
            self::Fingerprint => 'Fingerprint',
            self::Card => 'Card',
            self::Face => 'Face',
            self::Palm => 'Palm',
            null => $code === null ? 'Manual' : "Other ({$code})",
        };
    }
}
