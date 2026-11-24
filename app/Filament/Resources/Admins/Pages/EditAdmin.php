<?php

namespace App\Filament\Resources\Admins\Pages;

use App\Filament\Resources\Admins\AdminResource;
use App\Models\Admin;
use Filament\Actions\DeleteAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Spatie\Permission\Models\Role;

class EditAdmin extends EditRecord
{
    protected static string $resource = AdminResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }

    /**
     * Never allow the last Super Admin to lose the role, otherwise nobody
     * could manage the panel any more.
     */
    protected function beforeSave(): void
    {
        /** @var Admin $admin */
        $admin = $this->getRecord();

        if (! $admin->isSuperAdmin()) {
            return;
        }

        $superAdminRoleId = Role::findByName(Admin::SUPER_ADMIN_ROLE, 'admin')->getKey();
        $keepsRole = in_array($superAdminRoleId, array_map('intval', $this->data['roles'] ?? []), true);

        if (! $keepsRole && Admin::role(Admin::SUPER_ADMIN_ROLE)->count() <= 1) {
            Notification::make()
                ->title('At least one Super Admin is required')
                ->body('Give another admin the Super Admin role before removing it from this one.')
                ->danger()
                ->send();

            $this->halt();
        }
    }
}
