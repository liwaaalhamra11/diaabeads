<?php

namespace App\Support;

use App\Models\Product;

/**
 * Keeps a product's image rows in step with the list of paths coming from
 * the admin form: removed images are deleted (with their files), new ones
 * are added, and everything is re-numbered in the order shown.
 */
class ProductGallery
{
    /** @param  array<array-key, string>  $paths */
    public static function sync(Product $product, array $paths): void
    {
        $paths = array_values(array_unique(array_filter($paths)));

        $product->images()
            ->whereNotIn('path', $paths)
            ->get()
            ->each
            ->delete();

        foreach ($paths as $position => $path) {
            $product->images()->updateOrCreate(
                ['path' => $path],
                ['sort_order' => $position],
            );
        }

        $product->unsetRelation('images');
        $product->unsetRelation('coverImage');
    }
}
