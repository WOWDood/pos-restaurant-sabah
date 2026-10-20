<?php

namespace App\Models;

use Database\Factories\ItemFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Item extends Model
{
    /** @use HasFactory<ItemFactory> */
    use HasFactory;

    protected $fillable = ['code', 'category_id', 'description', 'notes', 'unit_price', 'active', 'type', 'is_combo'];

    protected $casts = ['active' => 'boolean', 'is_combo' => 'boolean', 'unit_price' => 'decimal:2'];

    public function category()
    {
        return $this->belongsTo(Category::class);
    }

    public function photos()
    {
        return $this->hasMany(ItemPhoto::class);
    }

    public function modifierGroups()
    {
        return $this->hasMany(ModifierGroup::class, 'item_id');
    }

    public function orderItems()
    {
        return $this->hasMany(OrderItem::class);
    }
}
