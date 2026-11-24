<?php

namespace Tests\Feature;

use App\Filament\Resources\Products\Pages\ViewProduct;
use App\Filament\Resources\Products\RelationManagers\SalesRelationManager;
use App\Models\Admin;
use App\Models\Bill;
use App\Models\Category;
use App\Models\Product;
use Database\Seeders\AdminSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ProductViewTest extends TestCase
{
    use RefreshDatabase;

    private Product $product;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
        $this->seed(AdminSeeder::class);
        $this->actingAs(Admin::where('email', 'admin@admin.com')->first(), 'admin');

        $category = Category::create(['name' => 'Bracelets']);
        $this->product = Product::create([
            'name' => 'Onyx Bracelet', 'category_id' => $category->id, 'description' => '<p>Matte <strong>black</strong> onyx.</p>',
            'quantity' => 10, 'initial_price' => 10, 'selling_price' => 40, 'discount_price' => 30, 'is_published' => true,
        ]);
    }

    private function sell(int $quantity, float $price = 30, string $customer = 'Maya'): Bill
    {
        $bill = Bill::create(['type' => 'in', 'party_name' => $customer, 'status' => 'paid']);
        $bill->items()->create(['product_id' => $this->product->id, 'quantity' => $quantity, 'unit_price' => $price]);

        return $bill->fresh();
    }

    public function test_view_page_shows_product_details_and_gallery(): void
    {
        foreach (['a', 'b'] as $i => $name) {
            Storage::disk('public')->put("products/{$name}.jpg", 'x');
            $this->product->images()->create(['path' => "products/{$name}.jpg", 'sort_order' => $i]);
        }

        $this->get("/admin/products/{$this->product->id}")
            ->assertOk()
            ->assertSee('Onyx Bracelet')
            ->assertSee($this->product->sku)
            ->assertSee('Bracelets')
            ->assertSee('<strong>black</strong>', escape: false)   // description keeps formatting
            ->assertSee('products/a.jpg')
            ->assertSee('products/b.jpg')
            ->assertSee('25% off');                                // 30 vs 40
    }

    public function test_photo_urls_follow_the_address_the_site_is_opened_on(): void
    {
        $this->product->images()->create(['path' => 'products/a.jpg']);
        $url = "/admin/products/{$this->product->id}";

        // php artisan serve on 127.0.0.1 (APP_URL says localhost)
        $this->get('http://127.0.0.1:8000'.$url)
            ->assertOk()
            ->assertSee('http://127.0.0.1:8000/storage/products/a.jpg');

        // MAMP: site lives in a sub-folder on port 8888
        $this->call('GET', 'http://localhost:8888/DiaaBeads/public/index.php'.$url, server: [
            'SCRIPT_NAME' => '/DiaaBeads/public/index.php',
            'SCRIPT_FILENAME' => public_path('index.php'),
        ])
            ->assertOk()
            ->assertSee('http://localhost:8888/DiaaBeads/public/storage/products/a.jpg');
    }

    public function test_view_page_without_photos_offers_to_add_them(): void
    {
        $this->get("/admin/products/{$this->product->id}")
            ->assertOk()
            ->assertSee('No photos yet')
            ->assertSee('Add photos');
    }

    public function test_sales_summary_and_history(): void
    {
        $this->sell(2, 30, 'Maya');
        $this->sell(1, 35, 'Rami');
        $trashed = $this->sell(4);
        $trashed->delete(); // trashed sales don't count

        $summary = $this->product->fresh()->salesSummary();
        $this->assertSame(3, $summary['units']);
        $this->assertEquals(95, $summary['revenue']);
        $this->assertSame(2, $summary['sales']);

        Livewire::test(SalesRelationManager::class, ['ownerRecord' => $this->product, 'pageClass' => ViewProduct::class])
            ->assertOk()
            ->assertCanSeeTableRecords($this->product->soldItems()->get())
            ->assertCountTableRecords(2)
            ->assertSee('Maya')
            ->assertSee('Rami');
    }

    public function test_publish_toggle_from_view_page(): void
    {
        Livewire::test(ViewProduct::class, ['record' => $this->product->getRouteKey()])
            ->callAction('togglePublish');
        $this->assertFalse($this->product->fresh()->is_published);

        Livewire::test(ViewProduct::class, ['record' => $this->product->getRouteKey()])
            ->callAction('togglePublish');
        $this->assertTrue($this->product->fresh()->is_published);
    }

    public function test_trashed_product_can_still_be_viewed(): void
    {
        $this->product->delete();

        $this->get("/admin/products/{$this->product->id}")
            ->assertOk()
            ->assertSee('In trash');
    }

    public function test_view_only_admin_sees_page_but_cannot_edit(): void
    {
        Role::findOrCreate('Product Viewer', 'admin')->syncPermissions(['products.view']);
        $viewer = Admin::create(['name' => 'V', 'email' => 'v@example.com', 'password' => 'password123']);
        $viewer->assignRole('Product Viewer');
        $this->actingAs($viewer, 'admin');

        $this->get("/admin/products/{$this->product->id}")->assertOk();

        Livewire::test(ViewProduct::class, ['record' => $this->product->getRouteKey()])
            ->assertActionHidden('edit')
            ->assertActionHidden('togglePublish');

        // No bills permission → no sales history table.
        $this->assertFalse(SalesRelationManager::canViewForRecord($this->product, ViewProduct::class));
    }
}
