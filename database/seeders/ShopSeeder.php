<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Subcategory;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Shop categories and their subcategories (no products).
 *
 * Safe to run more than once — existing categories and subcategories
 * (matched by name) are left as they are.
 *
 *   php artisan db:seed --class=ShopSeeder
 */
class ShopSeeder extends Seeder
{
    /** Category => its subcategories (empty when it has none). */
    public const CATEGORIES = [
        'Bracelets' => ['Men', 'Women', 'Couples', 'Children'],
        'Necklaces' => [],
        'Glasses Straps' => [],
        'Phone Straps' => [],
        'Scarves' => ['Men', 'Women'],
        'Foulard' => [],
        'Bags' => ['Men', 'Women'],
        'Watches' => ['Men', 'Women'],
        'Caps' => [],
        'Beanies' => [],
        'Wallets' => ['Men', 'Women'],
        'Socks' => [],
        'Sunglasses' => ['Men', 'Women'],
        'Phone Pins' => [],
        'Keychains' => [],
        'Rosaries' => [],
        'Hand Fans' => [],
    ];

    public function run(): void
    {
        DB::transaction(function () {
            foreach (self::CATEGORIES as $categoryName => $subcategories) {
                $category = Category::firstOrCreate(['name' => $categoryName], ['is_published' => true]);

                foreach ($subcategories as $subcategoryName) {
                    Subcategory::firstOrCreate(
                        ['category_id' => $category->id, 'name' => $subcategoryName],
                        ['is_published' => true],
                    );
                }
            }
        });
    }
}
