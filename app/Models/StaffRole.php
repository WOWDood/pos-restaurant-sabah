<?php

namespace App\Models;

use Database\Factories\StaffRoleFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class StaffRole extends Model
{
    /** @use HasFactory<StaffRoleFactory> */
    use HasFactory;

    public const PERMISSIONS = [
        'view-dashboard' => 'View dashboard and sales summaries',
        'view-orders' => 'View orders and print receipts',
        'use-pos' => 'Use POS, edit bills, and take payments',
        'manage-menu' => 'Manage categories, items, photos, and modifiers',
        'manage-tables' => 'Manage tables and table QR codes',
        'manage-payment-methods' => 'Manage payment methods',
    ];

    protected $fillable = ['name', 'permissions'];

    protected function casts(): array
    {
        return ['permissions' => 'array'];
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }
}
