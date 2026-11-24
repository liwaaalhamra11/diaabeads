<?php

namespace App\Filament\Resources\Products\RelationManagers;

use App\Filament\Resources\Bills\BillResource;
use App\Filament\Resources\Products\Pages\ViewProduct;
use App\Models\BillItem;
use App\Support\Money;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/** Read-only list of sale bills that include this product. */
class SalesRelationManager extends RelationManager
{
    protected static string $relationship = 'soldItems';

    protected static ?string $title = 'Sales history';

    protected static string|\BackedEnum|null $icon = Heroicon::OutlinedReceiptPercent;

    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        return $pageClass === ViewProduct::class && BillResource::canViewAny();
    }

    public function isReadOnly(): bool
    {
        return true;
    }

    public function table(Table $table): Table
    {
        $currency = Money::currency();

        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with('bill'))
            ->defaultSort('id', 'desc')
            ->columns([
                TextColumn::make('bill.number')
                    ->label('Bill')
                    ->fontFamily('mono')
                    ->weight('medium')
                    ->url(fn (BillItem $record) => BillResource::canEdit($record->bill)
                        ? BillResource::getUrl('edit', ['record' => $record->bill])
                        : null),
                TextColumn::make('bill.bill_date')
                    ->label('Date')
                    ->date('d M Y'),
                TextColumn::make('bill.party_name')
                    ->label('Customer'),
                TextColumn::make('quantity')
                    ->label('Qty')
                    ->alignCenter(),
                TextColumn::make('unit_price')
                    ->label('Unit price')
                    ->money($currency),
                TextColumn::make('line_total')
                    ->label('Total')
                    ->money($currency)
                    ->weight('medium'),
                TextColumn::make('bill.status')
                    ->label('Payment')
                    ->badge(),
            ])
            ->emptyStateHeading('Not sold yet')
            ->emptyStateDescription('Sales of this product will show up here.')
            ->emptyStateIcon(Heroicon::OutlinedReceiptPercent);
    }
}
