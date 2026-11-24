<?php

namespace App\Filament\Resources\Admins\Schemas;

use App\Models\Admin;
use App\Support\AdminPermissions;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Builder;
use Spatie\Permission\Models\Role;

class AdminForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(3)
            ->components([
                Section::make('Profile')
                    ->description('Basic details used to sign in to the admin panel.')
                    ->icon(Heroicon::OutlinedUserCircle)
                    ->columns(2)
                    ->columnSpan(2)
                    ->schema([
                        TextInput::make('name')
                            ->required()
                            ->maxLength(255)
                            ->prefixIcon(Heroicon::OutlinedUser),
                        TextInput::make('email')
                            ->label('Email address')
                            ->email()
                            ->required()
                            ->maxLength(255)
                            ->unique(ignoreRecord: true)
                            ->prefixIcon(Heroicon::OutlinedEnvelope),
                        TextInput::make('password')
                            ->password()
                            ->revealable()
                            ->rule('min:8')
                            ->required(fn (string $operation) => $operation === 'create')
                            ->dehydrated(fn (?string $state) => filled($state))
                            ->confirmed()
                            ->helperText(fn (string $operation) => $operation === 'edit'
                                ? 'Leave blank to keep the current password.'
                                : 'At least 8 characters.'),
                        TextInput::make('password_confirmation')
                            ->label('Confirm password')
                            ->password()
                            ->revealable()
                            ->required(fn (string $operation) => $operation === 'create')
                            ->dehydrated(false),
                    ]),

                Section::make('Access')
                    ->description('What this admin is allowed to do.')
                    ->icon(Heroicon::OutlinedShieldCheck)
                    ->columnSpan(1)
                    ->schema([
                        Select::make('roles')
                            ->relationship(
                                'roles',
                                'name',
                                fn (Builder $query) => $query
                                    ->where('guard_name', AdminPermissions::GUARD)
                                    // Only Super Admins may hand out the Super Admin role.
                                    ->when(
                                        ! static::currentAdminIsSuperAdmin(),
                                        fn (Builder $query) => $query->where('name', '!=', Admin::SUPER_ADMIN_ROLE),
                                    ),
                            )
                            ->multiple()
                            ->preload()
                            ->searchable()
                            ->helperText('Super Admin has full access to everything.')
                            // Server-side guard: the option list above is only a UI convenience.
                            ->rule(fn () => function (string $attribute, mixed $value, \Closure $fail) {
                                if (static::currentAdminIsSuperAdmin()) {
                                    return;
                                }

                                $grantsSuperAdmin = Role::query()
                                    ->whereKey((array) $value)
                                    ->where('name', Admin::SUPER_ADMIN_ROLE)
                                    ->exists();

                                if ($grantsSuperAdmin) {
                                    $fail('Only a Super Admin can give the Super Admin role.');
                                }
                            }),
                        Toggle::make('is_active')
                            ->label('Active')
                            ->helperText('Inactive admins cannot sign in.')
                            ->default(true)
                            ->disabled(fn (?Admin $record) => $record?->is(auth('admin')->user())),
                    ]),
            ]);
    }

    protected static function currentAdminIsSuperAdmin(): bool
    {
        return (bool) auth('admin')->user()?->isSuperAdmin();
    }
}
