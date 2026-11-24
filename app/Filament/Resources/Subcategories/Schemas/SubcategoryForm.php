<?php

namespace App\Filament\Resources\Subcategories\Schemas;

use App\Filament\Resources\Categories\Schemas\CategoryForm;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Illuminate\Validation\Rules\Unique;

class SubcategoryForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                Select::make('category_id')
                    ->label('Category')
                    ->relationship('category', 'name')
                    ->searchable()
                    ->preload()
                    ->required()
                    ->live()
                    ->createOptionForm(CategoryForm::fields())
                    ->createOptionModalHeading('New category'),
                ...static::fields(),
            ]);
    }

    /**
     * Name + published. Also used by the "new subcategory" popup on the
     * product form, where the category comes from the product instead.
     */
    public static function fields(bool $withUniqueRule = true): array
    {
        $name = TextInput::make('name')
            ->required()
            ->maxLength(255)
            ->placeholder('e.g. Charm Bracelets');

        if ($withUniqueRule) {
            $name
                ->unique(
                    'subcategories',
                    'name',
                    ignoreRecord: true,
                    modifyRuleUsing: fn (Unique $rule, Get $get) => $rule->where('category_id', $get('category_id')),
                )
                ->validationMessages(['unique' => 'This category already has a subcategory with that name.']);
        }

        return [
            $name,
            Toggle::make('is_published')
                ->label('Published')
                ->helperText('Hidden subcategories are not shown in the shop.')
                ->default(true),
        ];
    }
}
