<?php

namespace App\Policies;

class ProductPolicy extends ShopResourcePolicy
{
    protected function group(): string
    {
        return 'products';
    }
}
