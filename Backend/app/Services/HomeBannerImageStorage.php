<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use Intervention\Image\ImageManagerStatic as Image;

class HomeBannerImageStorage
{
    private const DESKTOP_MAX_WIDTH = 1920;

    private const MOBILE_MAX_WIDTH = 800;

    public function store(UploadedFile $file, string $variant): string
    {
        $maxW = $variant === 'mobile' ? self::MOBILE_MAX_WIDTH : self::DESKTOP_MAX_WIDTH;
        $image = Image::make($file);
        if ($image->width() > $maxW) {
            $image->resize($maxW, null, function ($constraint) {
                $constraint->aspectRatio();
                $constraint->upsize();
            });
        }

        $encoded = $image->encode('jpg', 85);
        $hash = md5($encoded->__toString() . microtime(true));
        $relative = "marketing-banners/{$hash}.jpg";
        $absoluteDir = public_path('img/marketing-banners');
        if (!File::isDirectory($absoluteDir)) {
            File::makeDirectory($absoluteDir, 0755, true);
        }
        $encoded->save(public_path('img/' . $relative));

        return '/' . $relative;
    }

    public function deleteIfStored(?string $path): void
    {
        $path = trim((string) $path);
        if ($path === '' || !str_starts_with($path, '/marketing-banners/')) {
            return;
        }

        $absolute = public_path('img' . $path);
        if (is_file($absolute)) {
            @unlink($absolute);
        }
    }
}