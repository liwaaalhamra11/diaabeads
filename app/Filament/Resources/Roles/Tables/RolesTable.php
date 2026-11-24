<?php

namespace App\Filament\Resources\Roles\Tables;

use App\Models\Admin;
use App\Support\AdminPermissions;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Spatie\Permission\Models\Role;

class RolesTable
{
    public static function configure(Table $table): Table
    {
        $totalPermissions = count(AdminPermissions::all());

        return $table
            ->defaultSort('name')
            ->columns([
                TextColumn::make('name')
                    ->searchable()
                    ->sortable()
                    ->weight('medium')
                    ->icon(fn (Role $record) => $record->name === Admin::SUPER_ADMIN_ROLE ? 'heroicon-m-star' : null)
                    ->iconColor('bronze')
                    ->description(fn (Role $record) => $record->name === Admin::SUPER_ADMIN_ROLE
                        ? 'Built-in role with full access. Cannot be changed.'
                        : null),
                TextColumn::make('permissions_count')
                    ->label('Permissions')
                    ->badge()
                    ->state(fn (Role $record) => $record->name === Admin::SUPER_ADMIN_ROLE
                        ? 'All'
                        : "{$record->permissions_count} / {$totalPermissions}")
                    ->color(fn (Role $record) => $record->name === Admin::SUPER_ADMIN_ROLE ? 'bronze' : 'gray'),
                TextColumn::make('admins')
                    ->label('Admins')
                    ->state(fn (Role $record) => $record->users()->count())
                    ->icon('heroicon-m-users'),
                TextColumn::make('updated_at')
                    ->label('Last updated')
                    ->since()
                    ->dateTimeTooltip()
                    ->sortable(),
            ])
            ->recordActions([
                EditAction::make()->hiddenLabel()->tooltip('Edit'),
                DeleteAction::make()->hiddenLabel()->tooltip('Delete'),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()->authorizeIndividualRecords('delete'),
                ]),
            ]);
    }
}
