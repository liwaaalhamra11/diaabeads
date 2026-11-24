<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Models\Subcategory;
use Database\Seeders\ShopSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ShopSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_seeds_categories_and_subcategories_only_and_can_run_twice(): void
    {
        $this->seed(ShopSeeder::class);
        $this->seed(ShopSeeder::class);

        $this->assertSame(17, Category::count());
        $this->assertSame(14, Subcategory::count());
        $this->assertSame(0, Product::count());

        $this->assertEqualsCanonicalizing(
            ['Men', 'Women', 'Couples', 'Children'],
            Category::where('name', 'Bracelets')->first()->subcategories->pluck('name')->all(),
        );
        $this->assertSame(0, Category::where('name', 'Necklaces')->first()->subcategories()->count());
        $this->assertSame(17, Category::where('is_published', true)->count());
    }
}
