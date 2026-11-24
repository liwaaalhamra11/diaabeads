<?php

namespace App\Filament\Resources\Roles\Pages;

use App\Filament\Resources\Roles\RoleResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Filament\Support\Icons\Heroicon;

class ListRoles extends ListRecords
{
    protected static string $resource = RoleResource::class;

    protected ?string $subheading = 'Group permissions into roles, then give roles to admins.';

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->label('New role')
                ->icon(Heroicon::OutlinedPlus),
        ];
    }
}
