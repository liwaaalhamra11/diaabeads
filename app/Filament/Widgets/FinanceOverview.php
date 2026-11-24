<?php

namespace App\Filament\Widgets;

use App\Enums\BillType;
use App\Filament\Resources\Bills\BillResource;
use App\Models\Bill;
use App\Support\Money;
use Filament\Support\Icons\Heroicon;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class FinanceOverview extends StatsOverviewWidget
{
    protected static ?int $sort = 0;

    protected ?string $heading = 'This month';

    public static function canView(): bool
    {
        return BillResource::canViewAny();
    }

    protected function getStats(): array
    {
        $month = fn () => Bill::query()->paid()->whereBetween('bill_date', [now()->startOfMonth(), now()->endOfMonth()]);

        $in = (float) $month()->incoming()->sum('total');
        $out = (float) $month()->outgoing()->sum('total');
        $toCollect = Bill::query()->incoming()->pending();
        $toPay = Bill::query()->outgoing()->pending();

        return [
            Stat::make('Money in', Money::format($in))
                ->description('Paid sales this month')
                ->descriptionIcon(BillType::In->getIcon()),
            Stat::make('Money out', Money::format($out))
                ->description('Paid expenses this month')
                ->descriptionIcon(BillType::Out->getIcon())
                ->color('bronze'),
            Stat::make('Balance', Money::format($in - $out))
                ->description('In − out')
                ->icon(Heroicon::OutlinedScale),
            Stat::make('Customers owe you', Money::format((float) $toCollect->sum('total')))
                ->description($toCollect->count().' pending sale(s)')
                ->descriptionIcon(Heroicon::OutlinedClock)
                ->color('bronze')
                ->url(BillResource::getUrl('index', ['tab' => 'pending'])),
            Stat::make('You owe', Money::format((float) $toPay->sum('total')))
                ->description($toPay->count().' pending expense(s)')
                ->descriptionIcon(Heroicon::OutlinedClock)
                ->color('bronze')
                ->url(BillResource::getUrl('index', ['tab' => 'pending'])),
        ];
    }
}
