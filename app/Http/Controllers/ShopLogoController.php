<?php

namespace App\Http\Controllers;

use App\Models\BusinessSetting;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;

class ShopLogoController extends Controller
{
    public function __invoke(): Response
    {
        $path = BusinessSetting::query()->where('key', 'logo_path')->value('value');
        $disk = Storage::disk('local');
        abort_unless(is_string($path) && str_starts_with($path, 'shop-logos/') && $disk->exists($path), 404);

        return response($disk->get($path), 200, [
            'Content-Type' => 'image/png',
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'public, max-age=86400',
        ]);
    }
}
