<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['bill_id', 'product_id', 'description', 'quantity', 'unit_price', 'sort_order'])]
class BillItem extends Model
{
    protected $attributes = [
        'quantity' => 1,
        'sort_order' => 0,
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
            'unit_price' => 'decimal:2',
            'unit_cost' => 'decimal:2',
            'line_total' => 'decimal:2',
            'sort_order' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (BillItem $item) {
            if ($item->product_id && (blank($item->description) || $item->isDirty('product_id'))) {
                $product = Product::withTrashed()->find($item->product_id);
                $item->description = $product ? "{$product->name} ({$product->sku})" : 'Product';
                // Remember what the piece cost us when it was sold.
                $item->unit_cost = $product?->initial_price ?? 0;
            }

            $item->line_total = round($item->quantity * (float) $item->unit_price, 2);
        });

        // Keep product stock in step with sale lines.
        static::created(function (BillItem $item) {
            if ($item->movesStock() && $item->product_id) {
                Product::adjustStock($item->product_id, -$item->quantity);
            }
        });

        static::updated(function (BillItem $item) {
            if (! $item->movesStock() || ! $item->wasChanged(['product_id', 'quantity'])) {
                return;
            }

            $oldProduct = $item->getOriginal('product_id');
            $oldQuantity = (int) $item->getOriginal('quantity');

            if ($oldProduct) {
                Product::adjustStock($oldProduct, $oldQuantity);
            }

            if ($item->product_id) {
                Product::adjustStock($item->product_id, -$item->quantity);
            }
        });

        static::deleted(function (BillItem $item) {
            if ($item->movesStock() && $item->product_id) {
                Product::adjustStock($item->product_id, $item->quantity);
            }
        });

        static::saved(fn (BillItem $item) => $item->bill?->recalculateTotals());
        static::deleted(fn (BillItem $item) => $item->bill?->recalculateTotals());
    }

    public function bill(): BelongsTo
    {
        return $this->belongsTo(Bill::class)->withTrashed();
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class)->withTrashed();
    }

    /** Only lines on a live sale bill move stock (a trashed bill already gave it back). */
    protected function movesStock(): bool
    {
        $bill = $this->bill;

        return $bill !== null && $bill->affectsStock() && ! $bill->trashed();
    }
}
