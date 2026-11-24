<?php

namespace App\Policies;

class CategoryPolicy extends ShopResourcePolicy
{
    protected function group(): string
    {
        return 'categories';
    }
}
