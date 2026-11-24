<?php

namespace App\Filament\Resources\Subcategories\Tables;

use App\Models\Subcategory;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Grouping\Group;
use Filament\Tables\Table;

class SubcategoriesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->with('category')->withCount('products'))
            ->defaultSort('name')
            ->defaultGroup(Group::make('category.name')->label('Category')->collapsible())
            ->columns([
                TextColumn::make('name')
                    ->searchable()
                    ->sortable()
                    ->weight('medium'),
                TextColumn::make('category.name')
                    ->label('Category')
                    ->badge()
                    ->color('bronze')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('products_count')
                    ->label('Products')
                    ->badge()
                    ->color('gray')
                    ->sortable(),
                ToggleColumn::make('is_published')
                    ->label('Published')
                    ->sortable()
                    ->disabled(fn () => ! auth('admin')->user()?->hasAdminPermission('subcategories.update')),
            ])
            ->filters([
                SelectFilter::make('category')
                    ->relationship('category', 'name')
                    ->searchable()
                    ->preload()
                    ->multiple(),
                TernaryFilter::make('is_published')
                    ->label('Visibility')
                    ->trueLabel('Published')
                    ->falseLabel('Hidden'),
            ])
            ->recordActions([
                EditAction::make()->hiddenLabel()->tooltip('Edit')->modalWidth('md'),
                DeleteAction::make()->hiddenLabel()->tooltip('Delete')->modalDescription(fn (Subcategory $record) => $record->products_count
                    ? "{$record->products_count} product(s) use this subcategory. They will be kept in their category, without a subcategory."
                    : 'Are you sure you want to delete this subcategory?'),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ])
            ->emptyStateHeading('No subcategories yet')
            ->emptyStateDescription('Split a category into smaller groups, e.g. Bracelets → Charm, Beaded, Leather.');
    }
}
