<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\Admins\AdminResource;
use App\Filament\Resources\Roles\RoleResource;
use App\Models\Admin;
use App\Support\AdminPermissions;
use Filament\Support\Icons\Heroicon;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Spatie\Permission\Models\Role;

class AccessOverview extends StatsOverviewWidget
{
    protected static ?int $sort = 2;

    protected function getStats(): array
    {
        $total = Admin::count();
        $active = Admin::where('is_active', true)->count();
        $recent = Admin::where('last_login_at', '>=', now()->subDays(7))->count();

        return [
            Stat::make('Admins', $total)
                ->description("{$active} active")
                ->descriptionIcon(Heroicon::OutlinedCheckCircle)
                ->icon(Heroicon::OutlinedUsers)
                ->color('bronze')
                ->url(AdminResource::canViewAny() ? AdminResource::getUrl() : null),
            Stat::make('Roles', Role::where('guard_name', AdminPermissions::GUARD)->count())
                ->description(count(AdminPermissions::all()).' permissions available')
                ->descriptionIcon(Heroicon::OutlinedKey)
                ->icon(Heroicon::OutlinedShieldCheck)
                ->url(RoleResource::canViewAny() ? RoleResource::getUrl() : null),
            Stat::make('Signed in this week', $recent)
                ->description('Admins active in the last 7 days')
                ->descriptionIcon(Heroicon::OutlinedClock)
                ->icon(Heroicon::OutlinedArrowRightEndOnRectangle),
        ];
    }
}
