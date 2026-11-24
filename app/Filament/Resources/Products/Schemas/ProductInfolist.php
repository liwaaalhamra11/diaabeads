<?php

namespace App\Filament\Resources\Products\Schemas;

use App\Models\Product;
use App\Support\Money;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Components\ViewEntry;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Enums\TextSize;
use Filament\Support\Icons\Heroicon;

class ProductInfolist
{
    public static function configure(Schema $schema): Schema
    {
        $currency = Money::currency();
        $lowStock = config('shop.low_stock_threshold');

        return $schema
            ->columns(3)
            ->components([
                Group::make()
                    ->columnSpan(['lg' => 2])
                    ->schema([
                        Section::make('Photos')
                            ->icon(Heroicon::OutlinedPhoto)
                            ->description(fn (Product $record) => $record->images->count().' photo(s) · use ← → to browse')
                            ->schema([
                                ViewEntry::make('gallery')
                                    ->hiddenLabel()
                                    ->view('filament.products.gallery'),
                            ]),
                        Section::make('Description')
                            ->icon(Heroicon::OutlinedDocumentText)
                            ->schema([
                                TextEntry::make('description')
                                    ->hiddenLabel()
                                    ->html()
                                    ->prose()
                                    ->placeholder('No description yet.'),
                            ]),
                    ]),

                Group::make()
                    ->columnSpan(['lg' => 1])
                    ->schema([
                        Section::make('Overview')
                            ->icon(Heroicon::OutlinedShoppingBag)
                            ->schema([
                                TextEntry::make('is_published')
                                    ->label('Status')
                                    ->badge()
                                    ->formatStateUsing(fn (bool $state) => $state ? 'Published' : 'Draft')
                                    ->color(fn (bool $state) => $state ? 'gray' : 'bronze')
                                    ->icon(fn (bool $state) => $state ? Heroicon::OutlinedEye : Heroicon::OutlinedEyeSlash)
                                    ->helperText(fn (Product $record) => $record->published_at
                                        ? 'Live since '.$record->published_at->format('d M Y')
                                        : null),
                                TextEntry::make('sku')
                                    ->label('SKU')
                                    ->fontFamily('mono')
                                    ->copyable()
                                    ->copyMessage('SKU copied'),
                                TextEntry::make('category.name')
                                    ->label('Category')
                                    ->badge()
                                    ->color('gray')
                                    ->placeholder('No category'),
                                TextEntry::make('subcategory.name')
                                    ->label('Subcategory')
                                    ->badge()
                                    ->color('gray')
                                    ->placeholder('—'),
                            ]),

                        Section::make('Pricing')
                            ->icon(Heroicon::OutlinedBanknotes)
                            ->schema([
                                TextEntry::make('final_price')
                                    ->label('Customer pays')
                                    ->money($currency)
                                    ->size(TextSize::Large)
                                    ->weight('bold')
                                    ->helperText(fn (Product $record) => $record->hasDiscount()
                                        ? 'On sale: '.$record->discountPercent().'% off '.Money::format($record->selling_price)
                                        : null),
                                Grid::make(2)->schema([
                                    TextEntry::make('selling_price')
                                        ->label('Selling price')
                                        ->money($currency),
                                    TextEntry::make('discount_price')
                                        ->label('Discount price')
                                        ->money($currency)
                                        ->color('bronze')
                                        ->placeholder('None'),
                                    TextEntry::make('initial_price')
                                        ->label('Cost')
                                        ->money($currency)
                                        ->color('gray'),
                                    TextEntry::make('profit')
                                        ->label('Profit / piece')
                                        ->state(fn (Product $record) => $record->profitPerUnit())
                                        ->money($currency)
                                        ->color(fn (Product $record) => $record->profitPerUnit() < 0 ? 'danger' : 'bronze')
                                        ->helperText(fn (Product $record) => (float) $record->final_price > 0
                                            ? round($record->profitPerUnit() / (float) $record->final_price * 100).'% margin'
                                            : null),
                                ]),
                            ]),

                        Section::make('Inventory')
                            ->icon(Heroicon::OutlinedArchiveBox)
                            ->schema([
                                Grid::make(2)->schema([
                                    TextEntry::make('quantity')
                                        ->label('In stock')
                                        ->badge()
                                        ->formatStateUsing(fn (int $state) => $state === 0 ? 'Out of stock' : "{$state} pcs")
                                        ->color(fn (int $state) => match (true) {
                                            $state === 0 => 'danger',
                                            $state <= $lowStock => 'bronze',
                                            default => 'gray',
                                        }),
                                    TextEntry::make('stock_value')
                                        ->label('Stock value (cost)')
                                        ->state(fn (Product $record) => $record->quantity * (float) $record->initial_price)
                                        ->money($currency),
                                ]),
                            ]),

                        Section::make('Sales')
                            ->icon(Heroicon::OutlinedChartBar)
                            ->description('From sale (IN) bills, before bill discounts.')
                            ->schema([
                                Grid::make(2)->schema([
                                    TextEntry::make('units_sold')
                                        ->label('Pieces sold')
                                        ->state(fn (Product $record) => $record->salesSummary()['units']),
                                    TextEntry::make('revenue')
                                        ->label('Revenue')
                                        ->state(fn (Product $record) => $record->salesSummary()['revenue'])
                                        ->money($currency),
                                    TextEntry::make('sales_count')
                                        ->label('Bills')
                                        ->state(fn (Product $record) => $record->salesSummary()['sales']),
                                    TextEntry::make('last_sold')
                                        ->label('Last sold')
                                        ->state(fn (Product $record) => $record->salesSummary()['last_sold_at']?->format('d M Y'))
                                        ->placeholder('Not sold yet'),
                                ]),
                            ]),

                        Section::make()
                            ->schema([
                                TextEntry::make('created_at')
                                    ->label('Added')
                                    ->dateTime('d M Y, H:i')
                                    ->color('gray'),
                                TextEntry::make('updated_at')
                                    ->label('Last updated')
                                    ->since()
                                    ->dateTimeTooltip()
                                    ->color('gray'),
                            ])
                            ->columns(2),
                    ]),
            ]);
    }
}
