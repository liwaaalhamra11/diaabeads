<?php

namespace App\Filament\Resources\Roles\Pages;

use App\Filament\Resources\Roles\RoleResource;
use App\Filament\Resources\Roles\Schemas\RoleForm;
use App\Support\AdminPermissions;
use Filament\Resources\Pages\CreateRecord;

class CreateRole extends CreateRecord
{
    protected static string $resource = RoleResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['guard_name'] = AdminPermissions::GUARD;

        return $data;
    }

    protected function afterCreate(): void
    {
        $this->getRecord()->syncPermissions(RoleForm::selectedPermissions($this->data));
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
