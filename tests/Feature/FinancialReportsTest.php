<?php

namespace Tests\Feature;

use App\Enums\PaymentMethod;
use App\Filament\Pages\FinancialReports;
use App\Models\Admin;
use App\Models\Bill;
use App\Models\Product;
use App\Reports\Exports\FinancialReportExcel;
use App\Reports\Exports\FinancialReportPdf;
use App\Reports\FinancialReport;
use Database\Seeders\AdminSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class FinancialReportsTest extends TestCase
{
    use RefreshDatabase;

    private Product $bracelet;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(AdminSeeder::class);
        $this->actingAs(Admin::where('email', 'admin@admin.com')->first(), 'admin');

        $this->bracelet = Product::create(['name' => 'Bracelet', 'quantity' => 100, 'initial_price' => 10, 'selling_price' => 40]);

        // November 2026
        $this->sale('2026-11-03', 3, 40, discount: 10);            // 110, paid, cost 30
        $this->sale('2026-11-03', 1, 40, status: 'pending');        // 40, pending, cost 10
        $this->expense('2026-11-05', 'Beads Wholesale', 50);        // paid
        $this->expense('2026-11-30', 'Landlord', 70, 'pending');    // last day of month, pending
        $this->sale('2026-11-30', 2, 40, method: 'whish');          // 80, paid by Whish, cost 20

        // Trashed: must be ignored everywhere.
        $this->sale('2026-11-10', 5, 40)->delete();

        // October 2026, for the comparison.
        $this->sale('2026-10-15', 2, 40);                           // 80
        $this->expense('2026-10-20', 'Beads Wholesale', 100);
    }

    private function sale(string $date, int $qty, float $price, float $discount = 0, string $status = 'paid', string $method = 'cash'): Bill
    {
        $bill = Bill::create(['type' => 'in', 'party_name' => 'Customer', 'bill_date' => $date, 'status' => $status,
            'payment_method' => $status === 'paid' ? $method : null, 'discount' => $discount]);
        $bill->items()->create(['product_id' => $this->bracelet->id, 'quantity' => $qty, 'unit_price' => $price]);

        return $bill->fresh();
    }

    private function expense(string $date, string $party, float $amount, string $status = 'paid'): Bill
    {
        $bill = Bill::create(['type' => 'out', 'party_name' => $party, 'bill_date' => $date, 'status' => $status]);
        $bill->items()->create(['description' => 'Stuff', 'quantity' => 1, 'unit_price' => $amount]);

        return $bill->fresh();
    }

    public function test_monthly_summary_numbers(): void
    {
        $s = FinancialReport::monthly('2026-11-01')->summary();

        $this->assertEquals(230, $s['income']);          // 110 + 40 + 80
        $this->assertEquals(190, $s['income_paid']);
        $this->assertEquals(40, $s['income_pending']);
        $this->assertSame(3, $s['income_count']);
        $this->assertEquals(10, $s['discounts']);
        $this->assertEquals(120, $s['expenses']);        // 50 + 70 (incl. last day)
        $this->assertEquals(50, $s['expenses_paid']);
        $this->assertEquals(70, $s['expenses_pending']);
        $this->assertEquals(110, $s['net']);
        $this->assertEquals(140, $s['cash_flow']);       // 190 − 50
        $this->assertEquals(60, $s['cost_of_goods']);    // 6 pcs × 10
        $this->assertEquals(170, $s['gross_profit']);    // 230 − 60
        $this->assertSame(6, $s['items_sold']);
        $this->assertSame('profit', FinancialReport::result($s['net']));
    }

    public function test_loss_month_and_comparison(): void
    {
        $october = FinancialReport::monthly('2026-10-01');
        $this->assertEquals(-20, $october->summary()['net']);
        $this->assertSame('Negative · Loss', FinancialReport::resultLabel($october->summary()['net']));

        $change = FinancialReport::monthly('2026-11-01')->comparison();
        $this->assertEquals(187.5, $change['income']['change']);   // 80 → 230
        $this->assertEquals(650.0, $change['net']['change']);      // −20 → 110
    }

    public function test_daily_report_and_breakdowns(): void
    {
        $day = FinancialReport::daily('2026-11-30');
        $this->assertEquals(80, $day->summary()['income']);
        $this->assertEquals(70, $day->summary()['expenses']);
        $this->assertCount(2, $day->bills());

        $month = FinancialReport::monthly('2026-11-01');
        $rows = $month->dailyRows();
        $this->assertCount(30, $rows);
        $this->assertEquals(110, $rows->last()['running']);  // running balance ends at the net result

        $whish = $month->paymentMethods()->firstWhere('method', PaymentMethod::Whish);
        $this->assertEquals(80, $whish['income']);

        $this->assertSame(6, $month->topProducts()->first()['quantity']);
        $this->assertEquals(70, $month->expensesByParty()->firstWhere('party', 'Landlord')['pending']);

        // Customers owe 40 (Nov) at end of November; you owe 70.
        $this->assertEquals(40, $month->outstanding()['customers_owe']);
        $this->assertEquals(70, $month->outstanding()['you_owe']);
    }

    public function test_cost_is_kept_from_the_moment_of_sale(): void
    {
        $this->bracelet->update(['initial_price' => 25]); // price change after the sales

        $this->assertEquals(60, FinancialReport::monthly('2026-11-01')->summary()['cost_of_goods']);
    }

    public function test_page_renders_monthly_and_daily(): void
    {
        $this->get('/admin/reports?period=monthly&month=2026-11')
            ->assertOk()
            ->assertSee('November 2026')
            ->assertSee('Positive · Profit')
            ->assertSee('Day by day');

        $this->get('/admin/reports?period=daily&day=2026-11-30')
            ->assertOk()
            ->assertSee('Monday, 30 November 2026')
            ->assertSee('Bills of the day')
            ->assertSee('Landlord');

        $this->get('/admin/reports?period=monthly&month=2026-10')->assertOk()->assertSee('Negative · Loss');
        $this->get('/admin/reports?period=monthly&month=garbage')->assertOk(); // bad input falls back to this month
    }

    public function test_navigation_between_periods(): void
    {
        Livewire::withQueryParams(['period' => 'monthly', 'month' => '2026-11'])
            ->test(FinancialReports::class)
            ->call('previousPeriod')->assertSet('month', '2026-10')
            ->call('nextPeriod')->call('nextPeriod')->assertSet('month', '2026-12')
            ->call('openDay', '2026-11-03')->assertSet('period', 'daily')->assertSet('day', '2026-11-03')
            ->call('nextPeriod')->assertSet('day', '2026-11-04');
    }

    public function test_excel_export(): void
    {
        $export = new FinancialReportExcel(FinancialReport::monthly('2026-11-01'));
        $path = tempnam(sys_get_temp_dir(), 'rep').'.xlsx';
        (new Xlsx($export->build()))->save($path);

        $book = IOFactory::load($path);
        $this->assertSame(['Summary', 'Daily breakdown', 'Bills', 'Products sold', 'Expenses'], $book->getSheetNames());

        $summary = $book->getSheetByName('Summary');
        $this->assertSame('Positive · Profit', $summary->getCell('B6')->getValue());
        $this->assertEquals(110, $summary->getCell('C6')->getValue());

        $daily = $book->getSheetByName('Daily breakdown');
        $this->assertEquals(230, $daily->getCell('D32')->getCalculatedValue()); // SUM row after 30 days
        $billNumbers = collect($book->getSheetByName('Bills')->rangeToArray('B2:B20'))->flatten()->filter()->values();
        $this->assertCount(5, $billNumbers); // trashed bill excluded
        @unlink($path);

        Livewire::withQueryParams(['period' => 'monthly', 'month' => '2026-11'])
            ->test(FinancialReports::class)
            ->callAction('exportExcel')
            ->assertFileDownloaded('financial-report-2026-11.xlsx');
    }

    public function test_pdf_export(): void
    {
        $pdf = (new FinancialReportPdf(FinancialReport::daily('2026-11-30')))->output();
        $this->assertStringStartsWith('%PDF', $pdf);

        Livewire::withQueryParams(['period' => 'daily', 'day' => '2026-11-30'])
            ->test(FinancialReports::class)
            ->callAction('exportPdf')
            ->assertFileDownloaded('financial-report-2026-11-30.pdf');
    }

    public function test_permissions(): void
    {
        Role::findOrCreate('Accountant', 'admin')->syncPermissions(['reports.view']);
        $viewer = tap(Admin::create(['name' => 'A', 'email' => 'a@example.com', 'password' => 'password123']))->assignRole('Accountant');
        $this->actingAs($viewer, 'admin');

        $this->get('/admin/reports')->assertOk();
        Livewire::test(FinancialReports::class)->assertActionHidden('exportExcel')->assertActionHidden('exportPdf');
    }

    public function test_admin_without_report_permission_is_blocked(): void
    {
        auth('admin')->logout();
        $this->flushSession();

        $this->actingAs(tap(Admin::create(['name' => 'N', 'email' => 'n@example.com', 'password' => 'password123']))->assignRole('Viewer'), 'admin');
        $this->get('/admin/reports')->assertForbidden();
    }
}
