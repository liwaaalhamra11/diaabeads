@php
    use App\Filament\Resources\Bills\BillResource;
    use App\Reports\FinancialReport;
    use App\Support\Money;

    $report = $this->report;
    $s = $report->summary();
    $out = $report->outstanding();
    $m = fn ($v) => Money::format($v);
    $signed = fn ($v) => ($v > 0 ? '+' : ($v < 0 ? '−' : '')) . Money::format(abs($v));
    $tone = fn ($v) => $v < 0 ? 'fr-neg' : '';
    $canOpenBills = BillResource::canViewAny();
@endphp

<x-filament-panels::page>
    <style>
        .fr { display: grid; gap: 1.5rem; }
        .fr-toolbar { display: flex; flex-wrap: wrap; gap: .75rem; align-items: center; justify-content: space-between; }
        .fr-nav { display: flex; gap: .5rem; align-items: center; }
        .fr-label { font-size: 1.125rem; font-weight: 600; }
        .fr-muted { color: #737373; }
        .fr-bronze { color: #8f6d46; }
        .fr-neg { color: #b91c1c; }
        .fr-banner { border-radius: .75rem; padding: 1.25rem 1.5rem; color: #fff; display: flex; flex-wrap: wrap; gap: 1rem; align-items: center; justify-content: space-between; background: #0a0a0a; border-left: 6px solid #9b7346; }
        .fr-banner.loss { background: #7f1d1d; border-left-color: #fca5a5; }
        .fr-banner .big { font-size: 2rem; font-weight: 700; line-height: 1.1; }
        .fr-banner .tag { font-size: .75rem; text-transform: uppercase; letter-spacing: .12em; opacity: .75; }
        .fr-pill { display: inline-block; padding: .3rem .8rem; border-radius: 999px; font-weight: 600; font-size: .875rem; background: rgba(255,255,255,.14); }
        .fr-cards { display: grid; gap: 1rem; grid-template-columns: repeat(auto-fit, minmax(14rem, 1fr)); }
        .fr-card { background: #fff; border: 1px solid #e5e5e5; border-radius: .75rem; padding: 1rem 1.25rem; }
        .fr-card .k { font-size: .75rem; text-transform: uppercase; letter-spacing: .08em; color: #737373; }
        .fr-card .v { font-size: 1.5rem; font-weight: 700; margin-top: .25rem; }
        .fr-card .rows { margin-top: .6rem; font-size: .8125rem; display: grid; gap: .15rem; }
        .fr-card .rows div { display: flex; justify-content: space-between; gap: .5rem; }
        .fr-cols { display: grid; gap: 1.5rem; grid-template-columns: repeat(auto-fit, minmax(22rem, 1fr)); }
        .fr-table { width: 100%; border-collapse: collapse; font-size: .875rem; }
        .fr-table th { text-align: left; font-size: .75rem; text-transform: uppercase; letter-spacing: .05em; color: #737373; font-weight: 600; padding: .5rem .5rem; border-bottom: 1px solid #e5e5e5; }
        .fr-table td { padding: .55rem .5rem; border-bottom: 1px solid #f0f0f0; vertical-align: middle; }
        .fr-table .r { text-align: right; white-space: nowrap; }
        .fr-table .c { text-align: center; }
        .fr-table tr.total td { font-weight: 700; border-top: 2px solid #0a0a0a; border-bottom: 0; }
        .fr-table tr.quiet td { color: #c4c4c4; }
        .fr-table tr.clickable { cursor: pointer; }
        .fr-table tr.clickable:hover td { background: #fafafa; }
        .fr-bars { display: grid; gap: 2px; min-width: 6rem; }
        .fr-bar { height: 5px; border-radius: 3px; }
        .fr-mono { font-family: ui-monospace, SFMono-Regular, Menlo, monospace; }
        .fr-empty { text-align: center; color: #a3a3a3; padding: 1.5rem .5rem; }
        .fr-scroll { overflow-x: auto; }
    </style>

    <div class="fr">
        {{-- Period picker --}}
        <div class="fr-toolbar">
            <x-filament::tabs>
                <x-filament::tabs.item :active="$period === 'daily'" wire:click="setPeriod('daily')" icon="heroicon-o-calendar">
                    Daily
                </x-filament::tabs.item>
                <x-filament::tabs.item :active="$period === 'monthly'" wire:click="setPeriod('monthly')" icon="heroicon-o-calendar-days">
                    Monthly
                </x-filament::tabs.item>
            </x-filament::tabs>

            <div class="fr-nav">
                <x-filament::icon-button icon="heroicon-m-chevron-left" color="gray" wire:click="previousPeriod" label="Previous period" />

                <x-filament::input.wrapper>
                    @if ($period === 'daily')
                        <x-filament::input type="date" wire:model.live="day" max="{{ today()->addYear()->format('Y-m-d') }}" />
                    @else
                        <x-filament::input type="month" wire:model.live="month" />
                    @endif
                </x-filament::input.wrapper>

                <x-filament::icon-button icon="heroicon-m-chevron-right" color="gray" wire:click="nextPeriod" label="Next period" />

                @unless ($this->isCurrentPeriod())
                    <x-filament::button color="gray" size="sm" wire:click="currentPeriod">
                        {{ $period === 'daily' ? 'Today' : 'This month' }}
                    </x-filament::button>
                @endunless
            </div>
        </div>

        <div>
            <div class="fr-label">{{ $report->label() }}</div>
            <div class="fr-muted" style="font-size:.8125rem">
                {{ $s['bills_count'] }} bill(s) · counted by bill date · trashed bills not included · amounts in {{ Money::currency() }}
            </div>
        </div>

        {{-- Result --}}
        <div class="fr-banner {{ FinancialReport::result($s['net']) }}">
            <div>
                <div class="tag">Net result · income − expenses</div>
                <div class="big">{{ $signed($s['net']) }}</div>
            </div>
            <span class="fr-pill">
                @if ($s['net'] > 0) ▲ @elseif ($s['net'] < 0) ▼ @else ● @endif
                {{ FinancialReport::resultLabel($s['net']) }}
            </span>
        </div>

        {{-- Cards --}}
        <div class="fr-cards">
            <div class="fr-card">
                <div class="k">Income</div>
                <div class="v">{{ $m($s['income']) }}</div>
                <div class="rows">
                    <div><span class="fr-muted">Received</span><span>{{ $m($s['income_paid']) }}</span></div>
                    <div><span class="fr-muted">Pending</span><span class="fr-bronze">{{ $m($s['income_pending']) }}</span></div>
                    <div><span class="fr-muted">Sales</span><span>{{ $s['income_count'] }}</span></div>
                </div>
            </div>
            <div class="fr-card">
                <div class="k">Expenses</div>
                <div class="v fr-bronze">{{ $m($s['expenses']) }}</div>
                <div class="rows">
                    <div><span class="fr-muted">Paid</span><span>{{ $m($s['expenses_paid']) }}</span></div>
                    <div><span class="fr-muted">Pending</span><span class="fr-bronze">{{ $m($s['expenses_pending']) }}</span></div>
                    <div><span class="fr-muted">Bills</span><span>{{ $s['expenses_count'] }}</span></div>
                </div>
            </div>
            <div class="fr-card">
                <div class="k">Cash flow</div>
                <div class="v {{ $tone($s['cash_flow']) }}">{{ $signed($s['cash_flow']) }}</div>
                <div class="rows">
                    <div><span class="fr-muted">Money actually received − paid out</span></div>
                    <div><span class="fr-muted">Result</span><span>{{ FinancialReport::resultLabel($s['cash_flow']) }}</span></div>
                </div>
            </div>
            <div class="fr-card">
                <div class="k">Profit on products</div>
                <div class="v {{ $tone($s['gross_profit']) }}">{{ $signed($s['gross_profit']) }}</div>
                <div class="rows">
                    <div><span class="fr-muted">Cost of products sold</span><span>{{ $m($s['cost_of_goods']) }}</span></div>
                    <div><span class="fr-muted">Pieces sold</span><span>{{ $s['items_sold'] }}</span></div>
                    <div><span class="fr-muted">Margin</span><span>{{ $s['gross_margin'] !== null ? $s['gross_margin'].'%' : '—' }}</span></div>
                </div>
            </div>
        </div>

        <div class="fr-cols">
            <x-filament::section icon="heroicon-o-arrows-right-left" heading="Compared with {{ $report->previous()->label() }}">
                <table class="fr-table">
                    <tr><th></th><th class="r">This period</th><th class="r">Previous</th><th class="r">Change</th></tr>
                    @foreach ($report->comparison() as $row)
                        <tr>
                            <td>{{ $row['label'] }}</td>
                            <td class="r {{ $tone($row['current']) }}">{{ $m($row['current']) }}</td>
                            <td class="r fr-muted">{{ $m($row['previous']) }}</td>
                            <td class="r {{ $row['change'] !== null && $row['change'] < 0 ? 'fr-neg' : '' }}">
                                @if ($row['change'] === null) <span class="fr-muted">—</span>
                                @else {{ $row['change'] > 0 ? '▲ +' : '▼ ' }}{{ $row['change'] }}%
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </table>
            </x-filament::section>

            <x-filament::section icon="heroicon-o-banknotes" heading="Paid by method">
                <table class="fr-table">
                    <tr><th>Method</th><th class="r">Received</th><th class="r">Paid out</th><th class="r">Net</th></tr>
                    @foreach ($report->paymentMethods() as $row)
                        <tr>
                            <td>{{ $row['method']->getLabel() }}</td>
                            <td class="r">{{ $m($row['income']) }}</td>
                            <td class="r">{{ $m($row['expenses']) }}</td>
                            <td class="r {{ $tone($row['net']) }}">{{ $signed($row['net']) }}</td>
                        </tr>
                    @endforeach
                </table>
                <div style="margin-top:1rem;font-size:.8125rem" class="fr-muted">Still unpaid on {{ $report->end->format('d M Y') }} (any date)</div>
                <table class="fr-table">
                    <tr><td>Customers owe you <span class="fr-muted">({{ $out['customers_owe_count'] }})</span></td><td class="r fr-bronze">{{ $m($out['customers_owe']) }}</td></tr>
                    <tr><td>You owe <span class="fr-muted">({{ $out['you_owe_count'] }})</span></td><td class="r fr-bronze">{{ $m($out['you_owe']) }}</td></tr>
                </table>
            </x-filament::section>
        </div>

        {{-- Breakdown --}}
        @if ($report->isDaily())
            <x-filament::section icon="heroicon-o-document-text" heading="Bills of the day">
                <div class="fr-scroll">
                    <table class="fr-table">
                        <tr><th>Bill</th><th>Type</th><th>Customer / Paid to</th><th>Status</th><th class="r">In</th><th class="r">Out</th></tr>
                        @forelse ($report->bills() as $bill)
                            <tr>
                                <td class="fr-mono">
                                    @if ($canOpenBills)
                                        <x-filament::link :href="BillResource::getUrl('edit', ['record' => $bill])">{{ $bill->number }}</x-filament::link>
                                    @else
                                        {{ $bill->number }}
                                    @endif
                                </td>
                                <td><x-filament::badge :color="$bill->type->getColor()" :icon="$bill->type->getIcon()">{{ $bill->type->getLabel() }}</x-filament::badge></td>
                                <td>{{ $bill->party_name }}@if ($bill->description)<div class="fr-muted" style="font-size:.75rem">{{ \Illuminate\Support\Str::limit($bill->description, 60) }}</div>@endif</td>
                                <td><x-filament::badge :color="$bill->status->getColor()">{{ $bill->status->getLabel() }}{{ $bill->payment_method ? ' · '.$bill->payment_method->getLabel() : '' }}</x-filament::badge></td>
                                <td class="r">{{ $bill->type->value === 'in' ? $m($bill->total) : '' }}</td>
                                <td class="r fr-bronze">{{ $bill->type->value === 'out' ? $m($bill->total) : '' }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="fr-empty">No bills on this day.</td></tr>
                        @endforelse
                        <tr class="total"><td colspan="4">Total</td><td class="r">{{ $m($s['income']) }}</td><td class="r">{{ $m($s['expenses']) }}</td></tr>
                    </table>
                </div>
            </x-filament::section>
        @else
            @php
                $rows = $report->dailyRows();
                $max = max(1, $rows->max('income'), $rows->max('expenses'));
            @endphp
            <x-filament::section icon="heroicon-o-calendar-days" heading="Day by day" description="Click a day to open its daily report.">
                <div class="fr-scroll">
                    <table class="fr-table">
                        <tr><th>Date</th><th class="c">Bills</th><th></th><th class="r">Income</th><th class="r">Expenses</th><th class="r">Net</th><th class="r">Running balance</th></tr>
                        @foreach ($rows as $row)
                            <tr class="{{ $row['bills'] ? 'clickable' : 'quiet' }}" @if ($row['bills']) wire:click="openDay('{{ $row['date']->format('Y-m-d') }}')" title="Open {{ $row['date']->format('d M') }}" @endif>
                                <td style="white-space:nowrap">{{ $row['date']->format('D d M') }}</td>
                                <td class="c">{{ $row['bills'] ?: '' }}</td>
                                <td>
                                    @if ($row['bills'])
                                        <div class="fr-bars">
                                            <div class="fr-bar" style="width:{{ round($row['income'] / $max * 100) }}%;background:#0a0a0a"></div>
                                            <div class="fr-bar" style="width:{{ round($row['expenses'] / $max * 100) }}%;background:#9b7346"></div>
                                        </div>
                                    @endif
                                </td>
                                <td class="r">{{ $row['income'] ? $m($row['income']) : '' }}</td>
                                <td class="r fr-bronze">{{ $row['expenses'] ? $m($row['expenses']) : '' }}</td>
                                <td class="r {{ $tone($row['net']) }}">{{ $row['bills'] ? $signed($row['net']) : '' }}</td>
                                <td class="r {{ $tone($row['running']) }}">{{ $signed($row['running']) }}</td>
                            </tr>
                        @endforeach
                        <tr class="total">
                            <td>Total</td><td class="c">{{ $s['bills_count'] }}</td><td></td>
                            <td class="r">{{ $m($s['income']) }}</td><td class="r">{{ $m($s['expenses']) }}</td>
                            <td class="r {{ $tone($s['net']) }}">{{ $signed($s['net']) }}</td><td></td>
                        </tr>
                    </table>
                </div>
                <div class="fr-muted" style="font-size:.75rem;margin-top:.5rem">
                    <span style="display:inline-block;width:.75rem;height:.35rem;background:#0a0a0a;border-radius:2px"></span> Income
                    &nbsp; <span style="display:inline-block;width:.75rem;height:.35rem;background:#9b7346;border-radius:2px"></span> Expenses
                </div>
            </x-filament::section>
        @endif

        <div class="fr-cols">
            <x-filament::section icon="heroicon-o-shopping-bag" heading="Top products" description="Before whole-bill discounts.">
                <table class="fr-table">
                    <tr><th>Product</th><th class="c">Pcs</th><th class="r">Revenue</th><th class="r">Profit</th></tr>
                    @forelse ($report->topProducts(10) as $row)
                        <tr>
                            <td>{{ $row['name'] }}@if ($row['sku'])<div class="fr-muted fr-mono" style="font-size:.7rem">{{ $row['sku'] }}</div>@endif</td>
                            <td class="c">{{ $row['quantity'] }}</td>
                            <td class="r">{{ $m($row['revenue']) }}</td>
                            <td class="r {{ $tone($row['profit']) }}">{{ $m($row['profit']) }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="fr-empty">No products sold.</td></tr>
                    @endforelse
                </table>
            </x-filament::section>

            <x-filament::section icon="heroicon-o-arrow-up-right" heading="Expenses by who was paid">
                <table class="fr-table">
                    <tr><th>Paid to</th><th class="c">Bills</th><th class="r">Total</th><th class="r">Pending</th></tr>
                    @forelse ($report->expensesByParty() as $row)
                        <tr>
                            <td>{{ $row['party'] }}</td>
                            <td class="c">{{ $row['bills'] }}</td>
                            <td class="r">{{ $m($row['total']) }}</td>
                            <td class="r fr-bronze">{{ $row['pending'] ? $m($row['pending']) : '' }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="fr-empty">No expenses.</td></tr>
                    @endforelse
                </table>
            </x-filament::section>
        </div>
    </div>
</x-filament-panels::page>
