<?php

namespace App\Support;

use Illuminate\Support\Str;

/**
 * File names for stored images: {random}-{timestamp}.{ext}
 * e.g. k3x9q7m2a8w4z1pd-1795400000.jpg
 *
 * The random part keeps names unique even when several files are saved
 * in the same second, and it doesn't reveal the original file name.
 */
class ImageName
{
    public static function generate(?string $extension = 'jpg'): string
    {
        $extension = strtolower(ltrim((string) $extension, '.')) ?: 'jpg';
        $extension = $extension === 'jpeg' ? 'jpg' : $extension;

        return Str::lower(Str::random(16)).'-'.now()->timestamp.'.'.$extension;
    }
}
