<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Item extends Model
{
    /** @use HasFactory<\Database\Factories\ItemFactory> */
    use HasFactory;

    protected $fillable = ['code', 'category_id', 'description', 'notes', 'unit_price', 'active', 'type', 'is_combo'];

    protected $casts = ['active' => 'boolean', 'is_combo' => 'boolean', 'unit_price' => 'decimal:2'];

    public function category()
    {
        return $this->belongsTo(Category::class);
    }
}
