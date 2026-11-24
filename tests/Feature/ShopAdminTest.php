<?php

namespace Tests\Feature;

use App\Filament\Resources\Admins\Pages\ListAdmins;
use App\Filament\Resources\Bills\Pages\ListBills;
use App\Filament\Resources\Categories\Pages\ManageCategories;
use App\Filament\Resources\Products\Pages\CreateProduct;
use App\Filament\Resources\Products\Pages\EditProduct;
use App\Filament\Resources\Products\Pages\ListProducts;
use App\Filament\Resources\Products\ProductResource;
use App\Filament\Resources\Roles\Pages\ListRoles;
use App\Filament\Resources\Subcategories\Pages\ManageSubcategories;
use App\Filament\Widgets\ShopOverview;
use App\Models\Admin;
use App\Models\Category;
use App\Models\Product;
use App\Models\Subcategory;
use Database\Seeders\AdminSeeder;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\RestoreAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ShopAdminTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
        $this->seed(AdminSeeder::class);
        $this->actingAs(Admin::where('email', 'admin@admin.com')->first(), 'admin');
    }

    private function product(array $attributes = []): Product
    {
        return Product::create($attributes + [
            'name' => 'Onyx Bracelet',
            'selling_price' => 40, 'initial_price' => 15, 'quantity' => 10,
        ]);
    }

    public function test_shop_pages_render(): void
    {
        $category = Category::create(['name' => 'Bracelets']);
        $product = $this->product(['category_id' => $category->id]);

        foreach (['/admin', '/admin/categories', '/admin/subcategories',
            '/admin/products', '/admin/products/create', "/admin/products/{$product->id}/edit"] as $url) {
            $this->get($url)->assertOk();
        }
    }

    public function test_create_toggle_and_delete_category(): void
    {
        Livewire::test(ManageCategories::class)
            ->callAction('create', data: ['name' => 'Necklaces', 'is_published' => true])
            ->assertHasNoFormErrors();

        $category = Category::where('name', 'Necklaces')->firstOrFail();
        $this->assertTrue($category->is_published);

        // Duplicate names are rejected.
        Livewire::test(ManageCategories::class)
            ->callAction('create', data: ['name' => 'Necklaces'])
            ->assertHasFormErrors(['name' => 'unique']);

        // The Published switch in the list saves straight away.
        Livewire::test(ManageCategories::class)
            ->call('updateTableColumnState', 'is_published', (string) $category->getKey(), false);
        $this->assertFalse($category->fresh()->is_published);

        // Deleting keeps its products, just without a category.
        $product = $this->product(['category_id' => $category->id]);
        Livewire::test(ManageCategories::class)->callTableAction('delete', $category);
        $this->assertDatabaseMissing('categories', ['id' => $category->id]);
        $this->assertNull($product->fresh()->category_id);
    }

    public function test_every_product_gets_a_unique_sku_that_never_changes(): void
    {
        $skus = collect(range(1, 50))->map(fn ($i) => $this->product(['name' => "P{$i}"])->sku);

        $this->assertCount(50, $skus->unique());

        $product = $this->product();
        $original = $product->sku;
        $product->update(['name' => 'Renamed', 'sku' => 'HACKED']); // sku is not fillable
        $this->assertSame($original, $product->fresh()->sku);

        // The SKU field on the form is display-only.
        Livewire::test(EditProduct::class, ['record' => $product->getRouteKey()])
            ->fillForm(['sku' => 'HACKED-2'])
            ->call('save')
            ->assertHasNoFormErrors();
        $this->assertSame($original, $product->fresh()->sku);
    }

    public function test_subcategories_depend_on_the_category(): void
    {
        $bracelets = Category::create(['name' => 'Bracelets']);
        $necklaces = Category::create(['name' => 'Necklaces']);
        $charm = Subcategory::create(['category_id' => $bracelets->id, 'name' => 'Charm']);
        $choker = Subcategory::create(['category_id' => $necklaces->id, 'name' => 'Choker']);

        Livewire::test(CreateProduct::class)
            ->fillForm(['category_id' => $bracelets->id])
            ->assertFormFieldExists('subcategory_id', function ($field) use ($charm, $choker) {
                $options = $field->getOptions();

                return isset($options[$charm->id]) && ! isset($options[$choker->id]);
            })
            ->fillForm(['subcategory_id' => $charm->id])
            // Switching category clears the subcategory.
            ->fillForm(['category_id' => $necklaces->id])
            ->assertSchemaStateSet(['subcategory_id' => null])
            ->fillForm([
                'name' => 'Pearl Choker', 'subcategory_id' => $choker->id,
                'selling_price' => 30, 'initial_price' => 10, 'quantity' => 2,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $product = Product::where('name', 'Pearl Choker')->firstOrFail();
        $this->assertSame($necklaces->id, $product->category_id);
        $this->assertSame($choker->id, $product->subcategory_id);

        // A subcategory from another category is never kept.
        $product->update(['category_id' => $bracelets->id, 'subcategory_id' => $choker->id]);
        $this->assertNull($product->fresh()->subcategory_id);
    }

    public function test_subcategory_field_is_disabled_until_category_has_subcategories(): void
    {
        $bracelets = Category::create(['name' => 'Bracelets']);
        $rings = Category::create(['name' => 'Rings']); // no subcategories
        $charm = Subcategory::create(['category_id' => $bracelets->id, 'name' => 'Charm']);

        Livewire::test(CreateProduct::class)
            ->assertFormFieldDisabled('subcategory_id')          // nothing chosen yet
            ->fillForm(['category_id' => $rings->id])
            ->assertFormFieldDisabled('subcategory_id')          // category without subcategories
            ->fillForm(['category_id' => $bracelets->id])
            ->assertFormFieldEnabled('subcategory_id');          // now it can be picked

        // Moving a product to a category without subcategories clears the old one.
        $product = $this->product(['category_id' => $bracelets->id, 'subcategory_id' => $charm->id]);

        Livewire::test(EditProduct::class, ['record' => $product->getRouteKey()])
            ->assertFormFieldEnabled('subcategory_id')
            ->fillForm(['category_id' => $rings->id])
            ->assertFormFieldDisabled('subcategory_id')
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame($rings->id, $product->fresh()->category_id);
        $this->assertNull($product->fresh()->subcategory_id);

        // Clearing the category doesn't bring the old one back either.
        $product->update(['category_id' => $bracelets->id, 'subcategory_id' => $charm->id]);

        Livewire::test(EditProduct::class, ['record' => $product->getRouteKey()])
            ->fillForm(['category_id' => null])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertNull($product->fresh()->category_id);
        $this->assertNull($product->fresh()->subcategory_id);
    }

    public function test_manage_subcategories(): void
    {
        $bracelets = Category::create(['name' => 'Bracelets']);
        $necklaces = Category::create(['name' => 'Necklaces']);

        Livewire::test(ManageSubcategories::class)
            ->callAction('create', data: ['category_id' => $bracelets->id, 'name' => 'Beaded'])
            ->assertHasNoFormErrors()
            // Same name in the same category is rejected...
            ->callAction('create', data: ['category_id' => $bracelets->id, 'name' => 'Beaded'])
            ->assertHasFormErrors(['name' => 'unique']);

        // ...but allowed under a different category.
        Livewire::test(ManageSubcategories::class)
            ->callAction('create', data: ['category_id' => $necklaces->id, 'name' => 'Beaded'])
            ->assertHasNoFormErrors();

        $this->assertSame(2, Subcategory::where('name', 'Beaded')->count());

        // Deleting a category removes its subcategories; products stay.
        $product = $this->product(['subcategory_id' => Subcategory::where('category_id', $bracelets->id)->value('id')]);
        $this->assertSame($bracelets->id, $product->category_id);

        Livewire::test(ManageCategories::class)->callTableAction('delete', $bracelets);
        $this->assertSame(0, Subcategory::where('category_id', $bracelets->id)->count());
        $this->assertDatabaseHas('products', ['id' => $product->id, 'category_id' => null, 'subcategory_id' => null]);
    }

    public function test_create_product_with_many_images(): void
    {
        $category = Category::create(['name' => 'Bracelets']);

        Livewire::test(CreateProduct::class)
            ->fillForm([
                'name' => 'Pearl Bracelet',
                'category_id' => $category->id,
                'description' => '<p>Freshwater pearls.</p>',
                'initial_price' => 12.5,
                'selling_price' => 45,
                'discount_price' => 36,
                'quantity' => 7,
                'is_published' => true,
                'gallery' => [
                    UploadedFile::fake()->image('one.jpg'),
                    UploadedFile::fake()->image('two.jpg'),
                    UploadedFile::fake()->image('three.jpg'),
                ],
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $product = Product::where('name', 'Pearl Bracelet')->firstOrFail();
        $this->assertMatchesRegularExpression('/^DB-[2-9A-HJ-NP-Z]{6}$/', $product->sku);

        $this->assertSame(3, $product->images()->count());
        $this->assertEquals([0, 1, 2], $product->images->pluck('sort_order')->all());
        $this->assertSame($product->images->first()->id, $product->coverImage->id);
        $product->images->each(fn ($image) => Storage::disk('public')->assertExists($image->path));
        // Stored as random-timestamp.ext, never the uploaded file name.
        $product->images->each(fn ($image) => $this->assertMatchesRegularExpression('#^products/[a-z0-9]{16}-\d{10}\.(jpg|jpeg)$#', $image->path));

        $this->assertTrue($product->is_published);
        $this->assertNotNull($product->published_at);
        $this->assertSame(20, $product->discountPercent());
        $this->assertEquals('36.00', $product->final_price);
    }

    public function test_editing_images_reorders_and_deletes_removed_files(): void
    {
        $product = $this->product();
        foreach (['products/a.jpg', 'products/b.jpg', 'products/c.jpg'] as $i => $path) {
            Storage::disk('public')->put($path, 'x');
            $product->images()->create(['path' => $path, 'sort_order' => $i]);
        }

        Livewire::test(EditProduct::class, ['record' => $product->getRouteKey()])
            ->assertSchemaStateSet(fn (array $state) => $this->assertCount(3, $state['gallery']) ?? [])
            ->fillForm(['gallery' => ['products/c.jpg', 'products/a.jpg']])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertEquals(['products/c.jpg', 'products/a.jpg'], $product->images()->pluck('path')->all());
        Storage::disk('public')->assertMissing('products/b.jpg');
        Storage::disk('public')->assertExists('products/a.jpg');
    }

    public function test_discount_must_be_lower_than_selling_price(): void
    {
        Livewire::test(CreateProduct::class)
            ->fillForm(['name' => 'X', 'selling_price' => 20, 'discount_price' => 25, 'initial_price' => 5, 'quantity' => 1])
            ->call('create')
            ->assertHasFormErrors(['discount_price' => 'lt']);

        Livewire::test(CreateProduct::class)
            ->fillForm(['name' => 'Y', 'selling_price' => 20, 'discount_price' => null, 'initial_price' => 5, 'quantity' => 1])
            ->call('create')
            ->assertHasNoFormErrors();
    }

    public function test_soft_delete_restore_and_force_delete(): void
    {
        $product = $this->product();
        Storage::disk('public')->put('products/a.jpg', 'x');
        $product->images()->create(['path' => 'products/a.jpg']);

        Livewire::test(ListProducts::class)->callTableAction('delete', $product);
        $this->assertSoftDeleted($product);
        Storage::disk('public')->assertExists('products/a.jpg'); // still recoverable

        Livewire::test(EditProduct::class, ['record' => $product->getRouteKey()])->callAction(RestoreAction::class);
        $this->assertNotSoftDeleted($product);

        $product->delete();
        Livewire::test(EditProduct::class, ['record' => $product->getRouteKey()])->callAction(ForceDeleteAction::class);
        $this->assertDatabaseMissing('products', ['id' => $product->id]);
        $this->assertDatabaseMissing('product_images', ['product_id' => $product->id]);
        Storage::disk('public')->assertMissing('products/a.jpg');
    }

    public function test_dashboard_restock_count_matches_the_list_it_opens(): void
    {
        foreach ([0, 3, 5, 6, 40] as $i => $qty) {
            $this->product(['name' => "P{$i}", 'quantity' => $qty]);
        }

        // 0 = out, 3 and 5 = low (threshold 5), 6 and 40 = in stock
        $this->assertSame(2, Product::lowStock()->count());
        $this->assertSame(1, Product::outOfStock()->count());
        $this->assertSame(3, Product::needsRestock()->count());
        $this->assertSame('3', ProductResource::getNavigationBadge());

        // Dashboard widgets load after the page, so render the widget itself.
        Livewire::test(ShopOverview::class)
            ->assertSee('Needs restocking')
            ->assertSee('2 low · 1 out of stock')
            ->assertSee('stock%5D%5Bvalue%5D=restock', escape: false);

        // The card links to the "restock" filter, which shows exactly those 3.
        Livewire::test(ListProducts::class)
            ->filterTable('stock', 'restock')
            ->assertCountTableRecords(3)
            ->filterTable('stock', 'low')
            ->assertCountTableRecords(2)
            ->filterTable('stock', 'out')
            ->assertCountTableRecords(1)
            ->filterTable('stock', 'in')
            ->assertCountTableRecords(2);
    }

    public function test_table_row_actions_are_icon_only(): void
    {
        $pages = [
            ListProducts::class,
            ManageCategories::class,
            ManageSubcategories::class,
            ListAdmins::class,
            ListRoles::class,
            ListBills::class,
        ];

        foreach ($pages as $page) {
            $actions = Livewire::test($page)->instance()->getTable()->getRecordActions();

            $this->assertNotEmpty($actions, $page);
            foreach ($actions as $action) {
                $this->assertTrue($action->isLabelHidden(), $action->getName().' on '.class_basename($page).' should be icon-only');
                $this->assertNotEmpty($action->getTooltip(), $action->getName().' on '.class_basename($page).' needs a tooltip');
            }
        }
    }

    public function test_bulk_publish(): void
    {
        $a = $this->product();
        $b = $this->product(['name' => 'Other']);

        Livewire::test(ListProducts::class)->callTableBulkAction('publish', [$a, $b]);

        $this->assertTrue($a->fresh()->is_published);
        $this->assertTrue($b->fresh()->is_published);
    }

    public function test_permissions_control_shop_access(): void
    {
        Role::findOrCreate('Stock Keeper', 'admin')->syncPermissions(['products.view', 'products.update']);
        $keeper = Admin::create(['name' => 'K', 'email' => 'k@example.com', 'password' => 'password123']);
        $keeper->assignRole('Stock Keeper');
        $this->actingAs($keeper, 'admin');

        $product = $this->product();

        $this->get('/admin/products')->assertOk();
        $this->get("/admin/products/{$product->id}/edit")->assertOk();
        $this->get('/admin/products/create')->assertForbidden();
        $this->get('/admin/categories')->assertForbidden();
        Livewire::test(ListProducts::class)->assertTableActionHidden('delete', $product);
    }
}
