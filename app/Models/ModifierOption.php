<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ModifierOption extends Model
{
    protected $fillable = ['modifier_group_id', 'name_en', 'name_zh', 'price_adjustment', 'is_base', 'sort_order'];
    protected $casts = ['price_adjustment' => 'decimal:2', 'is_base' => 'boolean', 'sort_order' => 'integer'];

    public function group()
    {
        return $this->belongsTo(ModifierGroup::class, 'modifier_group_id');
    }
}
