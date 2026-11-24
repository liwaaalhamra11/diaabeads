<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;
use Filament\Support\Icons\Heroicon;

enum BillStatus: string implements HasColor, HasIcon, HasLabel
{
    case Paid = 'paid';
    case Pending = 'pending';

    public function getLabel(): string
    {
        return match ($this) {
            self::Paid => 'Paid',
            self::Pending => 'Pending',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Paid => 'primary',
            self::Pending => 'bronze',
        };
    }

    public function getIcon(): Heroicon
    {
        return match ($this) {
            self::Paid => Heroicon::OutlinedCheckCircle,
            self::Pending => Heroicon::OutlinedClock,
        };
    }
}
