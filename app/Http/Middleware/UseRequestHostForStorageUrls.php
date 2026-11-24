<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response;

/**
 * Builds public file URLs (product photos etc.) from the address the site is
 * opened on — 127.0.0.1:8000, localhost:8000, or MAMP's
 * localhost:8888/DiaaBeads/public — instead of the fixed APP_URL.
 *
 * Without this, opening the admin on a different address than APP_URL makes
 * the browser block the photo uploader from fetching existing images
 * (cross-origin), so they spin forever on edit pages.
 */
class UseRequestHostForStorageUrls
{
    public function handle(Request $request, Closure $next): Response
    {
        $url = $request->getSchemeAndHttpHost().$request->getBasePath().'/storage';

        if (config('filesystems.disks.public.url') !== $url) {
            config(['filesystems.disks.public.url' => $url]);
            Storage::forgetDisk('public'); // rebuild with the new URL
        }

        return $next($request);
    }
}
