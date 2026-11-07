<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BusinessSetting extends Model
{
    protected $fillable = ['key', 'value'];

    /** @return array<string, string> */
    public static function settings(): array
    {
        return array_replace([
            'business_name' => 'Kedai Soto Banso', 'location' => '', 'phone' => '',
            'email' => '', 'logo_path' => '', 'tax_enabled' => '0', 'tax_name' => 'Tax',
            'tax_rate' => '0.00', 'menu_message' => 'Choose your favourites, made your way.',
            'receipt_footer' => 'Thank you for your visit!',
        ], static::query()->pluck('value', 'key')->map(fn ($value): string => (string) $value)->all());
    }

    /** @param array<string, string> $settings */
    public static function logoUrl(array $settings): ?string
    {
        return filled($settings['logo_path'] ?? null)
            ? route('shop.logo', ['v' => substr(hash('sha256', $settings['logo_path']), 0, 16)])
            : null;
    }
}
