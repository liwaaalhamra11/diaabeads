<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\Products\ProductResource;
use App\Models\Category;
use App\Models\Product;
use App\Support\Money;
use Filament\Support\Icons\Heroicon;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class ShopOverview extends StatsOverviewWidget
{
    protected static ?int $sort = 1;

    public static function canView(): bool
    {
        return ProductResource::canViewAny();
    }

    protected function getStats(): array
    {
        $products = Product::count();
        $published = Product::published()->count();
        $low = Product::lowStock()->count();
        $out = Product::outOfStock()->count();
        $restock = $low + $out;
        $stockValue = (float) Product::query()->selectRaw('COALESCE(SUM(quantity * initial_price), 0) as total')->value('total');

        return [
            Stat::make('Products', $products)
                ->description("{$published} published · ".($products - $published).' draft')
                ->descriptionIcon(Heroicon::OutlinedEye)
                ->icon(Heroicon::OutlinedShoppingBag)
                ->url(ProductResource::getUrl()),
            Stat::make('Categories', Category::count())
                ->icon(Heroicon::OutlinedTag)
                ->description('Organising your shop'),
            Stat::make('Needs restocking', $restock)
                ->description($restock ? "{$low} low · {$out} out of stock" : 'All products well stocked')
                ->descriptionIcon($restock ? Heroicon::OutlinedExclamationTriangle : Heroicon::OutlinedCheckCircle)
                ->icon(Heroicon::OutlinedArchiveBox)
                ->color($restock ? 'bronze' : 'gray')
                // Opens the product list filtered to exactly these products.
                ->url(ProductResource::getUrl('index', ['filters' => ['stock' => ['value' => 'restock']]])),
            Stat::make('Stock value (cost)', Money::format($stockValue))
                ->icon(Heroicon::OutlinedBanknotes)
                ->description('Quantity × initial price'),
        ];
    }
}
