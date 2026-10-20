<?php

namespace App\Models;

use Database\Factories\OrderFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

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

    protected $fillable = ['restaurant_table_id', 'status', 'total', 'paid_at', 'created_by'];

    protected $casts = [
        'total' => 'decimal:2',
        'paid_at' => 'datetime',
    ];

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
