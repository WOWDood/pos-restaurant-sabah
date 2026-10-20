<?php

namespace App\Models;

use Database\Factories\PaymentMethodFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PaymentMethod extends Model
{
    /** @use HasFactory<PaymentMethodFactory> */
    use HasFactory;

    protected $fillable = ['description', 'sort_order', 'active', 'ref_required'];

    protected $casts = ['active' => 'boolean', 'ref_required' => 'boolean', 'sort_order' => 'integer'];

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }
}
