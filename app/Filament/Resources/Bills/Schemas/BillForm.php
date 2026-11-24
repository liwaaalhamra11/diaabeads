<?php

namespace App\Filament\Resources\Bills\Schemas;

use App\Enums\BillStatus;
use App\Enums\BillType;
use App\Enums\PaymentMethod;
use App\Models\Bill;
use App\Models\BillItem;
use App\Models\Product;
use App\Support\Money;
use Closure;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Repeater\TableColumn;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\ToggleButtons;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\HtmlString;

class BillForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(3)
            ->components([
                Group::make()
                    ->columnSpan(['lg' => 2])
                    ->schema([
                        static::billSection(),
                        static::itemsSection(),
                    ]),
                Group::make()
                    ->columnSpan(['lg' => 1])
                    ->schema([
                        static::paymentSection(),
                        static::detailsSection(),
                    ]),
            ]);
    }

    protected static function type(Get $get, string $path = 'type'): BillType
    {
        $type = $get($path);

        return $type instanceof BillType ? $type : (BillType::tryFrom((string) $type) ?? BillType::In);
    }

    protected static function billSection(): Section
    {
        return Section::make('Bill')
            ->icon(Heroicon::OutlinedDocumentText)
            ->columns(2)
            ->schema([
                ToggleButtons::make('type')
                    ->label('Type')
                    ->options(BillType::class)
                    ->default(fn () => BillType::tryFrom((string) request()->query('type')) ?? BillType::In)
                    ->inline()
                    ->required()
                    ->live()
                    // Changing the type of an existing bill would confuse stock and totals.
                    ->disabledOn('edit')
                    ->helperText(fn (string $operation, Get $get) => $operation === 'edit'
                        ? 'The type cannot be changed after the bill is created.'
                        : static::type($get)->getDescription())
                    ->columnSpanFull(),
                TextInput::make('party_name')
                    ->label(fn (Get $get) => static::type($get)->partyLabel())
                    ->placeholder(fn (Get $get) => static::type($get) === BillType::In ? 'Customer name' : 'Supplier, landlord, courier…')
                    ->required()
                    ->maxLength(255)
                    ->prefixIcon(Heroicon::OutlinedUser),
                TextInput::make('party_phone')
                    ->label('Phone')
                    ->tel()
                    ->maxLength(50)
                    ->prefixIcon(Heroicon::OutlinedPhone),
                DatePicker::make('bill_date')
                    ->label('Bill date')
                    ->default(today())
                    ->required()
                    ->native(false)
                    ->displayFormat('d M Y')
                    ->prefixIcon(Heroicon::OutlinedCalendar),
                TextInput::make('number')
                    ->label('Bill number')
                    ->disabled()
                    ->dehydrated(false)
                    ->placeholder('Generated when saved')
                    ->prefixIcon(Heroicon::OutlinedHashtag),
            ]);
    }

    protected static function itemsSection(): Section
    {
        $currency = Money::currency();

        return Section::make(fn (Get $get) => static::type($get) === BillType::In ? 'Products sold' : 'What was paid for')
            ->description(fn (Get $get) => static::type($get) === BillType::In
                ? 'Pick products from the shop. Sold quantities are taken out of stock.'
                : 'Describe each expense line.')
            ->icon(Heroicon::OutlinedListBullet)
            ->schema([
                Repeater::make('items')
                    ->hiddenLabel()
                    ->relationship('items')
                    ->orderColumn('sort_order')
                    ->defaultItems(1)
                    ->minItems(1)
                    ->addActionLabel('Add line')
                    ->live()
                    ->table(fn (Get $get) => static::type($get) === BillType::In
                        ? [
                            TableColumn::make('Product')->markAsRequired(),
                            TableColumn::make('Qty')->markAsRequired()->width('7rem'),
                            TableColumn::make('Unit price')->markAsRequired()->width('11rem'),
                            TableColumn::make('Total')->alignment('end')->width('8rem'),
                        ]
                        : [
                            TableColumn::make('Description')->markAsRequired(),
                            TableColumn::make('Qty')->markAsRequired()->width('7rem'),
                            TableColumn::make('Unit price')->markAsRequired()->width('11rem'),
                            TableColumn::make('Total')->alignment('end')->width('8rem'),
                        ])
                    // Only the fields for this bill type: a hidden field would still
                    // take a table cell and push the columns out of line.
                    ->schema(fn (Get $get) => static::type($get) === BillType::In
                        ? [static::productField(), ...static::amountFields()]
                        : [static::descriptionField(), ...static::amountFields()]),

                TextInput::make('discount')
                    ->label('Discount on the whole bill')
                    ->numeric()
                    ->minValue(0)
                    ->step('0.01')
                    ->prefix($currency)
                    ->default(0)
                    ->live(onBlur: true)
                    ->maxWidth('xs'),

                TextEntry::make('totals_preview')
                    ->hiddenLabel()
                    ->state(fn (Get $get) => static::totalsSummary($get)),
            ]);
    }

    protected static function productField(): Select
    {
        return Select::make('product_id')
            ->hiddenLabel()
            ->options(fn () => Product::query()
                ->orderBy('name')
                ->get()
                ->mapWithKeys(fn (Product $p) => [$p->id => "{$p->name} · {$p->sku} · {$p->quantity} in stock"]))
            ->getOptionLabelUsing(fn ($value) => ($p = Product::withTrashed()->find($value)) ? "{$p->name} · {$p->sku}" : null)
            ->searchable()
            ->disableOptionsWhenSelectedInSiblingRepeaterItems()
            ->placeholder('Choose a product')
            ->required()
            ->live()
            // Fill in the product's current price (can still be changed).
            ->afterStateUpdated(fn (Set $set, ?string $state) => $set('unit_price', $state ? Product::find($state)?->final_price : null));
    }

    protected static function descriptionField(): TextInput
    {
        return TextInput::make('description')
            ->hiddenLabel()
            ->placeholder('e.g. Silver clasps × 100, shop rent…')
            ->maxLength(255)
            ->required();
    }

    /** Qty, unit price and the line total — the same on both bill types. */
    protected static function amountFields(): array
    {
        return [
            TextInput::make('quantity')
                ->hiddenLabel()
                ->numeric()
                ->integer()
                ->minValue(1)
                ->default(1)
                ->required()
                ->live(onBlur: true)
                ->rule(fn (Get $get, $livewire) => static::stockRule($get, $livewire->getRecord())),
            TextInput::make('unit_price')
                ->hiddenLabel()
                ->numeric()
                ->minValue(0)
                ->step('0.01')
                ->prefix(Money::currency())
                ->required()
                ->live(onBlur: true),
            TextEntry::make('line_total_preview')
                ->hiddenLabel()
                ->state(fn (Get $get) => Money::format((int) $get('quantity') * (float) $get('unit_price')))
                ->weight('medium')
                ->alignEnd(),
        ];
    }

    protected static function paymentSection(): Section
    {
        return Section::make('Payment')
            ->icon(Heroicon::OutlinedBanknotes)
            ->schema([
                ToggleButtons::make('status')
                    ->options(BillStatus::class)
                    ->default(BillStatus::Paid)
                    ->inline()
                    ->required()
                    ->live()
                    ->helperText(fn (Get $get) => static::isPending($get)
                        ? (static::type($get) === BillType::In ? 'The customer will pay later.' : 'You will pay this later.')
                        : 'Settled.'),
                ToggleButtons::make('payment_method')
                    ->label('Paid by')
                    ->options(PaymentMethod::class)
                    ->default(PaymentMethod::Cash)
                    ->inline()
                    ->required(fn (Get $get) => ! static::isPending($get))
                    ->visible(fn (Get $get) => ! static::isPending($get)),
                DatePicker::make('due_date')
                    ->label(fn (Get $get) => static::type($get) === BillType::In ? 'Customer pays by' : 'Pay by')
                    ->helperText('Optional. Overdue bills are highlighted.')
                    ->native(false)
                    ->displayFormat('d M Y')
                    ->minDate(fn (Get $get) => $get('bill_date'))
                    ->visible(fn (Get $get) => static::isPending($get)),
                TextEntry::make('paid_at')
                    ->label('Paid on')
                    ->dateTime()
                    ->visible(fn (?Bill $record) => $record?->paid_at !== null),
            ]);
    }

    protected static function detailsSection(): Section
    {
        return Section::make('Details')
            ->icon(Heroicon::OutlinedPencilSquare)
            ->schema([
                Textarea::make('description')
                    ->label('Description')
                    ->helperText(fn (Get $get) => static::type($get) === BillType::In
                        ? 'Who bought and what it was for (e.g. gift order, Instagram sale).'
                        : 'Who you are paying and why (e.g. beads supplier, monthly rent).')
                    ->rows(3),
                Textarea::make('notes')
                    ->label('Notes')
                    ->helperText('Anything else to remember. Only visible to admins.')
                    ->rows(3),
            ]);
    }

    protected static function isPending(Get $get): bool
    {
        $status = $get('status');

        return ($status instanceof BillStatus ? $status : BillStatus::tryFrom((string) $status)) === BillStatus::Pending;
    }

    /**
     * Sold quantity can't be more than what's in stock, plus whatever this
     * bill already took for the same product (when editing a saved sale).
     */
    protected static function stockRule(Get $get, mixed $bill): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail) use ($get, $bill) {
            if (static::type($get, '../../type') !== BillType::In || blank($get('product_id'))) {
                return;
            }

            $product = Product::withTrashed()->find($get('product_id'));

            if (! $product) {
                return;
            }

            $available = $product->quantity;

            if ($bill instanceof Bill && $bill->exists && ! $bill->trashed()) {
                $available += (int) BillItem::where('bill_id', $bill->id)
                    ->where('product_id', $product->id)
                    ->sum('quantity');
            }

            if ((int) $value > $available) {
                $fail($available > 0
                    ? "Only {$available} of {$product->name} in stock."
                    : "{$product->name} is out of stock.");
            }
        };
    }

    protected static function totalsSummary(Get $get): HtmlString
    {
        $subtotal = collect($get('items') ?? [])
            ->sum(fn (array $item) => (int) ($item['quantity'] ?? 0) * (float) ($item['unit_price'] ?? 0));
        $discount = min((float) $get('discount'), $subtotal);
        $total = $subtotal - $discount;
        $sign = static::type($get) === BillType::In ? 'You receive' : 'You pay';

        $row = fn (string $label, string $value, string $style = '') => "<div style=\"display:flex;justify-content:space-between;gap:2rem;{$style}\"><span>{$label}</span><span>{$value}</span></div>";

        return new HtmlString(
            '<div style="max-width:20rem;margin-left:auto;line-height:2">'
            .$row('Subtotal', e(Money::format($subtotal)), 'color:#525252')
            .($discount > 0 ? $row('Discount', '−'.e(Money::format($discount)), 'color:#8f6d46') : '')
            .$row("<strong>{$sign}</strong>", '<strong style="font-size:1.1em">'.e(Money::format($total)).'</strong>', 'border-top:1px solid #e5e5e5;padding-top:.25rem')
            .'</div>'
        );
    }
}
