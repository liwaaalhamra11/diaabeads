<?php

namespace App\Filament\Resources\Admins\Tables;

use App\Models\Admin;
use App\Support\AdminPermissions;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class AdminsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('name')
                    ->searchable()
                    ->sortable()
                    ->weight('medium')
                    ->description(fn (Admin $record) => $record->email),
                TextColumn::make('roles.name')
                    ->label('Roles')
                    ->badge()
                    ->color(fn (string $state) => $state === Admin::SUPER_ADMIN_ROLE ? 'bronze' : 'gray')
                    ->placeholder('No role'),
                ToggleColumn::make('is_active')
                    ->label('Active')
                    ->disabled(fn (Admin $record) => $record->is(auth('admin')->user())
                        || ! auth('admin')->user()->can('update', $record)),
                TextColumn::make('last_login_at')
                    ->label('Last sign-in')
                    ->since()
                    ->dateTimeTooltip()
                    ->sortable()
                    ->placeholder('Never'),
                TextColumn::make('created_at')
                    ->label('Added')
                    ->date()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('roles')
                    ->relationship('roles', 'name', fn (Builder $query) => $query->where('guard_name', AdminPermissions::GUARD))
                    ->multiple()
                    ->preload(),
                TernaryFilter::make('is_active')
                    ->label('Status')
                    ->trueLabel('Active')
                    ->falseLabel('Inactive'),
            ])
            ->recordActions([
                EditAction::make()->hiddenLabel()->tooltip('Edit'),
                DeleteAction::make()->hiddenLabel()->tooltip('Delete'),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()->authorizeIndividualRecords('delete'),
                ]),
            ])
            ->emptyStateHeading('No admins yet')
            ->emptyStateDescription('Add your first admin to share the workload.');
    }
}
