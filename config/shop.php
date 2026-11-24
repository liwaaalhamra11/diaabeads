<?php

return [
    // ISO 4217 code used when showing prices in the admin panel.
    'currency' => env('SHOP_CURRENCY', 'USD'),

    // Products at or below this quantity are flagged as "low stock".
    'low_stock_threshold' => (int) env('SHOP_LOW_STOCK', 5),

    // Disk where category and product images are stored.
    'images_disk' => 'public',
];
