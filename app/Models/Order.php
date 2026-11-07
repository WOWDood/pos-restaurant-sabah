<?php

namespace App\Models;

use Database\Factories\OrderFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Order extends Model
{
    /** @use HasFactory<OrderFactory> */
    use HasFactory;

    public const STATUS_NEW = 'new';

    public const STATUS_PRE_ORDER = 'pre_order';

    public const STATUS_PAID = 'paid';

    public const STATUS_CANCELLED = 'cancelled';

    /** @var array<int, string> */
    public const STATUSES = [
        self::STATUS_NEW,
        self::STATUS_PRE_ORDER,
        self::STATUS_PAID,
        self::STATUS_CANCELLED,
    ];

    /** @var array<int, string> */
    public const OPEN_STATUSES = [
        self::STATUS_NEW,
        self::STATUS_PRE_ORDER,
    ];

    protected $fillable = ['restaurant_table_id', 'status', 'total', 'paid_at', 'created_by', 'subtotal', 'tax_name', 'tax_rate', 'tax_amount'];

    protected $casts = [
        'total' => 'decimal:2',
        'subtotal' => 'decimal:2',
        'tax_rate' => 'decimal:2',
        'tax_amount' => 'decimal:2',
        'paid_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function (Order $order): void {
            if (! array_key_exists('tax_rate', $order->getAttributes())) {
                $settings = BusinessSetting::settings();
                $order->tax_rate = $settings['tax_enabled'] === '1' ? $settings['tax_rate'] : 0;
                $order->tax_name = $settings['tax_enabled'] === '1' ? $settings['tax_name'] : null;
            }
        });
    }

    public function recalculateTotals(): void
    {
        $subtotal = round((float) $this->items()->sum('subtotal'), 2);
        $taxAmount = round($subtotal * (float) $this->tax_rate / 100, 2);

        $this->update(['subtotal' => $subtotal, 'tax_amount' => $taxAmount, 'total' => round($subtotal + $taxAmount, 2)]);
    }

    public function table(): BelongsTo
    {
        return $this->belongsTo(RestaurantTable::class, 'restaurant_table_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function receipt(): HasOne
    {
        return $this->hasOne(Receipt::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function isOpen(): bool
    {
        return in_array($this->status, self::OPEN_STATUSES, true);
    }

    public function isEditable(): bool
    {
        return ! in_array($this->status, [self::STATUS_PAID, self::STATUS_CANCELLED], true);
    }

    public static function statusLabel(string $status): string
    {
        return match ($status) {
            self::STATUS_NEW => 'New',
            self::STATUS_PRE_ORDER => 'Pre-Order',
            self::STATUS_PAID => 'Paid',
            self::STATUS_CANCELLED => 'Cancelled',
            default => ucfirst(str_replace('_', ' ', $status)),
        };
    }

    public static function statusBadgeClasses(string $status): string
    {
        return match ($status) {
            self::STATUS_NEW => 'bg-yellow-100 text-yellow-700',
            self::STATUS_PRE_ORDER => 'bg-blue-100 text-blue-700',
            self::STATUS_PAID => 'bg-green-100 text-green-700',
            self::STATUS_CANCELLED => 'bg-red-100 text-red-700',
            default => 'bg-gray-100 text-gray-600',
        };
    }
}
