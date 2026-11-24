<?php

namespace App\Policies;

class SubcategoryPolicy extends ShopResourcePolicy
{
    protected function group(): string
    {
        return 'subcategories';
    }
}
