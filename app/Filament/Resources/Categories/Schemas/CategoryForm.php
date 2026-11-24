<?php

namespace App\Filament\Resources\Categories\Schemas;

use App\Models\Category;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class CategoryForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components(static::fields());
    }

    /** Shared with the "new category" popup on the product form. */
    public static function fields(): array
    {
        return [
            TextInput::make('name')
                ->required()
                ->maxLength(255)
                ->unique(Category::class, 'name', ignoreRecord: true)
                ->placeholder('e.g. Bracelets')
                ->autofocus(),
            Toggle::make('is_published')
                ->label('Published')
                ->helperText('Hidden categories are not shown in the shop.')
                ->default(true),
        ];
    }
}
