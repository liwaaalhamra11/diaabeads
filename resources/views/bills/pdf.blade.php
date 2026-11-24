@php
    use App\Enums\BillStatus;
    use App\Enums\BillType;
    use App\Support\Money;

    /** @var \App\Models\Bill $bill */
    $isSale = $bill->type === BillType::In;
    $isPaid = $bill->status === BillStatus::Paid;
    $m = fn ($v) => Money::format($v);
    $logo = public_path('images/logo-small.png');
@endphp
<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">
<title>{{ $isSale ? 'Receipt' : 'Expense' }} {{ $bill->number }}</title>
<style>
    @page { margin: 36px 40px 56px; }
    * { font-family: 'DejaVu Sans', sans-serif; }
    body { color: #0a0a0a; font-size: 10px; line-height: 1.5; }
    table { width: 100%; border-collapse: collapse; }
    .muted { color: #6b6b6b; }
    .bronze { color: #9b7346; }
    .right { text-align: right; }
    .center { text-align: center; }
    .mono { font-family: 'DejaVu Sans Mono', monospace; }
    .label { font-size: 8px; text-transform: uppercase; letter-spacing: 1.5px; color: #9b7346; font-weight: bold; margin-bottom: 4px; }
    .title { font-size: 22px; font-weight: bold; letter-spacing: .5px; }
    .stamp { display: inline-block; padding: 4px 12px; font-size: 10px; font-weight: bold; letter-spacing: 2px; text-transform: uppercase; border: 1.5px solid #0a0a0a; }
    .stamp.pending { border-color: #9b7346; color: #9b7346; }
    .box td { vertical-align: top; }
    .party { border-top: 2px solid #0a0a0a; padding-top: 8px; }
    .items th { background: #0a0a0a; color: #fff; font-size: 8px; text-transform: uppercase; letter-spacing: 1px; text-align: left; padding: 7px 8px; border-bottom: 2px solid #9b7346; }
    .items th.right { text-align: right; }
    .items th.center { text-align: center; }
    .items td { padding: 8px; border-bottom: 1px solid #ececec; vertical-align: top; }
    .totals td { padding: 4px 8px; }
    .totals tr.grand td { font-size: 14px; font-weight: bold; border-top: 2px solid #0a0a0a; padding-top: 8px; }
    .note { margin-top: 22px; padding: 10px 12px; background: #f7f5f2; border-left: 3px solid #9b7346; }
    .footer { position: fixed; bottom: -36px; left: 0; right: 0; text-align: center; font-size: 8.5px; color: #8a8a8a; border-top: 1px solid #e5e5e5; padding-top: 6px; }
</style>
</head>
<body>

<div class="footer">
    @if ($isSale)
        Thank you for shopping with {{ config('app.name') }} · Where your style matters
    @else
        {{ config('app.name') }} · Expense record · Generated {{ now()->format('d M Y, H:i') }}
    @endif
</div>

{{-- Header --}}
<table class="box">
    <tr>
        <td style="width:90px">
            @if (file_exists($logo))
                <img src="{{ $logo }}" style="width:80px;height:80px" alt="">
            @endif
        </td>
        <td style="padding-left:10px">
            <div class="label">{{ config('app.name') }}</div>
            <div class="title">{{ $isSale ? 'Sales receipt' : 'Expense bill' }}</div>
            <div class="mono" style="font-size:12px;margin-top:2px">{{ $bill->number }}</div>
        </td>
        <td class="right">
            <span class="stamp {{ $isPaid ? '' : 'pending' }}">{{ $bill->status->getLabel() }}</span>
            <div style="margin-top:10px" class="muted">Date</div>
            <div style="font-weight:bold">{{ $bill->bill_date->format('d M Y') }}</div>
        </td>
    </tr>
</table>

{{-- Parties --}}
<table class="box" style="margin-top:22px">
    <tr>
        <td class="party" style="width:50%;padding-right:16px">
            <div class="label">{{ $isSale ? 'Customer' : 'Paid to' }}</div>
            <div style="font-size:12px;font-weight:bold">{{ $bill->party_name }}</div>
            @if ($bill->party_phone)
                <div class="muted">{{ $bill->party_phone }}</div>
            @endif
        </td>
        <td class="party" style="width:50%;padding-left:16px">
            <div class="label">Payment</div>
            @if ($isPaid)
                <div><strong>Paid</strong>{{ $bill->payment_method ? ' by '.$bill->payment_method->getLabel() : '' }}</div>
                @if ($bill->paid_at)
                    <div class="muted">on {{ $bill->paid_at->format('d M Y') }}</div>
                @endif
            @else
                <div class="bronze"><strong>{{ $isSale ? 'To be paid by the customer' : 'To be paid' }}</strong></div>
                <div class="muted">{{ $bill->due_date ? 'Due '.$bill->due_date->format('d M Y') : 'No due date set' }}</div>
            @endif
        </td>
    </tr>
</table>

{{-- Lines --}}
<table class="items" style="margin-top:22px">
    <tr>
        <th>{{ $isSale ? 'Product' : 'Description' }}</th>
        <th class="center" style="width:50px">Qty</th>
        <th class="right" style="width:90px">Unit price</th>
        <th class="right" style="width:90px">Total</th>
    </tr>
    @foreach ($bill->items as $item)
        <tr>
            <td>
                {{ $item->product?->name ?? $item->description }}
                @if ($item->product)
                    <div class="muted mono" style="font-size:8px">{{ $item->product->sku }}</div>
                @endif
            </td>
            <td class="center">{{ $item->quantity }}</td>
            <td class="right">{{ $m($item->unit_price) }}</td>
            <td class="right">{{ $m($item->line_total) }}</td>
        </tr>
    @endforeach
</table>

{{-- Totals --}}
<table style="margin-top:10px">
    <tr>
        <td style="width:55%"></td>
        <td>
            <table class="totals">
                <tr><td class="muted">Subtotal</td><td class="right">{{ $m($bill->subtotal) }}</td></tr>
                @if ((float) $bill->discount > 0)
                    <tr><td class="bronze">Discount</td><td class="right bronze">−{{ $m($bill->discount) }}</td></tr>
                @endif
                <tr class="grand">
                    <td>{{ $isPaid ? ($isSale ? 'Total paid' : 'Total') : 'Amount due' }}</td>
                    <td class="right">{{ $m($bill->total) }}</td>
                </tr>
            </table>
        </td>
    </tr>
</table>

@if ($bill->description)
    <div class="note">
        <div class="label" style="margin-bottom:2px">Description</div>
        {{ $bill->description }}
    </div>
@endif

</body>
</html>
