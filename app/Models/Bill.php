<?php

namespace App\Models;

use App\Enums\BillStatus;
use App\Enums\BillType;
use App\Enums\PaymentMethod;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable([
    'type', 'party_name', 'party_phone', 'bill_date', 'status', 'payment_method',
    'due_date', 'paid_at', 'discount', 'description', 'notes',
])]
class Bill extends Model
{
    use SoftDeletes;

    protected $attributes = [
        'status' => 'pending',
        'subtotal' => 0,
        'discount' => 0,
        'total' => 0,
    ];

    protected function casts(): array
    {
        return [
            'type' => BillType::class,
            'status' => BillStatus::class,
            'payment_method' => PaymentMethod::class,
            'bill_date' => 'date',
            'due_date' => 'date',
            'paid_at' => 'datetime',
            'subtotal' => 'decimal:2',
            'discount' => 'decimal:2',
            'total' => 'decimal:2',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Bill $bill) {
            $bill->bill_date ??= today();
            $bill->number ??= static::nextNumber($bill->type);
            $bill->created_by ??= auth('admin')->id();
        });

        static::saving(function (Bill $bill) {
            if ($bill->status === BillStatus::Paid) {
                $bill->paid_at ??= now();
                $bill->payment_method ??= PaymentMethod::Cash;
                $bill->due_date = null;
            } else {
                $bill->paid_at = null;
                $bill->payment_method = null;
            }

            $bill->total = max(0, (float) $bill->subtotal - (float) $bill->discount);
        });

        // Stock: a sale takes products out of stock. Deleting the sale puts
        // them back; restoring it takes them out again.
        static::deleted(function (Bill $bill) {
            if (! $bill->isForceDeleting()) {
                $bill->returnStock();
            }
        });

        static::forceDeleting(function (Bill $bill) {
            if (! $bill->trashed()) {
                $bill->returnStock();
            }
        });

        static::restored(fn (Bill $bill) => $bill->takeStock());
    }

    public function items(): HasMany
    {
        return $this->hasMany(BillItem::class)->orderBy('sort_order');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'created_by');
    }

    public function scopeIncoming(Builder $query): void
    {
        $query->where('type', BillType::In);
    }

    public function scopeOutgoing(Builder $query): void
    {
        $query->where('type', BillType::Out);
    }

    public function scopePending(Builder $query): void
    {
        $query->where('status', BillStatus::Pending);
    }

    public function scopePaid(Builder $query): void
    {
        $query->where('status', BillStatus::Paid);
    }

    public function isOverdue(): bool
    {
        return $this->status === BillStatus::Pending
            && $this->due_date !== null
            && $this->due_date->isBefore(today());
    }

    public function markAsPaid(PaymentMethod $method = PaymentMethod::Cash): void
    {
        $this->update([
            'status' => BillStatus::Paid,
            'payment_method' => $method,
            'paid_at' => now(),
        ]);
    }

    /** Re-add up all lines. Called whenever a line changes. */
    public function recalculateTotals(): void
    {
        $this->subtotal = $this->items()->sum('line_total');
        $this->save();
    }

    public function affectsStock(): bool
    {
        return $this->type === BillType::In;
    }

    public function returnStock(): void
    {
        if (! $this->affectsStock()) {
            return;
        }

        $this->items()->whereNotNull('product_id')->get()
            ->each(fn (BillItem $item) => Product::adjustStock($item->product_id, $item->quantity));
    }

    public function takeStock(): void
    {
        if (! $this->affectsStock()) {
            return;
        }

        $this->items()->whereNotNull('product_id')->get()
            ->each(fn (BillItem $item) => Product::adjustStock($item->product_id, -$item->quantity));
    }

    /** IN-00001, IN-00002 … and OUT-00001 … (separate counters). */
    public static function nextNumber(BillType|string $type): string
    {
        $type = $type instanceof BillType ? $type : BillType::from($type);
        $prefix = $type->prefix().'-';

        $last = static::withTrashed()
            ->where('number', 'like', $prefix.'%')
            ->orderByDesc('id')
            ->value('number');

        $next = $last ? ((int) substr($last, strlen($prefix))) + 1 : 1;

        while (static::withTrashed()->where('number', $number = $prefix.str_pad((string) $next, 5, '0', STR_PAD_LEFT))->exists()) {
            $next++;
        }

        return $number;
    }
}
