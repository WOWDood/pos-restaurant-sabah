<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Category extends Model
{
    use \Illuminate\Database\Eloquent\Factories\HasFactory;

    protected $fillable = ['code', 'description', 'sort_order'];

    protected $casts = ['sort_order' => 'integer'];

    public function items()
    {
        return $this->hasMany(Item::class, 'category_id');
    }
}
