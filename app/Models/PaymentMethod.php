<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PaymentMethod extends Model
{
    use \Illuminate\Database\Eloquent\Factories\HasFactory;

    protected $fillable = ['description', 'sort_order', 'active', 'ref_required'];
    protected $casts = ['active' => 'boolean', 'ref_required' => 'boolean', 'sort_order' => 'integer'];
}
