<?php

namespace App\Http\Controllers;

use App\Models\BusinessSetting;
use App\Models\ItemPhoto;
use App\Models\RestaurantTable;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;

class CustomerOrderController extends Controller
{
    public function create(string $table): Response
    {
        $restaurantTable = ctype_digit($table) ? RestaurantTable::find($table) : null;
        $businessName = BusinessSetting::where('key', 'business_name')->value('value') ?: 'Restaurant';

        return response()->view('customer-order', [
            'table' => $restaurantTable,
            'businessName' => $businessName,
        ], $restaurantTable ? 200 : 404);
    }

    public function photo(ItemPhoto $photo): Response
    {
        abort_unless($photo->item()->where('active', true)->exists(), 404);
        $source = Storage::disk('public');
        abort_unless($source->exists($photo->path), 404);
        $cachePath = 'menu-thumbnails/'.hash('sha256', $photo->path.'|'.$source->lastModified($photo->path)).'.jpg';
        $cache = Storage::disk('local');

        if (! $cache->exists($cachePath)) {
            $contents = $source->get($photo->path);
            $dimensions = $contents ? @getimagesizefromstring($contents) : false;
            abort_unless($dimensions && $dimensions[0] > 0 && $dimensions[1] > 0 && $dimensions[0] * $dimensions[1] <= 40000000, 404);
            $image = @imagecreatefromstring($contents);
            abort_unless($image, 404);
            $scale = min(1, 320 / max($dimensions[0], $dimensions[1]));
            $width = max(1, (int) round($dimensions[0] * $scale));
            $height = max(1, (int) round($dimensions[1] * $scale));
            $thumbnail = imagecreatetruecolor($width, $height);
            imagefill($thumbnail, 0, 0, imagecolorallocate($thumbnail, 255, 253, 249));
            imagecopyresampled($thumbnail, $image, 0, 0, 0, 0, $width, $height, $dimensions[0], $dimensions[1]);
            ob_start();
            imagejpeg($thumbnail, null, 78);
            $cache->put($cachePath, ob_get_clean());
        }

        return response($cache->get($cachePath), 200, ['Content-Type' => 'image/jpeg', 'Cache-Control' => 'public, max-age=86400']);
    }
}
