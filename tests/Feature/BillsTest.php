<?php

namespace Tests\Feature;

use App\Documents\BillPdf;
use App\Enums\BillStatus;
use App\Enums\BillType;
use App\Enums\PaymentMethod;
use App\Filament\Resources\Bills\BillResource;
use App\Filament\Resources\Bills\Pages\CreateBill;
use App\Filament\Resources\Bills\Pages\EditBill;
use App\Filament\Resources\Bills\Pages\ListBills;
use App\Filament\Resources\Bills\Pages\ViewBill;
use App\Models\Admin;
use App\Models\Bill;
use App\Models\Product;
use Database\Seeders\AdminSeeder;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Forms\Components\Repeater;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class BillsTest extends TestCase
{
    use RefreshDatabase;

    private Admin $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(AdminSeeder::class);
        $this->admin = Admin::where('email', 'admin@admin.com')->first();
        $this->actingAs($this->admin, 'admin');
        Repeater::fake();
    }

    private function product(string $name, int $stock, float $price = 20, ?float $discount = null): Product
    {
        return Product::create([
            'name' => $name, 'quantity' => $stock, 'initial_price' => 5,
            'selling_price' => $price, 'discount_price' => $discount,
        ]);
    }

    private function sale(array $items, array $extra = []): Bill
    {
        Livewire::test(CreateBill::class)
            ->fillForm(['type' => 'in'])
            ->fillForm($extra + [
                'party_name' => 'Maya',
                'bill_date' => '2026-11-20',
                'status' => 'paid',
                'payment_method' => 'cash',
                'items' => $items,
            ])
            ->call('create')
            ->assertHasNoFormErrors()
            ->assertRedirect(BillResource::getUrl('index'));

        return Bill::latest('id')->first();
    }

    public function test_pages_render(): void
    {
        $bill = Bill::create(['type' => 'out', 'party_name' => 'Landlord', 'status' => 'pending']);

        $this->get('/admin/bills')->assertOk();
        $this->get('/admin/bills/create?type=in')->assertOk();
        $this->get('/admin/bills/create?type=out')->assertOk();
        $this->get("/admin/bills/{$bill->id}/edit")->assertOk();
        $this->get('/admin')->assertOk();
    }

    public function test_line_table_has_no_empty_cells_that_shift_columns(): void
    {
        foreach (['in' => 'Choose a product', 'out' => 'e.g. Silver clasps'] as $type => $firstField) {
            $html = $this->get("/admin/bills/create?type={$type}")->assertOk()->getContent();

            $this->assertStringNotContainsString('<td class="fi-hidden"></td>', $html, "{$type} bill");
            $this->assertStringContainsString($firstField, $html);
        }
    }

    public function test_changing_the_product_on_a_line_updates_its_name(): void
    {
        $bracelet = $this->product('Bracelet', 10);
        $ring = $this->product('Ring', 10);
        $bill = $this->sale([['product_id' => $bracelet->id, 'quantity' => 1, 'unit_price' => 20]]);

        $line = $bill->items()->first();
        $line->update(['product_id' => $ring->id]);

        $this->assertSame("Ring ({$ring->sku})", $line->fresh()->description);
    }

    public function test_sale_takes_products_out_of_stock_and_totals_add_up(): void
    {
        $bracelet = $this->product('Bracelet', 10, 40, 30); // on sale
        $ring = $this->product('Ring', 5, 15);

        Livewire::test(CreateBill::class)
            ->fillForm(['type' => 'in'])
            ->fillForm(['items' => [['product_id' => $bracelet->id]]])
            // Picking a product fills in its current price.
            ->assertSchemaStateSet(fn (array $state) => $this->assertEquals(30, $state['items'][array_key_first($state['items'])]['unit_price']) ?? []);

        $bill = $this->sale([
            ['product_id' => $bracelet->id, 'quantity' => 2, 'unit_price' => 30],
            ['product_id' => $ring->id, 'quantity' => 3, 'unit_price' => 15],
        ], ['discount' => 5]);

        $this->assertSame('IN-00001', $bill->number);
        $this->assertSame(BillType::In, $bill->type);
        $this->assertEquals(105, $bill->subtotal);  // 2×30 + 3×15
        $this->assertEquals(100, $bill->total);     // − 5 discount
        $this->assertSame(BillStatus::Paid, $bill->status);
        $this->assertSame(PaymentMethod::Cash, $bill->payment_method);
        $this->assertNotNull($bill->paid_at);
        $this->assertSame($this->admin->id, $bill->created_by);
        $this->assertSame('Bracelet ('.$bracelet->sku.')', $bill->items[0]->description);

        $this->assertSame(8, $bracelet->fresh()->quantity);
        $this->assertSame(2, $ring->fresh()->quantity);
    }

    public function test_cannot_sell_more_than_in_stock(): void
    {
        $ring = $this->product('Ring', 2);

        Livewire::test(CreateBill::class)
            ->fillForm(['type' => 'in'])
            ->fillForm([
                'party_name' => 'Maya', 'status' => 'paid', 'payment_method' => 'cash',
                'items' => [['product_id' => $ring->id, 'quantity' => 3, 'unit_price' => 20]],
            ])
            ->call('create')
            ->assertHasFormErrors(['items.0.quantity']);

        $this->assertSame(0, Bill::count());
        $this->assertSame(2, $ring->fresh()->quantity);
    }

    public function test_editing_a_sale_adjusts_stock(): void
    {
        $bracelet = $this->product('Bracelet', 10);
        $ring = $this->product('Ring', 10);

        $bill = $this->sale([
            ['product_id' => $bracelet->id, 'quantity' => 2, 'unit_price' => 20],
            ['product_id' => $ring->id, 'quantity' => 4, 'unit_price' => 20],
        ]);
        $this->assertSame(8, $bracelet->fresh()->quantity);
        $this->assertSame(6, $ring->fresh()->quantity);

        // Can sell up to stock + what this line already took (8 + 2 = 10).
        Livewire::test(EditBill::class, ['record' => $bill->getRouteKey()])
            ->fillForm(['items' => [['product_id' => $bracelet->id, 'quantity' => 10, 'unit_price' => 20]]])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame(0, $bracelet->fresh()->quantity);
        $this->assertSame(10, $ring->fresh()->quantity); // removed line went back to stock
        $this->assertEquals(200, $bill->fresh()->total);
    }

    public function test_changing_a_line_in_place_adjusts_stock(): void
    {
        $bracelet = $this->product('Bracelet', 10);
        $ring = $this->product('Ring', 10);
        $bill = $this->sale([['product_id' => $bracelet->id, 'quantity' => 2, 'unit_price' => 20]]);
        $line = $bill->items()->first();

        $line->update(['quantity' => 5]);
        $this->assertSame(5, $bracelet->fresh()->quantity);

        $line->update(['product_id' => $ring->id, 'quantity' => 1]); // swap product
        $this->assertSame(10, $bracelet->fresh()->quantity);
        $this->assertSame(9, $ring->fresh()->quantity);
        $this->assertEquals(20, $bill->fresh()->total);
    }

    public function test_deleting_and_restoring_a_sale_moves_stock(): void
    {
        $ring = $this->product('Ring', 10);
        $bill = $this->sale([['product_id' => $ring->id, 'quantity' => 3, 'unit_price' => 20]]);
        $this->assertSame(7, $ring->fresh()->quantity);

        Livewire::test(ListBills::class)->callTableAction('delete', $bill);
        $this->assertSoftDeleted($bill);
        $this->assertSame(10, $ring->fresh()->quantity);

        Livewire::test(EditBill::class, ['record' => $bill->getRouteKey()])->callAction(RestoreAction::class);
        $this->assertSame(7, $ring->fresh()->quantity);

        $bill->delete();
        Livewire::test(EditBill::class, ['record' => $bill->getRouteKey()])->callAction(ForceDeleteAction::class);
        $this->assertDatabaseMissing('bills', ['id' => $bill->id]);
        $this->assertSame(10, $ring->fresh()->quantity); // not returned twice
    }

    public function test_pending_expense_then_mark_as_paid(): void
    {
        $ring = $this->product('Ring', 10);

        Livewire::test(CreateBill::class)
            ->fillForm(['type' => 'out'])
            ->fillForm([
                'party_name' => 'Beads Wholesale',
                'bill_date' => today()->toDateString(),
                'status' => 'pending',
                'due_date' => today()->addWeek()->toDateString(),
                'description' => 'Monthly bead order',
                'notes' => 'Ask for the bulk discount next time',
                'items' => [
                    ['description' => 'Glass beads 1kg', 'quantity' => 2, 'unit_price' => 25],
                    ['description' => 'Delivery', 'quantity' => 1, 'unit_price' => 5],
                ],
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $bill = Bill::latest('id')->first();
        $this->assertSame('OUT-00001', $bill->number);
        $this->assertSame(BillStatus::Pending, $bill->status);
        $this->assertNull($bill->payment_method);
        $this->assertEquals(55, $bill->total);
        $this->assertSame('Monthly bead order', $bill->description);
        $this->assertSame(10, $ring->fresh()->quantity); // expenses never touch stock

        Livewire::test(ListBills::class)
            ->callTableAction('markPaid', $bill, data: ['payment_method' => 'whish'])
            ->assertHasNoTableActionErrors();

        $bill->refresh();
        $this->assertSame(BillStatus::Paid, $bill->status);
        $this->assertSame(PaymentMethod::Whish, $bill->payment_method);
        $this->assertNotNull($bill->paid_at);
        $this->assertNull($bill->due_date);
    }

    public function test_list_shows_payment_beside_status(): void
    {
        $paid = Bill::create(['type' => 'in', 'party_name' => 'Maya', 'status' => 'paid', 'payment_method' => 'whish']);
        $due = Bill::create(['type' => 'out', 'party_name' => 'Landlord', 'status' => 'pending', 'due_date' => today()->addDays(5)]);
        $late = Bill::create(['type' => 'out', 'party_name' => 'Courier', 'bill_date' => today()->subDays(10), 'status' => 'pending', 'due_date' => today()->subDays(2)]);
        $open = Bill::create(['type' => 'in', 'party_name' => 'Rami', 'status' => 'pending']);

        Livewire::test(ListBills::class)
            ->assertTableColumnStateSet('payment', 'Whish', $paid)
            ->assertTableColumnStateSet('payment', 'Due '.today()->addDays(5)->format('d M Y'), $due)
            ->assertTableColumnStateSet('payment', 'Overdue · '.today()->subDays(2)->format('d M'), $late)
            ->assertTableColumnStateSet('payment', 'No due date', $open)
            ->assertTableColumnExists('bill_date')
            ->assertTableActionVisible('markPaid', $due)
            ->assertTableActionHidden('markPaid', $paid);
    }

    public function test_view_page_for_a_sale(): void
    {
        $ring = $this->product('Silver Ring', 10, 25);
        $bill = $this->sale([['product_id' => $ring->id, 'quantity' => 2, 'unit_price' => 25]], [
            'party_name' => 'Maya', 'party_phone' => '+961 70 123 456', 'discount' => 5,
            'description' => 'Birthday gift', 'notes' => 'Wrap in black paper',
        ]);

        $this->assertSame('IN-00001', $bill->number);

        $this->get("/admin/bills/{$bill->id}")
            ->assertOk()
            ->assertSee('Bill IN-00001')
            ->assertSee('Maya')
            ->assertSee('tel:+96170123456', false)
            ->assertSee('Silver Ring')
            ->assertSee($ring->sku)
            ->assertSee('Sale  ·  Paid  ·  from Maya')
            ->assertSee('$50.00')   // line total
            ->assertSee('$45.00')   // after discount
            ->assertSee('Birthday gift')
            ->assertSee('Wrap in black paper')
            ->assertSee('Received')
            ->assertSee("/admin/products/{$ring->id}", false); // line links to the product
    }

    public function test_view_page_for_pending_expense_and_mark_as_paid(): void
    {
        $bill = Bill::create(['type' => 'out', 'party_name' => 'Landlord', 'status' => 'pending', 'due_date' => today()->subDay(), 'bill_date' => today()->subWeek()]);
        $bill->items()->create(['description' => 'November rent', 'quantity' => 1, 'unit_price' => 300]);

        $this->get("/admin/bills/{$bill->id}")
            ->assertOk()
            ->assertSee('November rent')
            ->assertSee('You still owe')
            ->assertSee('Overdue by 1 day(s)');

        Livewire::test(ViewBill::class, ['record' => $bill->getRouteKey()])
            ->assertActionVisible('markPaid')
            ->callAction('markPaid', data: ['payment_method' => 'cash'])
            ->assertHasNoActionErrors();

        $this->assertSame(BillStatus::Paid, $bill->fresh()->status);

        Livewire::test(ViewBill::class, ['record' => $bill->getRouteKey()])
            ->assertActionHidden('markPaid');
    }

    public function test_trashed_bill_can_be_viewed(): void
    {
        $bill = Bill::create(['type' => 'out', 'party_name' => 'Landlord']);
        $bill->delete();

        $this->get("/admin/bills/{$bill->id}")->assertOk()->assertSee('In trash');
    }

    public function test_bill_pdf(): void
    {
        $ring = $this->product('Silver Ring', 10, 25);
        $bill = $this->sale([['product_id' => $ring->id, 'quantity' => 2, 'unit_price' => 25]], [
            'party_name' => 'Maya', 'discount' => 5, 'description' => 'Birthday gift', 'notes' => 'SECRET ADMIN NOTE',
        ]);

        $pdf = new BillPdf($bill);
        $this->assertSame('bill-IN-00001.pdf', $pdf->filename());
        $this->assertStringStartsWith('%PDF', $pdf->output());

        $html = view('bills.pdf', ['bill' => $bill->load('items.product')])->render();
        $this->assertStringContainsString('Sales receipt', $html);
        $this->assertStringContainsString('Silver Ring', $html);
        $this->assertStringContainsString($ring->sku, $html);
        $this->assertStringContainsString('Birthday gift', $html);
        $this->assertStringContainsString('$45.00', $html);
        $this->assertStringNotContainsString('SECRET ADMIN NOTE', $html); // notes are admin-only

        // From the list (icon) and from the bill's page (button).
        Livewire::test(ListBills::class)
            ->assertTableActionVisible('downloadPdf', $bill)
            ->callTableAction('downloadPdf', $bill)
            ->assertFileDownloaded('bill-IN-00001.pdf');

        Livewire::test(ViewBill::class, ['record' => $bill->getRouteKey()])
            ->callAction('downloadPdf')
            ->assertFileDownloaded('bill-IN-00001.pdf');
    }

    public function test_expense_pdf_shows_amount_due(): void
    {
        $bill = Bill::create(['type' => 'out', 'party_name' => 'Landlord', 'status' => 'pending', 'due_date' => '2026-12-05']);
        $bill->items()->create(['description' => 'December rent', 'quantity' => 1, 'unit_price' => 300]);

        $html = view('bills.pdf', ['bill' => $bill->fresh()->load('items.product')])->render();
        $this->assertStringContainsString('Expense bill', $html);
        $this->assertStringContainsString('December rent', $html);
        $this->assertStringContainsString('Amount due', $html);
        $this->assertStringContainsString('Due 05 Dec 2026', $html);
    }

    public function test_in_and_out_have_separate_numbers(): void
    {
        $numbers = collect([
            Bill::create(['type' => 'in', 'party_name' => 'A']),
            Bill::create(['type' => 'out', 'party_name' => 'B']),
            Bill::create(['type' => 'in', 'party_name' => 'C']),
        ])->pluck('number')->all();

        $this->assertSame(['IN-00001', 'OUT-00001', 'IN-00002'], $numbers);
    }

    public function test_type_cannot_change_after_creation(): void
    {
        $bill = Bill::create(['type' => 'out', 'party_name' => 'Landlord']);

        Livewire::test(EditBill::class, ['record' => $bill->getRouteKey()])
            ->assertFormFieldDisabled('type');
    }

    public function test_admin_without_bill_permission_is_blocked(): void
    {
        $this->actingAs(tap(Admin::create(['name' => 'V', 'email' => 'v@example.com', 'password' => 'password123']))->assignRole('Viewer'), 'admin');

        $this->get('/admin/bills')->assertForbidden();
        $this->get('/admin')->assertOk(); // dashboard hides the finance cards
    }
}
