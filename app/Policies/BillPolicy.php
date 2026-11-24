<?php

namespace App\Policies;

class BillPolicy extends ShopResourcePolicy
{
    protected function group(): string
    {
        return 'bills';
    }
}
