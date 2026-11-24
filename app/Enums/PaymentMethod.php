<?php

namespace App\Enums;

use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;
use Filament\Support\Icons\Heroicon;

enum PaymentMethod: string implements HasIcon, HasLabel
{
    case Cash = 'cash';
    case Whish = 'whish';   // Whish Money mobile wallet
    case Other = 'other';

    public function getLabel(): string
    {
        return match ($this) {
            self::Cash => 'Cash',
            self::Whish => 'Whish',
            self::Other => 'Other',
        };
    }

    public function getIcon(): Heroicon
    {
        return match ($this) {
            self::Cash => Heroicon::OutlinedBanknotes,
            self::Whish => Heroicon::OutlinedDevicePhoneMobile,
            self::Other => Heroicon::OutlinedEllipsisHorizontalCircle,
        };
    }
}
