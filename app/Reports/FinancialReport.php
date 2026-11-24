<?php

namespace App\Reports;

use App\Enums\BillStatus;
use App\Enums\BillType;
use App\Enums\PaymentMethod;
use App\Models\Bill;
use App\Models\BillItem;
use Carbon\CarbonImmutable;
use Carbon\CarbonPeriod;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Income / expense report for one day or one month.
 *
 * Bills are counted by their bill date. Trashed bills are ignored.
 * "Income" = sale (IN) bills, "Expenses" = expense (OUT) bills, both after
 * bill-level discounts. Paid and pending amounts are shown separately.
 *
 * The admin page, the Excel export and the PDF export all read from this
 * class, so they always show the same numbers.
 */
class FinancialReport
{
    public const DAILY = 'daily';

    public const MONTHLY = 'monthly';

    public readonly CarbonImmutable $start;

    public readonly CarbonImmutable $end;

    private array $cache = [];

    public function __construct(public readonly string $period, CarbonImmutable|string $date)
    {
        $date = $date instanceof CarbonImmutable ? $date : CarbonImmutable::parse($date);

        [$this->start, $this->end] = $period === self::DAILY
            ? [$date->startOfDay(), $date->endOfDay()]
            : [$date->startOfMonth(), $date->endOfMonth()];
    }

    public static function daily(CarbonImmutable|string $date): static
    {
        return new static(self::DAILY, $date);
    }

    public static function monthly(CarbonImmutable|string $date): static
    {
        return new static(self::MONTHLY, $date);
    }

    public function isDaily(): bool
    {
        return $this->period === self::DAILY;
    }

    public function title(): string
    {
        return $this->isDaily() ? 'Daily financial report' : 'Monthly financial report';
    }

    public function label(): string
    {
        return $this->isDaily() ? $this->start->format('l, d F Y') : $this->start->format('F Y');
    }

    /** Short label for file names, e.g. 2026-11 or 2026-11-23. */
    public function slug(): string
    {
        return $this->isDaily() ? $this->start->format('Y-m-d') : $this->start->format('Y-m');
    }

    public function previous(): static
    {
        return new static($this->period, $this->isDaily() ? $this->start->subDay() : $this->start->subMonthNoOverflow());
    }

    public function next(): static
    {
        return new static($this->period, $this->isDaily() ? $this->start->addDay() : $this->start->addMonthNoOverflow());
    }

    /** Bills dated inside the period (not trashed). */
    public function billsQuery(): Builder
    {
        return Bill::query()
            ->whereDate('bill_date', '>=', $this->start->toDateString())
            ->whereDate('bill_date', '<=', $this->end->toDateString());
    }

    // ------------------------------------------------------------------
    // Summary
    // ------------------------------------------------------------------

    /**
     * @return array{
     *     income: float, income_paid: float, income_pending: float, income_count: int,
     *     expenses: float, expenses_paid: float, expenses_pending: float, expenses_count: int,
     *     net: float, cash_flow: float, discounts: float, cost_of_goods: float,
     *     gross_profit: float, gross_margin: ?float, items_sold: int, bills_count: int
     * }
     */
    public function summary(): array
    {
        return $this->cache['summary'] ??= $this->calculateSummary();
    }

    private function calculateSummary(): array
    {
        $totals = $this->billsQuery()
            ->selectRaw('type, status, COUNT(*) as bills, COALESCE(SUM(total), 0) as amount, COALESCE(SUM(discount), 0) as discounts')
            ->groupBy('type', 'status')
            ->toBase()
            ->get();

        $sum = fn (BillType $type, ?BillStatus $status = null, string $column = 'amount') => (float) $totals
            ->filter(fn ($row) => $row->type === $type->value && ($status === null || $row->status === $status->value))
            ->sum($column);

        $count = fn (BillType $type) => (int) $totals->where('type', $type->value)->sum('bills');

        $sales = $this->saleItemsQuery()
            ->selectRaw('COALESCE(SUM(bill_items.quantity), 0) as qty, COALESCE(SUM(bill_items.quantity * COALESCE(bill_items.unit_cost, 0)), 0) as cost')
            ->toBase()
            ->first();

        $income = $sum(BillType::In);
        $expenses = $sum(BillType::Out);
        $costOfGoods = round((float) $sales->cost, 2);
        $grossProfit = $income - $costOfGoods;

        return [
            'income' => $income,
            'income_paid' => $sum(BillType::In, BillStatus::Paid),
            'income_pending' => $sum(BillType::In, BillStatus::Pending),
            'income_count' => $count(BillType::In),
            'expenses' => $expenses,
            'expenses_paid' => $sum(BillType::Out, BillStatus::Paid),
            'expenses_pending' => $sum(BillType::Out, BillStatus::Pending),
            'expenses_count' => $count(BillType::Out),
            'net' => round($income - $expenses, 2),
            'cash_flow' => round($sum(BillType::In, BillStatus::Paid) - $sum(BillType::Out, BillStatus::Paid), 2),
            'discounts' => $sum(BillType::In, null, 'discounts'),
            'cost_of_goods' => $costOfGoods,
            'gross_profit' => round($grossProfit, 2),
            'gross_margin' => $income > 0 ? round($grossProfit / $income * 100, 1) : null,
            'items_sold' => (int) $sales->qty,
            'bills_count' => $count(BillType::In) + $count(BillType::Out),
        ];
    }

    /** Positive / negative / break-even, for labels and colours. */
    public static function result(float $amount): string
    {
        return match (true) {
            $amount > 0 => 'profit',
            $amount < 0 => 'loss',
            default => 'even',
        };
    }

    public static function resultLabel(float $amount): string
    {
        return match (static::result($amount)) {
            'profit' => 'Positive · Profit',
            'loss' => 'Negative · Loss',
            default => 'Break-even',
        };
    }

    // ------------------------------------------------------------------
    // Comparison with the previous period
    // ------------------------------------------------------------------

    /** @return array<string, array{current: float, previous: float, change: ?float}> */
    public function comparison(): array
    {
        return $this->cache['comparison'] ??= (function () {
            $now = $this->summary();
            $before = $this->previous()->summary();

            return collect(['income' => 'Income', 'expenses' => 'Expenses', 'net' => 'Net result', 'gross_profit' => 'Profit on products'])
                ->mapWithKeys(fn ($label, $key) => [$key => [
                    'label' => $label,
                    'current' => $now[$key],
                    'previous' => $before[$key],
                    'change' => $before[$key] != 0
                        ? round(($now[$key] - $before[$key]) / abs($before[$key]) * 100, 1)
                        : null,
                ]])
                ->all();
        })();
    }

    // ------------------------------------------------------------------
    // Breakdowns
    // ------------------------------------------------------------------

    /**
     * Monthly: one row per day with a running balance.
     *
     * @return Collection<int, array{date: CarbonImmutable, income: float, expenses: float, net: float, running: float, bills: int}>
     */
    public function dailyRows(): Collection
    {
        return $this->cache['dailyRows'] ??= (function () {
            $byDay = $this->billsQuery()
                ->selectRaw('bill_date, type, COUNT(*) as bills, COALESCE(SUM(total), 0) as amount')
                ->groupBy('bill_date', 'type')
                ->toBase()
                ->get()
                ->groupBy(fn ($row) => substr((string) $row->bill_date, 0, 10));

            $running = 0.0;

            return collect(CarbonPeriod::create($this->start, $this->end->startOfDay()))
                ->map(function ($day) use ($byDay, &$running) {
                    $rows = $byDay->get($day->format('Y-m-d'), collect());
                    $income = (float) $rows->where('type', BillType::In->value)->sum('amount');
                    $expenses = (float) $rows->where('type', BillType::Out->value)->sum('amount');
                    $running += $income - $expenses;

                    return [
                        'date' => CarbonImmutable::instance($day),
                        'income' => $income,
                        'expenses' => $expenses,
                        'net' => round($income - $expenses, 2),
                        'running' => round($running, 2),
                        'bills' => (int) $rows->sum('bills'),
                    ];
                })
                ->values();
        })();
    }

    /** Every bill in the period, newest first. */
    public function bills(): Collection
    {
        return $this->cache['bills'] ??= $this->billsQuery()
            ->withCount('items')
            ->orderBy('bill_date')
            ->orderBy('id')
            ->get();
    }

    /**
     * Paid amounts per payment method.
     *
     * @return Collection<int, array{method: PaymentMethod, income: float, expenses: float, net: float}>
     */
    public function paymentMethods(): Collection
    {
        return $this->cache['paymentMethods'] ??= (function () {
            $rows = $this->billsQuery()->paid()
                ->selectRaw('payment_method, type, COALESCE(SUM(total), 0) as amount')
                ->groupBy('payment_method', 'type')
                ->toBase()
                ->get();

            return collect(PaymentMethod::cases())->map(function (PaymentMethod $method) use ($rows) {
                $income = (float) $rows->where('payment_method', $method->value)->where('type', BillType::In->value)->sum('amount');
                $expenses = (float) $rows->where('payment_method', $method->value)->where('type', BillType::Out->value)->sum('amount');

                return ['method' => $method, 'income' => $income, 'expenses' => $expenses, 'net' => round($income - $expenses, 2)];
            });
        })();
    }

    /**
     * Products sold in the period, best sellers first.
     * Revenue is per line, before bill-level discounts.
     *
     * @return Collection<int, array{name: string, sku: ?string, quantity: int, revenue: float, cost: float, profit: float}>
     */
    public function topProducts(?int $limit = 10): Collection
    {
        return $this->cache["topProducts.{$limit}"] ??= $this->saleItemsQuery()
            ->leftJoin('products', 'products.id', '=', 'bill_items.product_id')
            ->selectRaw('bill_items.product_id, MAX(products.name) as name, MAX(products.sku) as sku, MAX(bill_items.description) as description,
                SUM(bill_items.quantity) as quantity, SUM(bill_items.line_total) as revenue,
                SUM(bill_items.quantity * COALESCE(bill_items.unit_cost, 0)) as cost')
            ->groupBy('bill_items.product_id')
            ->orderByDesc('revenue')
            ->when($limit, fn ($q) => $q->limit($limit))
            ->toBase()
            ->get()
            ->map(fn ($row) => [
                'name' => $row->name ?? $row->description,
                'sku' => $row->sku,
                'quantity' => (int) $row->quantity,
                'revenue' => round((float) $row->revenue, 2),
                'cost' => round((float) $row->cost, 2),
                'profit' => round((float) $row->revenue - (float) $row->cost, 2),
            ]);
    }

    /**
     * Expenses grouped by who was paid.
     *
     * @return Collection<int, array{party: string, bills: int, total: float, paid: float, pending: float}>
     */
    public function expensesByParty(): Collection
    {
        return $this->cache['expensesByParty'] ??= $this->billsQuery()->outgoing()
            ->selectRaw('party_name, status, COUNT(*) as bills, COALESCE(SUM(total), 0) as amount')
            ->groupBy('party_name', 'status')
            ->toBase()
            ->get()
            ->groupBy('party_name')
            ->map(fn (Collection $rows, string $party) => [
                'party' => $party,
                'bills' => (int) $rows->sum('bills'),
                'total' => (float) $rows->sum('amount'),
                'paid' => (float) $rows->where('status', BillStatus::Paid->value)->sum('amount'),
                'pending' => (float) $rows->where('status', BillStatus::Pending->value)->sum('amount'),
            ])
            ->sortByDesc('total')
            ->values();
    }

    /**
     * Everything still unpaid on the last day of the period (from any date).
     *
     * @return array{customers_owe: float, customers_owe_count: int, you_owe: float, you_owe_count: int}
     */
    public function outstanding(): array
    {
        return $this->cache['outstanding'] ??= (function () {
            $pending = fn (BillType $type) => Bill::query()->pending()
                ->where('type', $type)
                ->whereDate('bill_date', '<=', $this->end->toDateString());

            return [
                'customers_owe' => (float) $pending(BillType::In)->sum('total'),
                'customers_owe_count' => $pending(BillType::In)->count(),
                'you_owe' => (float) $pending(BillType::Out)->sum('total'),
                'you_owe_count' => $pending(BillType::Out)->count(),
            ];
        })();
    }

    /** Sale lines (with a product) on non-trashed IN bills in the period. */
    private function saleItemsQuery(): Builder
    {
        return BillItem::query()
            ->join('bills', 'bills.id', '=', 'bill_items.bill_id')
            ->whereNull('bills.deleted_at')
            ->where('bills.type', BillType::In->value)
            ->whereNotNull('bill_items.product_id')
            ->whereDate('bills.bill_date', '>=', $this->start->toDateString())
            ->whereDate('bills.bill_date', '<=', $this->end->toDateString());
    }
}
