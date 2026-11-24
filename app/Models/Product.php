<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

#[Fillable([
    'category_id', 'subcategory_id', 'name', 'description', 'quantity',
    'initial_price', 'selling_price', 'discount_price', 'is_published', 'published_at',
])]
class Product extends Model
{
    use SoftDeletes;

    public const SKU_PREFIX = 'DB-';

    /** No 0/O or 1/I, so SKUs are easy to read out loud and type. */
    private const SKU_ALPHABET = '23456789ABCDEFGHJKLMNPQRSTUVWXYZ';

    protected $attributes = [
        'is_published' => false,
        'quantity' => 0,
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
            'initial_price' => 'decimal:2',
            'selling_price' => 'decimal:2',
            'discount_price' => 'decimal:2',
            'is_published' => 'boolean',
            'published_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        // Every product gets its own SKU, e.g. DB-7KQ2XM. It never changes.
        static::creating(function (Product $product) {
            $product->sku ??= static::generateSku();
        });

        static::saving(function (Product $product) {
            // Keep category and subcategory consistent: the subcategory must
            // belong to the chosen category (or decides it when none is set).
            if ($product->subcategory_id) {
                $parentId = Subcategory::whereKey($product->subcategory_id)->value('category_id');

                if ($product->category_id === null) {
                    $product->category_id = $parentId;
                } elseif ((int) $product->category_id !== (int) $parentId) {
                    $product->subcategory_id = null;
                }
            }

            // Remember when a product first went live.
            if ($product->is_published && ! $product->published_at) {
                $product->published_at = now();
            }
        });

        // Delete image rows one by one so their files are removed too.
        static::forceDeleting(function (Product $product) {
            $product->images()->get()->each->delete();
        });
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function subcategory(): BelongsTo
    {
        return $this->belongsTo(Subcategory::class);
    }

    public function images(): HasMany
    {
        return $this->hasMany(ProductImage::class)->orderBy('sort_order');
    }

    /** The first image is the cover. */
    public function coverImage(): HasOne
    {
        return $this->hasOne(ProductImage::class)->oldestOfMany('sort_order');
    }

    /** Add (positive) or remove (negative) stock. Never goes below zero. */
    public static function adjustStock(int $productId, int $change): void
    {
        if ($change === 0) {
            return;
        }

        DB::transaction(function () use ($productId, $change) {
            $product = static::withTrashed()->lockForUpdate()->find($productId);

            if ($product) {
                $product->quantity = max(0, $product->quantity + $change);
                $product->saveQuietly();
            }
        });
    }

    public function billItems(): HasMany
    {
        return $this->hasMany(BillItem::class);
    }

    /** Lines of this product on sales (IN) bills that are not in the trash. */
    public function soldItems(): HasMany
    {
        return $this->billItems()->whereHas('bill', fn (Builder $bill) => $bill->withoutTrashed()->incoming());
    }

    /** @return array{units: int, revenue: float, sales: int, last_sold_at: ?Carbon} */
    public function salesSummary(): array
    {
        return once(fn () => $this->calculateSalesSummary());
    }

    protected function calculateSalesSummary(): array
    {
        $items = $this->soldItems();

        $lastBillDate = Bill::query()
            ->whereIn('id', (clone $items)->select('bill_id'))
            ->max('bill_date');

        return [
            'units' => (int) (clone $items)->sum('quantity'),
            'revenue' => (float) (clone $items)->sum('line_total'),
            'sales' => (int) (clone $items)->distinct()->count('bill_id'),
            'last_sold_at' => $lastBillDate ? Carbon::parse($lastBillDate) : null,
        ];
    }

    public function profitPerUnit(): float
    {
        return (float) $this->final_price - (float) $this->initial_price;
    }

    public static function generateSku(): string
    {
        do {
            $code = '';
            for ($i = 0; $i < 6; $i++) {
                $code .= self::SKU_ALPHABET[random_int(0, strlen(self::SKU_ALPHABET) - 1)];
            }
            $sku = self::SKU_PREFIX.$code;
        } while (static::withTrashed()->where('sku', $sku)->exists());

        return $sku;
    }

    public function scopePublished(Builder $query): void
    {
        $query->where('is_published', true);
    }

    /*
     * Stock levels — one definition used everywhere (dashboard, menu badge,
     * product filters):
     *   low stock        = 1 … threshold pieces (default 5)
     *   out of stock     = 0 pieces
     *   needs restocking = low stock + out of stock
     */
    public function scopeLowStock(Builder $query): void
    {
        $query->whereBetween('quantity', [1, config('shop.low_stock_threshold')]);
    }

    public function scopeOutOfStock(Builder $query): void
    {
        $query->where('quantity', '<=', 0);
    }

    public function scopeNeedsRestock(Builder $query): void
    {
        $query->where('quantity', '<=', config('shop.low_stock_threshold'));
    }

    public function scopeInStock(Builder $query): void
    {
        $query->where('quantity', '>', config('shop.low_stock_threshold'));
    }

    /** What the customer actually pays. */
    protected function finalPrice(): Attribute
    {
        return Attribute::get(fn () => $this->hasDiscount() ? $this->discount_price : $this->selling_price);
    }

    public function hasDiscount(): bool
    {
        return $this->discount_price !== null
            && (float) $this->discount_price < (float) $this->selling_price;
    }

    public function discountPercent(): ?int
    {
        if (! $this->hasDiscount() || (float) $this->selling_price <= 0) {
            return null;
        }

        return (int) round(100 - ((float) $this->discount_price / (float) $this->selling_price * 100));
    }
}
