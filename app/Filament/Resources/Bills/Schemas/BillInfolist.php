<?php

namespace App\Filament\Resources\Bills\Schemas;

use App\Enums\BillStatus;
use App\Enums\BillType;
use App\Filament\Resources\Products\ProductResource;
use App\Models\Bill;
use App\Models\BillItem;
use App\Support\Money;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\RepeatableEntry\TableColumn;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Enums\TextSize;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\HtmlString;

class BillInfolist
{
    public static function configure(Schema $schema): Schema
    {
        $currency = Money::currency();

        return $schema
            ->columns(3)
            ->components([
                Group::make()
                    ->columnSpan(['lg' => 2])
                    ->schema([
                        Section::make(fn (Bill $record) => $record->type === BillType::In ? 'Customer' : 'Paid to')
                            ->icon(Heroicon::OutlinedUser)
                            ->columns(3)
                            ->schema([
                                TextEntry::make('party_name')
                                    ->label('Name')
                                    ->weight('medium')
                                    ->size(TextSize::Large),
                                TextEntry::make('party_phone')
                                    ->label('Phone')
                                    ->icon(Heroicon::OutlinedPhone)
                                    ->url(fn (Bill $record) => $record->party_phone ? 'tel:'.preg_replace('/[^\d+]/', '', $record->party_phone) : null)
                                    ->placeholder('—'),
                                TextEntry::make('bill_date')
                                    ->label('Bill date')
                                    ->date('l, d M Y')
                                    ->icon(Heroicon::OutlinedCalendar),
                            ]),

                        Section::make(fn (Bill $record) => $record->type === BillType::In ? 'Products sold' : 'What was paid for')
                            ->icon(Heroicon::OutlinedListBullet)
                            ->description(fn (Bill $record) => $record->items->count().' line(s)')
                            ->schema([
                                RepeatableEntry::make('items')
                                    ->hiddenLabel()
                                    ->table(fn (Bill $record) => [
                                        TableColumn::make($record->type === BillType::In ? 'Product' : 'Description'),
                                        TableColumn::make('Qty')->alignment('center')->width('6rem'),
                                        TableColumn::make('Unit price')->alignment('end')->width('9rem'),
                                        TableColumn::make('Total')->alignment('end')->width('9rem'),
                                    ])
                                    ->schema([
                                        TextEntry::make('description')
                                            ->weight('medium')
                                            // Product lines: name, with the SKU small underneath.
                                            ->formatStateUsing(fn (BillItem $record, ?string $state) => $record->product
                                                ? new HtmlString(e($record->product->name)
                                                    .'<br><span style="font-family:ui-monospace,monospace;font-size:.75rem;color:#737373;font-weight:400">'
                                                    .e($record->product->sku).'</span>')
                                                : $state)
                                            ->url(fn (BillItem $record) => $record->product_id && $record->product && ProductResource::canView($record->product)
                                                ? ProductResource::getUrl('view', ['record' => $record->product_id])
                                                : null),
                                        TextEntry::make('quantity')->alignCenter(),
                                        TextEntry::make('unit_price')->money($currency)->alignEnd(),
                                        TextEntry::make('line_total')->money($currency)->alignEnd()->weight('medium'),
                                    ]),
                                TextEntry::make('totals')
                                    ->hiddenLabel()
                                    ->state(fn (Bill $record) => static::totals($record)),
                            ]),
                    ]),

                Group::make()
                    ->columnSpan(['lg' => 1])
                    ->schema([
                        Section::make('Payment')
                            ->icon(Heroicon::OutlinedBanknotes)
                            ->schema([
                                Grid::make(2)->schema([
                                    TextEntry::make('status')
                                        ->badge(),
                                    TextEntry::make('payment_method')
                                        ->label('Paid by')
                                        ->badge()
                                        ->color('gray')
                                        ->visible(fn (Bill $record) => $record->status === BillStatus::Paid),
                                    TextEntry::make('due_date')
                                        ->label(fn (Bill $record) => $record->type === BillType::In ? 'Customer pays by' : 'Pay by')
                                        ->date('d M Y')
                                        ->badge()
                                        ->color(fn (Bill $record) => $record->isOverdue() ? 'danger' : 'bronze')
                                        ->icon(fn (Bill $record) => $record->isOverdue() ? Heroicon::OutlinedExclamationTriangle : Heroicon::OutlinedCalendar)
                                        ->helperText(fn (Bill $record) => $record->isOverdue()
                                            ? 'Overdue by '.$record->due_date->diffInDays(today()).' day(s)'
                                            : null)
                                        ->placeholder('No due date')
                                        ->visible(fn (Bill $record) => $record->status === BillStatus::Pending),
                                ]),
                                TextEntry::make('total')
                                    ->label(fn (Bill $record) => match (true) {
                                        $record->status === BillStatus::Paid && $record->type === BillType::In => 'Received',
                                        $record->status === BillStatus::Paid => 'Paid',
                                        $record->type === BillType::In => 'Customer still owes',
                                        default => 'You still owe',
                                    })
                                    ->money($currency)
                                    ->size(TextSize::Large)
                                    ->weight('bold')
                                    ->color(fn (Bill $record) => $record->status === BillStatus::Pending ? 'bronze' : null),
                                TextEntry::make('paid_at')
                                    ->label('Paid on')
                                    ->dateTime('d M Y, H:i')
                                    ->visible(fn (Bill $record) => $record->paid_at !== null),
                            ]),

                        Section::make('Details')
                            ->icon(Heroicon::OutlinedPencilSquare)
                            ->schema([
                                TextEntry::make('description')
                                    ->placeholder('No description'),
                                TextEntry::make('notes')
                                    ->placeholder('No notes')
                                    ->helperText('Only visible to admins.'),
                            ]),

                        Section::make()
                            ->columns(2)
                            ->schema([
                                TextEntry::make('creator.name')
                                    ->label('Created by')
                                    ->placeholder('—')
                                    ->color('gray'),
                                TextEntry::make('created_at')
                                    ->label('Created')
                                    ->dateTime('d M Y, H:i')
                                    ->color('gray'),
                            ]),
                    ]),
            ]);
    }

    protected static function totals(Bill $record): HtmlString
    {
        $row = fn (string $label, string $value, string $style = '') => "<div style=\"display:flex;justify-content:space-between;gap:2rem;{$style}\"><span>{$label}</span><span>{$value}</span></div>";
        $label = $record->type === BillType::In ? 'You receive' : 'You pay';

        return new HtmlString(
            '<div style="max-width:20rem;margin-left:auto;line-height:2">'
            .$row('Subtotal', e(Money::format($record->subtotal)), 'color:#525252')
            .((float) $record->discount > 0 ? $row('Discount', '−'.e(Money::format($record->discount)), 'color:#8f6d46') : '')
            .$row("<strong>{$label}</strong>", '<strong style="font-size:1.15em">'.e(Money::format($record->total)).'</strong>', 'border-top:1px solid #e5e5e5;padding-top:.25rem')
            .'</div>'
        );
    }
}
