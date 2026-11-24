<?php

namespace App\Filament\Resources\Subcategories;

use App\Filament\Resources\Subcategories\Pages\ManageSubcategories;
use App\Filament\Resources\Subcategories\Schemas\SubcategoryForm;
use App\Filament\Resources\Subcategories\Tables\SubcategoriesTable;
use App\Models\Subcategory;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

class SubcategoryResource extends Resource
{
    protected static ?string $model = Subcategory::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedSquares2x2;

    protected static string|BackedEnum|null $activeNavigationIcon = Heroicon::Squares2x2;

    protected static string|UnitEnum|null $navigationGroup = 'Shop';

    protected static ?int $navigationSort = 3;

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Schema $schema): Schema
    {
        return SubcategoryForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return SubcategoriesTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageSubcategories::route('/'),
        ];
    }
}
