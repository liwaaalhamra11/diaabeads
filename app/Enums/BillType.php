<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasDescription;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;
use Filament\Support\Icons\Heroicon;

enum BillType: string implements HasColor, HasDescription, HasIcon, HasLabel
{
    /** Money coming in: a customer bought products from the shop. */
    case In = 'in';

    /** Money going out: something the shop paid for or still has to pay. */
    case Out = 'out';

    public function getLabel(): string
    {
        return match ($this) {
            self::In => 'In · Sale',
            self::Out => 'Out · Expense',
        };
    }

    public function getDescription(): string
    {
        return match ($this) {
            self::In => 'A customer bought from the shop',
            self::Out => 'You paid, or have to pay, someone',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::In => 'primary',
            self::Out => 'bronze',
        };
    }

    public function getIcon(): Heroicon
    {
        return match ($this) {
            self::In => Heroicon::OutlinedArrowDownLeft,
            self::Out => Heroicon::OutlinedArrowUpRight,
        };
    }

    public function prefix(): string
    {
        return strtoupper($this->value);
    }

    /** Label for the person on the other side of the bill. */
    public function partyLabel(): string
    {
        return match ($this) {
            self::In => 'Customer',
            self::Out => 'Paid to',
        };
    }
}
