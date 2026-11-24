<?php

namespace App\Filament\Resources\Categories\Tables;

use App\Filament\Resources\Subcategories\SubcategoryResource;
use App\Models\Category;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

class CategoriesTable
{
    public static function configure(Table $table): Table
    {
        $deleteWarning = fn (Category $record) => collect([
            $record->subcategories_count ? "Its {$record->subcategories_count} subcategory(ies) will be deleted too." : null,
            $record->products_count ? "{$record->products_count} product(s) will be kept, but left without a category." : null,
        ])->filter()->join(' ') ?: 'Are you sure you want to delete this category?';

        return $table
            ->modifyQueryUsing(fn ($query) => $query->withCount(['products', 'subcategories']))
            ->defaultSort('name')
            ->columns([
                TextColumn::make('name')
                    ->searchable()
                    ->sortable()
                    ->weight('medium'),
                TextColumn::make('subcategories_count')
                    ->label('Subcategories')
                    ->badge()
                    ->color('bronze')
                    ->url(fn (Category $record) => SubcategoryResource::getUrl('index', [
                        'filters' => ['category' => ['values' => [$record->getKey()]]],
                    ]))
                    ->sortable(),
                TextColumn::make('products_count')
                    ->label('Products')
                    ->badge()
                    ->color('gray')
                    ->sortable(),
                ToggleColumn::make('is_published')
                    ->label('Published')
                    ->sortable()
                    ->disabled(fn () => ! auth('admin')->user()?->hasAdminPermission('categories.update')),
            ])
            ->filters([
                TernaryFilter::make('is_published')
                    ->label('Visibility')
                    ->trueLabel('Published')
                    ->falseLabel('Hidden'),
            ])
            ->recordActions([
                EditAction::make()->hiddenLabel()->tooltip('Edit')->modalWidth('md'),
                DeleteAction::make()->hiddenLabel()->tooltip('Delete')->modalDescription($deleteWarning),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ])
            ->emptyStateHeading('No categories yet')
            ->emptyStateDescription('Create categories like Bracelets, Necklaces or Earrings to organise your products.');
    }
}
