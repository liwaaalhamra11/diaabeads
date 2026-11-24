<?php

namespace App\Filament\Resources\Products\Tables;

use App\Models\Product;
use App\Support\Money;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\RestoreAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Actions\ViewAction;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

class ProductsTable
{
    public static function configure(Table $table): Table
    {
        $currency = Money::currency();
        $lowStock = config('shop.low_stock_threshold');

        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['coverImage', 'category', 'subcategory'])->withCount('images'))
            ->defaultSort('created_at', 'desc')
            ->columns([
                ImageColumn::make('coverImage.path')
                    ->label('')
                    ->disk(config('shop.images_disk'))
                    ->square()
                    ->imageSize(52),
                TextColumn::make('name')
                    ->searchable()
                    ->sortable()
                    ->weight('medium')
                    ->wrap()
                    ->description(fn (Product $record) => collect([$record->category?->name, $record->subcategory?->name])
                        ->filter()->join(' › ') ?: 'No category'),
                TextColumn::make('sku')
                    ->label('SKU')
                    ->searchable()
                    ->sortable()
                    ->copyable()
                    ->copyMessage('SKU copied')
                    ->fontFamily('mono')
                    ->color('gray'),
                TextColumn::make('selling_price')
                    ->label('Price')
                    ->sortable()
                    ->state(fn (Product $record) => $record->final_price)
                    ->money($currency)
                    ->description(fn (Product $record) => $record->hasDiscount()
                        ? 'Was '.Money::format($record->selling_price).' · −'.$record->discountPercent().'%'
                        : null),
                TextColumn::make('initial_price')
                    ->label('Cost')
                    ->money($currency)
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('quantity')
                    ->label('Stock')
                    ->sortable()
                    ->badge()
                    ->formatStateUsing(fn (int $state) => $state === 0 ? 'Out of stock' : "{$state} pcs")
                    ->color(fn (int $state) => match (true) {
                        $state === 0 => 'danger',
                        $state <= $lowStock => 'bronze',
                        default => 'gray',
                    }),
                TextColumn::make('images_count')
                    ->label('Photos')
                    ->icon(Heroicon::OutlinedPhoto)
                    ->sortable()
                    ->toggleable(),
                ToggleColumn::make('is_published')
                    ->label('Published'),
                TextColumn::make('created_at')
                    ->label('Added')
                    ->date()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('category')
                    ->relationship('category', 'name')
                    ->searchable()
                    ->preload()
                    ->multiple(),
                SelectFilter::make('subcategory')
                    ->relationship('subcategory', 'name')
                    ->getOptionLabelFromRecordUsing(fn ($record) => "{$record->category->name} › {$record->name}")
                    ->searchable()
                    ->preload()
                    ->multiple(),
                TernaryFilter::make('is_published')
                    ->label('Visibility')
                    ->trueLabel('Published')
                    ->falseLabel('Draft'),
                SelectFilter::make('stock')
                    ->options([
                        'in' => 'In stock',
                        'restock' => 'Needs restocking (low + out)',
                        'low' => "Low stock (1–{$lowStock})",
                        'out' => 'Out of stock',
                    ])
                    ->query(fn (Builder $query, array $data) => match ($data['value'] ?? null) {
                        'in' => $query->inStock(),
                        'restock' => $query->needsRestock(),
                        'low' => $query->lowStock(),
                        'out' => $query->outOfStock(),
                        default => $query,
                    }),
                TernaryFilter::make('on_sale')
                    ->label('On sale')
                    ->queries(
                        true: fn (Builder $query) => $query->whereNotNull('discount_price')->whereColumn('discount_price', '<', 'selling_price'),
                        false: fn (Builder $query) => $query->where(fn (Builder $q) => $q->whereNull('discount_price')->orWhereColumn('discount_price', '>=', 'selling_price')),
                    ),
                TrashedFilter::make(),
            ])
            ->recordActions([
                ViewAction::make()->hiddenLabel()->tooltip('View'),
                EditAction::make()->hiddenLabel()->tooltip('Edit'),
                DeleteAction::make()->hiddenLabel()->tooltip('Delete'),
                RestoreAction::make()->hiddenLabel()->tooltip('Restore'),
                ForceDeleteAction::make()->hiddenLabel()->tooltip('Delete forever'),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    BulkAction::make('publish')
                        ->label('Publish')
                        ->icon(Heroicon::OutlinedEye)
                        ->authorize('updateAny')
                        ->action(fn (Collection $records) => $records->each->update(['is_published' => true]))
                        ->deselectRecordsAfterCompletion()
                        ->successNotificationTitle('Products published'),
                    BulkAction::make('unpublish')
                        ->label('Move to draft')
                        ->icon(Heroicon::OutlinedEyeSlash)
                        ->authorize('updateAny')
                        ->action(fn (Collection $records) => $records->each->update(['is_published' => false]))
                        ->deselectRecordsAfterCompletion()
                        ->successNotificationTitle('Products moved to draft'),
                    DeleteBulkAction::make(),
                    RestoreBulkAction::make(),
                    ForceDeleteBulkAction::make(),
                ]),
            ])
            ->emptyStateHeading('No products yet')
            ->emptyStateDescription('Add your first piece: name, photos, prices and stock.');
    }
}
