<?php

namespace App\Filament\Resources\Admins\Pages;

use App\Filament\Resources\Admins\AdminResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Filament\Support\Icons\Heroicon;

class ListAdmins extends ListRecords
{
    protected static string $resource = AdminResource::class;

    protected ?string $subheading = 'People who can sign in to this admin panel.';

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->label('New admin')
                ->icon(Heroicon::OutlinedUserPlus),
        ];
    }
}
