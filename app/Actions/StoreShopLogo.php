<?php

namespace App\Actions;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

class StoreShopLogo
{
    public function handle(UploadedFile $upload): string
    {
        $source = imagecreatefromstring($upload->get());
        if (! $source) {
            throw new RuntimeException('The logo could not be read.');
        }

        $scale = min(1, 320 / max(imagesx($source), imagesy($source)));
        $width = max(1, (int) round(imagesx($source) * $scale));
        $height = max(1, (int) round(imagesy($source) * $scale));
        $image = imagecreatetruecolor($width, $height);
        imagealphablending($image, false);
        imagesavealpha($image, true);
        imagefill($image, 0, 0, imagecolorallocatealpha($image, 255, 255, 255, 127));
        imagecopyresampled($image, $source, 0, 0, 0, 0, $width, $height, imagesx($source), imagesy($source));
        ob_start();
        imagepng($image, null, 7);
        $contents = ob_get_clean();
        $path = 'shop-logos/'.Str::uuid().'.png';

        if (! Storage::disk('local')->put($path, $contents)) {
            throw new RuntimeException('The logo could not be stored.');
        }

        return $path;
    }
}
