<?php

namespace App\Filament\Resources\Roles\Schemas;

use App\Support\AdminPermissions;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Validation\Rules\Unique;

class RoleForm
{
    public static function configure(Schema $schema): Schema
    {
        $permissionSections = collect(AdminPermissions::groups())
            ->map(fn (array $group, string $key) => Section::make($group['label'])
                ->description($group['description'])
                ->compact()
                ->schema([
                    CheckboxList::make("permissions_{$key}")
                        ->hiddenLabel()
                        ->options(AdminPermissions::optionsFor($key))
                        ->columns(2)
                        ->bulkToggleable()
                        ->dehydrated(false),
                ]))
            ->values()
            ->all();

        return $schema
            ->columns(1)
            ->components([
                Section::make('Role')
                    ->icon(Heroicon::OutlinedIdentification)
                    ->schema([
                        TextInput::make('name')
                            ->label('Role name')
                            ->placeholder('e.g. Store Manager')
                            ->required()
                            ->maxLength(255)
                            ->unique(
                                ignoreRecord: true,
                                modifyRuleUsing: fn (Unique $rule) => $rule->where('guard_name', AdminPermissions::GUARD),
                            ),
                    ]),

                Section::make('Permissions')
                    ->description('Tick what admins with this role are allowed to do.')
                    ->icon(Heroicon::OutlinedKey)
                    ->schema([
                        Grid::make(['default' => 1, 'lg' => 2])->schema($permissionSections),
                    ]),
            ]);
    }

    /**
     * Pull the ticked permissions out of the per-group checkbox lists.
     *
     * @return list<string>
     */
    public static function selectedPermissions(array $data): array
    {
        return collect(array_keys(AdminPermissions::groups()))
            ->flatMap(fn (string $key) => $data["permissions_{$key}"] ?? [])
            ->intersect(AdminPermissions::all())
            ->values()
            ->all();
    }

    /** Spread a role's permissions back into the per-group checkbox lists. */
    public static function fillPermissions(array $data, array $permissionNames): array
    {
        foreach (array_keys(AdminPermissions::groups()) as $key) {
            $data["permissions_{$key}"] = array_values(array_filter(
                $permissionNames,
                fn (string $name) => str_starts_with($name, "{$key}."),
            ));
        }

        return $data;
    }
}
