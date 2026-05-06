<?php

namespace App\Enums;

enum ReservationStatus: string
{
    case OnHold = 'on_hold';
    case Confirmed = 'confirmed';

    public static function values(): array {
        return array_column(self::cases(), 'value');
    }
}
