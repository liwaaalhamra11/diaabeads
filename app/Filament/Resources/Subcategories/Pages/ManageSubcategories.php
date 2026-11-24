<?php

namespace App\Filament\Resources\Subcategories\Pages;

use App\Filament\Resources\Subcategories\SubcategoryResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;
use Filament\Support\Icons\Heroicon;

class ManageSubcategories extends ManageRecords
{
    protected static string $resource = SubcategoryResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->label('New subcategory')
                ->icon(Heroicon::OutlinedPlus)
                ->modalWidth('md')
                ->createAnother(false),
        ];
    }
}
