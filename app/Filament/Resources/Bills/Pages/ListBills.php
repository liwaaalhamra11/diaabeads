<?php

namespace App\Filament\Resources\Bills\Pages;

use App\Enums\BillType;
use App\Filament\Resources\Bills\BillResource;
use App\Models\Bill;
use Filament\Actions\Action;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Builder;

class ListBills extends ListRecords
{
    protected static string $resource = BillResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('newOut')
                ->label('New expense')
                ->icon(BillType::Out->getIcon())
                ->color('gray')
                ->url(BillResource::getUrl('create', ['type' => BillType::Out->value]))
                ->visible(BillResource::canCreate()),
            Action::make('newIn')
                ->label('New sale')
                ->icon(BillType::In->getIcon())
                ->url(BillResource::getUrl('create', ['type' => BillType::In->value]))
                ->visible(BillResource::canCreate()),
        ];
    }

    public function getTabs(): array
    {
        return [
            'all' => Tab::make('All'),
            'in' => Tab::make('Money in')
                ->icon(BillType::In->getIcon())
                ->modifyQueryUsing(fn (Builder $query) => $query->incoming()),
            'out' => Tab::make('Money out')
                ->icon(BillType::Out->getIcon())
                ->modifyQueryUsing(fn (Builder $query) => $query->outgoing()),
            'pending' => Tab::make('Pending')
                ->icon(Heroicon::OutlinedClock)
                ->badge(Bill::query()->pending()->count() ?: null)
                ->badgeColor('bronze')
                ->modifyQueryUsing(fn (Builder $query) => $query->pending()),
        ];
    }
}
