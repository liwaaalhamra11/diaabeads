<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['name', 'is_published'])]
class Category extends Model
{
    protected $attributes = [
        'is_published' => true,
    ];

    protected function casts(): array
    {
        return [
            'is_published' => 'boolean',
        ];
    }

    public function subcategories(): HasMany
    {
        return $this->hasMany(Subcategory::class)->orderBy('name');
    }

    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }
}
