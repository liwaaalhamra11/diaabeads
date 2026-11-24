@php
    use App\Reports\FinancialReport;
    use App\Support\Money;

    /** @var FinancialReport $report */
    $s = $report->summary();
    $out = $report->outstanding();
    $m = fn ($v) => Money::format($v);
    $signed = fn ($v) => ($v > 0 ? '+' : ($v < 0 ? '−' : '')) . Money::format(abs($v));
    $tone = fn ($v) => $v < 0 ? 'neg' : ($v > 0 ? 'pos' : '');
@endphp
<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">
<title>{{ $report->title() }} — {{ $report->label() }}</title>
<style>
    @page { margin: 28px 32px 40px; }
    * { font-family: 'DejaVu Sans', sans-serif; }
    body { color: #0a0a0a; font-size: 9.5px; line-height: 1.45; }
    h1 { font-size: 20px; margin: 0; letter-spacing: .5px; }
    h2 { font-size: 10px; text-transform: uppercase; letter-spacing: 1.5px; color: #9b7346;
         border-bottom: 1px solid #9b7346; padding-bottom: 3px; margin: 18px 0 6px; }
    .muted { color: #6b6b6b; }
    .bronze { color: #9b7346; }
    .neg { color: #b91c1c; }
    .pos { color: #0a0a0a; }
    .right { text-align: right; }
    .center { text-align: center; }
    .bold { font-weight: bold; }
    table { width: 100%; border-collapse: collapse; }
    .grid th { background: #0a0a0a; color: #fff; font-weight: bold; text-align: left; padding: 5px 6px;
               border-bottom: 2px solid #9b7346; font-size: 8.5px; text-transform: uppercase; letter-spacing: .5px; }
    .grid td { padding: 4px 6px; border-bottom: 1px solid #ececec; }
    .grid th.right, .grid td.right { text-align: right; }
    .grid th.center, .grid td.center { text-align: center; }
    .grid tr.total td { font-weight: bold; background: #f5f5f5; border-top: 1px solid #0a0a0a; }
    .grid tr.quiet td { color: #b5b5b5; }
    .header td { vertical-align: top; }
    .banner { margin-top: 14px; padding: 10px 14px; color: #fff; }
    .banner.profit, .banner.even { background: #0a0a0a; }
    .banner.loss { background: #b91c1c; }
    .banner .big { font-size: 20px; font-weight: bold; }
    .cards td { width: 25%; padding: 8px 10px; border: 1px solid #e5e5e5; vertical-align: top; }
    .cards .label { font-size: 8px; text-transform: uppercase; letter-spacing: 1px; color: #6b6b6b; }
    .cards .value { font-size: 14px; font-weight: bold; margin-top: 2px; }
    .cards .sub { font-size: 8px; color: #6b6b6b; margin-top: 2px; }
    .two td.col { width: 50%; vertical-align: top; }
    .footer { position: fixed; bottom: -24px; left: 0; right: 0; font-size: 8px; color: #a3a3a3;
              border-top: 1px solid #e5e5e5; padding-top: 4px; }
    .page-break { page-break-before: always; }
</style>
</head>
<body>

<div class="footer">
    <table><tr>
        <td>{{ config('app.name') }} · {{ $report->title() }} · {{ $report->label() }}</td>
        <td class="right">Generated {{ now()->format('d M Y, H:i') }} · Amounts in {{ Money::currency() }}</td>
    </tr></table>
</div>

<table class="header">
    <tr>
        @if (file_exists(public_path('images/logo-small.png')))
            <td style="width:70px;padding-right:12px">
                <img src="{{ public_path('images/logo-small.png') }}" style="width:64px;height:64px" alt="">
            </td>
        @endif
        <td>
            <div class="bronze bold" style="font-size:9px;letter-spacing:2px;text-transform:uppercase">{{ config('app.name') }}</div>
            <h1>{{ $report->title() }}</h1>
            <div class="muted" style="font-size:11px">{{ $report->label() }}</div>
        </td>
        <td class="right muted" style="font-size:8.5px">
            {{ $s['bills_count'] }} bill(s) in this period<br>
            Trashed bills are not included
        </td>
    </tr>
</table>

{{-- Result banner --}}
<div class="banner {{ FinancialReport::result($s['net']) }}">
    <table><tr>
        <td>
            <div style="font-size:8.5px;letter-spacing:1.5px;text-transform:uppercase;opacity:.8">Net result (income − expenses)</div>
            <div class="big">{{ $signed($s['net']) }}</div>
        </td>
        <td class="right" style="font-size:13px;font-weight:bold">{{ FinancialReport::resultLabel($s['net']) }}</td>
    </tr></table>
</div>

<table class="cards" style="margin-top:10px">
    <tr>
        <td>
            <div class="label">Income</div>
            <div class="value">{{ $m($s['income']) }}</div>
            <div class="sub">{{ $s['income_count'] }} sale(s)</div>
        </td>
        <td>
            <div class="label">Expenses</div>
            <div class="value bronze">{{ $m($s['expenses']) }}</div>
            <div class="sub">{{ $s['expenses_count'] }} expense(s)</div>
        </td>
        <td>
            <div class="label">Cash flow</div>
            <div class="value {{ $tone($s['cash_flow']) }}">{{ $signed($s['cash_flow']) }}</div>
            <div class="sub">Received − paid out</div>
        </td>
        <td>
            <div class="label">Profit on products</div>
            <div class="value {{ $tone($s['gross_profit']) }}">{{ $signed($s['gross_profit']) }}</div>
            <div class="sub">{{ $s['gross_margin'] !== null ? $s['gross_margin'].'% margin' : 'No sales' }}</div>
        </td>
    </tr>
</table>

<table class="two" style="margin-top:4px"><tr>
    <td class="col" style="padding-right:10px">
        <h2>Income</h2>
        <table class="grid">
            <tr><td>Received (paid)</td><td class="right">{{ $m($s['income_paid']) }}</td></tr>
            <tr><td>Pending — customers will pay</td><td class="right bronze">{{ $m($s['income_pending']) }}</td></tr>
            <tr><td>Discounts given</td><td class="right muted">{{ $m($s['discounts']) }}</td></tr>
            <tr><td>Cost of products sold ({{ $s['items_sold'] }} pcs)</td><td class="right muted">{{ $m($s['cost_of_goods']) }}</td></tr>
            <tr class="total"><td>Total income</td><td class="right">{{ $m($s['income']) }}</td></tr>
        </table>
    </td>
    <td class="col" style="padding-left:10px">
        <h2>Expenses</h2>
        <table class="grid">
            <tr><td>Paid</td><td class="right">{{ $m($s['expenses_paid']) }}</td></tr>
            <tr><td>Pending — you will pay</td><td class="right bronze">{{ $m($s['expenses_pending']) }}</td></tr>
            <tr><td>&nbsp;</td><td></td></tr>
            <tr><td>&nbsp;</td><td></td></tr>
            <tr class="total"><td>Total expenses</td><td class="right">{{ $m($s['expenses']) }}</td></tr>
        </table>
    </td>
</tr></table>

<table class="two"><tr>
    <td class="col" style="padding-right:10px">
        <h2>Compared with {{ $report->previous()->label() }}</h2>
        <table class="grid">
            <tr><th></th><th class="right">This period</th><th class="right">Previous</th><th class="right">Change</th></tr>
            @foreach ($report->comparison() as $row)
                <tr>
                    <td>{{ $row['label'] }}</td>
                    <td class="right">{{ $m($row['current']) }}</td>
                    <td class="right muted">{{ $row['previous'] < 0 ? '−'.$m(abs($row['previous'])) : $m($row['previous']) }}</td>
                    <td class="right {{ $row['change'] !== null && $row['change'] < 0 ? 'neg' : '' }}">
                        {{ $row['change'] === null ? '—' : ($row['change'] > 0 ? '+' : '').$row['change'].'%' }}
                    </td>
                </tr>
            @endforeach
        </table>
    </td>
    <td class="col" style="padding-left:10px">
        <h2>Paid by method</h2>
        <table class="grid">
            <tr><th>Method</th><th class="right">Received</th><th class="right">Paid out</th><th class="right">Net</th></tr>
            @foreach ($report->paymentMethods() as $row)
                <tr>
                    <td>{{ $row['method']->getLabel() }}</td>
                    <td class="right">{{ $m($row['income']) }}</td>
                    <td class="right">{{ $m($row['expenses']) }}</td>
                    <td class="right {{ $tone($row['net']) }}">{{ $signed($row['net']) }}</td>
                </tr>
            @endforeach
        </table>
        <h2>Still unpaid on {{ $report->end->format('d M Y') }}</h2>
        <table class="grid">
            <tr><td>Customers owe you ({{ $out['customers_owe_count'] }})</td><td class="right bronze">{{ $m($out['customers_owe']) }}</td></tr>
            <tr><td>You owe ({{ $out['you_owe_count'] }})</td><td class="right bronze">{{ $m($out['you_owe']) }}</td></tr>
        </table>
    </td>
</tr></table>

{{-- Breakdown --}}
@if ($report->isDaily())
    <h2>Bills of the day</h2>
    <table class="grid">
        <tr><th>Bill</th><th>Type</th><th>Customer / Paid to</th><th>Status</th><th class="right">In</th><th class="right">Out</th></tr>
        @forelse ($report->bills() as $bill)
            <tr>
                <td style="font-family:'DejaVu Sans Mono',monospace">{{ $bill->number }}</td>
                <td>{{ $bill->type->getLabel() }}</td>
                <td>{{ $bill->party_name }}@if ($bill->description)<br><span class="muted">{{ \Illuminate\Support\Str::limit($bill->description, 70) }}</span>@endif</td>
                <td class="{{ $bill->status->value === 'pending' ? 'bronze' : '' }}">{{ $bill->status->getLabel() }}{{ $bill->payment_method ? ' · '.$bill->payment_method->getLabel() : '' }}</td>
                <td class="right">{{ $bill->type->value === 'in' ? $m($bill->total) : '' }}</td>
                <td class="right bronze">{{ $bill->type->value === 'out' ? $m($bill->total) : '' }}</td>
            </tr>
        @empty
            <tr><td colspan="6" class="center muted" style="padding:14px">No bills on this day.</td></tr>
        @endforelse
        <tr class="total"><td colspan="4">Total</td><td class="right">{{ $m($s['income']) }}</td><td class="right">{{ $m($s['expenses']) }}</td></tr>
    </table>
@else
    <div class="page-break"></div>
    <h2>Day by day</h2>
    <table class="grid">
        <tr><th>Date</th><th class="center">Bills</th><th class="right">Income</th><th class="right">Expenses</th><th class="right">Net</th><th class="right">Running balance</th></tr>
        @foreach ($report->dailyRows() as $row)
            <tr class="{{ $row['bills'] ? '' : 'quiet' }}">
                <td>{{ $row['date']->format('D d M') }}</td>
                <td class="center">{{ $row['bills'] ?: '' }}</td>
                <td class="right">{{ $row['income'] ? $m($row['income']) : '' }}</td>
                <td class="right">{{ $row['expenses'] ? $m($row['expenses']) : '' }}</td>
                <td class="right {{ $tone($row['net']) }}">{{ $row['bills'] ? $signed($row['net']) : '' }}</td>
                <td class="right {{ $tone($row['running']) }}">{{ $signed($row['running']) }}</td>
            </tr>
        @endforeach
        <tr class="total">
            <td>Total</td><td class="center">{{ $s['bills_count'] }}</td>
            <td class="right">{{ $m($s['income']) }}</td><td class="right">{{ $m($s['expenses']) }}</td>
            <td class="right {{ $tone($s['net']) }}">{{ $signed($s['net']) }}</td><td></td>
        </tr>
    </table>
@endif

<table class="two"><tr>
    <td class="col" style="padding-right:10px">
        <h2>Top products</h2>
        <table class="grid">
            <tr><th>Product</th><th class="center">Pcs</th><th class="right">Revenue</th><th class="right">Profit</th></tr>
            @forelse ($report->topProducts(10) as $row)
                <tr>
                    <td>{{ $row['name'] }}@if ($row['sku'])<br><span class="muted" style="font-size:8px">{{ $row['sku'] }}</span>@endif</td>
                    <td class="center">{{ $row['quantity'] }}</td>
                    <td class="right">{{ $m($row['revenue']) }}</td>
                    <td class="right {{ $tone($row['profit']) }}">{{ $m($row['profit']) }}</td>
                </tr>
            @empty
                <tr><td colspan="4" class="center muted" style="padding:10px">No products sold.</td></tr>
            @endforelse
        </table>
    </td>
    <td class="col" style="padding-left:10px">
        <h2>Expenses by who was paid</h2>
        <table class="grid">
            <tr><th>Paid to</th><th class="center">Bills</th><th class="right">Total</th><th class="right">Pending</th></tr>
            @forelse ($report->expensesByParty() as $row)
                <tr>
                    <td>{{ $row['party'] }}</td>
                    <td class="center">{{ $row['bills'] }}</td>
                    <td class="right">{{ $m($row['total']) }}</td>
                    <td class="right bronze">{{ $row['pending'] ? $m($row['pending']) : '' }}</td>
                </tr>
            @empty
                <tr><td colspan="4" class="center muted" style="padding:10px">No expenses.</td></tr>
            @endforelse
        </table>
    </td>
</tr></table>

</body>
</html>
