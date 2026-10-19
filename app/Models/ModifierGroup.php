<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ModifierGroup extends Model
{
    protected $fillable = ['item_id', 'name', 'min_select', 'max_select', 'sort_order'];
    protected $casts = ['min_select' => 'integer', 'max_select' => 'integer', 'sort_order' => 'integer'];

    public function item()
    {
        return $this->belongsTo(Item::class);
    }

    public function options()
    {
        return $this->hasMany(ModifierOption::class, 'modifier_group_id');
    }
}
