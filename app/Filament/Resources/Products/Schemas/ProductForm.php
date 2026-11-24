<?php

namespace App\Filament\Resources\Products\Schemas;

use App\Filament\Resources\Categories\Schemas\CategoryForm;
use App\Models\Product;
use App\Models\Subcategory;
use App\Support\ImageName;
use App\Support\Money;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\HtmlString;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;

class ProductForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(3)
            ->components([
                Group::make()
                    ->columnSpan(['lg' => 2])
                    ->schema([
                        static::detailsSection(),
                        static::imagesSection(),
                    ]),
                Group::make()
                    ->columnSpan(['lg' => 1])
                    ->schema([
                        static::statusSection(),
                        static::pricingSection(),
                        static::inventorySection(),
                    ]),
            ]);
    }

    protected static function detailsSection(): Section
    {
        return Section::make('Product details')
            ->icon(Heroicon::OutlinedShoppingBag)
            ->columns(2)
            ->schema([
                TextInput::make('name')
                    ->required()
                    ->maxLength(255)
                    ->placeholder('e.g. Black Onyx Beaded Bracelet'),
                TextInput::make('sku')
                    ->label('SKU')
                    ->disabled()
                    ->dehydrated(false)
                    ->placeholder('Generated automatically when saved')
                    ->copyable(fn (?Product $record) => filled($record?->sku))
                    ->helperText('Unique product code. Created for you and never changes.'),
                Select::make('category_id')
                    ->label('Category')
                    ->relationship('category', 'name', fn (Builder $query) => $query->orderBy('name'))
                    ->searchable()
                    ->preload()
                    ->placeholder('Choose a category')
                    ->live()
                    // A new category means the old subcategory no longer fits.
                    ->afterStateUpdated(fn (Set $set) => $set('subcategory_id', null))
                    ->createOptionForm(CategoryForm::fields())
                    ->createOptionModalHeading('New category'),
                Select::make('subcategory_id')
                    ->label('Subcategory')
                    ->relationship(
                        'subcategory',
                        'name',
                        fn (Builder $query, Get $get) => $query
                            ->where('category_id', $get('category_id'))
                            ->orderBy('name'),
                    )
                    ->searchable()
                    ->preload()
                    // Only usable once a category that has subcategories is chosen.
                    ->disabled(fn (Get $get) => ! static::categoryHasSubcategories($get('category_id')))
                    ->placeholder(fn (Get $get) => match (true) {
                        blank($get('category_id')) => 'Choose a category first',
                        ! static::categoryHasSubcategories($get('category_id')) => 'This category has no subcategories',
                        default => 'Choose a subcategory',
                    }),
                RichEditor::make('description')
                    ->toolbarButtons([
                        'bold', 'italic', 'underline', 'strike', 'link',
                        'h2', 'h3', 'bulletList', 'orderedList', 'blockquote',
                        'undo', 'redo',
                    ])
                    ->columnSpanFull(),
            ]);
    }

    protected static function imagesSection(): Section
    {
        return Section::make('Images')
            ->description('Upload as many photos as you like. Drag to reorder; the first one is the cover.')
            ->icon(Heroicon::OutlinedPhoto)
            ->schema([
                // Stored in the product_images table (one product → many images).
                FileUpload::make('gallery')
                    ->hiddenLabel()
                    ->image()
                    ->multiple()
                    ->reorderable()
                    ->appendFiles()
                    ->panelLayout('grid')
                    ->imageEditor()
                    ->disk(config('shop.images_disk'))
                    ->directory('products')
                    ->visibility('public')
                    // Saved as random-timestamp.ext, e.g. k3x9q7m2a8w4z1pd-1795400000.jpg
                    ->getUploadedFileNameForStorageUsing(
                        fn (TemporaryUploadedFile $file) => ImageName::generate($file->guessExtension() ?? $file->getClientOriginalExtension()),
                    )
                    ->maxSize(5120)
                    ->maxFiles(20)
                    ->helperText('JPG, PNG or WebP, up to 5 MB each, max 20 images.'),
            ]);
    }

    protected static function statusSection(): Section
    {
        return Section::make('Status')
            ->icon(Heroicon::OutlinedEye)
            ->schema([
                Toggle::make('is_published')
                    ->label('Published')
                    ->helperText('Only published products are visible in the shop.')
                    ->default(false),
                TextEntry::make('published_at')
                    ->label('Live since')
                    ->dateTime()
                    ->visible(fn (?Product $record) => $record?->published_at !== null),
            ]);
    }

    protected static function pricingSection(): Section
    {
        $currency = Money::currency();

        return Section::make('Pricing')
            ->icon(Heroicon::OutlinedBanknotes)
            ->schema([
                TextInput::make('initial_price')
                    ->label('Initial price (cost)')
                    ->helperText('What the item costs you. Never shown to customers.')
                    ->numeric()
                    ->minValue(0)
                    ->step('0.01')
                    ->prefix($currency)
                    ->default(0)
                    ->required()
                    ->live(onBlur: true),
                TextInput::make('selling_price')
                    ->label('Selling price')
                    ->numeric()
                    ->minValue(0)
                    ->step('0.01')
                    ->prefix($currency)
                    ->required()
                    ->live(onBlur: true),
                TextInput::make('discount_price')
                    ->label('Discount price')
                    ->helperText('Optional sale price. Leave empty for no discount.')
                    ->numeric()
                    ->minValue(0)
                    ->step('0.01')
                    ->prefix($currency)
                    ->lt('selling_price')
                    ->validationMessages(['lt' => 'The discount price must be lower than the selling price.'])
                    ->live(onBlur: true),
                TextEntry::make('price_summary')
                    ->hiddenLabel()
                    ->state(fn (Get $get) => static::priceSummary($get)),
            ]);
    }

    protected static function inventorySection(): Section
    {
        return Section::make('Inventory')
            ->icon(Heroicon::OutlinedArchiveBox)
            ->schema([
                TextInput::make('quantity')
                    ->label('Quantity in stock')
                    ->numeric()
                    ->integer()
                    ->minValue(0)
                    ->default(0)
                    ->required()
                    ->suffix('pcs'),
            ]);
    }

    public static function categoryHasSubcategories(mixed $categoryId): bool
    {
        return filled($categoryId)
            && Subcategory::where('category_id', $categoryId)->exists();
    }

    /** Live "customer pays / you earn" summary under the price fields. */
    protected static function priceSummary(Get $get): HtmlString
    {
        $cost = (float) $get('initial_price');
        $selling = (float) $get('selling_price');
        $discount = $get('discount_price');
        $final = (filled($discount) && (float) $discount < $selling) ? (float) $discount : $selling;

        if ($selling <= 0) {
            return new HtmlString('<span style="color:#737373">Enter a selling price to see the profit.</span>');
        }

        $profit = $final - $cost;
        $margin = $final > 0 ? round($profit / $final * 100) : 0;
        $profitColor = $profit < 0 ? '#b91c1c' : '#8f6d46';
        $off = $final < $selling ? ' <span style="color:#8f6d46">('.round(100 - $final / $selling * 100).'% off)</span>' : '';

        return new HtmlString(
            '<div style="line-height:1.8">'
            .'Customer pays <strong>'.e(Money::format($final)).'</strong>'.$off.'<br>'
            .'Your profit <strong style="color:'.$profitColor.'">'.e(Money::format($profit)).'</strong>'
            .' <span style="color:#737373">('.$margin.'% margin)</span>'
            .'</div>'
        );
    }
}
