<?php

namespace App\Filament\Resources\Bills;

use App\Filament\Resources\Bills\Pages\CreateBill;
use App\Filament\Resources\Bills\Pages\EditBill;
use App\Filament\Resources\Bills\Pages\ListBills;
use App\Filament\Resources\Bills\Pages\ViewBill;
use App\Filament\Resources\Bills\Schemas\BillForm;
use App\Filament\Resources\Bills\Schemas\BillInfolist;
use App\Filament\Resources\Bills\Tables\BillsTable;
use App\Models\Bill;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use UnitEnum;

class BillResource extends Resource
{
    protected static ?string $model = Bill::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedDocumentText;

    protected static string|BackedEnum|null $activeNavigationIcon = Heroicon::DocumentText;

    protected static string|UnitEnum|null $navigationGroup = 'Finance';

    protected static ?int $navigationSort = 1;

    protected static ?string $recordTitleAttribute = 'number';

    public static function getGloballySearchableAttributes(): array
    {
        return ['number', 'party_name', 'party_phone'];
    }

    public static function getNavigationBadge(): ?string
    {
        $pending = Bill::query()->pending()->count();

        return $pending ? (string) $pending : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'bronze';
    }

    public static function getNavigationBadgeTooltip(): ?string
    {
        return 'Pending bills';
    }

    public static function form(Schema $schema): Schema
    {
        return BillForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return BillInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return BillsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListBills::route('/'),
            'create' => CreateBill::route('/create'),
            'view' => ViewBill::route('/{record}'),
            'edit' => EditBill::route('/{record}/edit'),
        ];
    }

    public static function getRecordRouteBindingEloquentQuery(): Builder
    {
        return parent::getRecordRouteBindingEloquentQuery()
            ->withoutGlobalScopes([
                SoftDeletingScope::class,
            ]);
    }
}
