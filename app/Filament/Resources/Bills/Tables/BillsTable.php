<?php

namespace App\Filament\Resources\Bills\Tables;

use App\Enums\BillStatus;
use App\Enums\BillType;
use App\Enums\PaymentMethod;
use App\Filament\Resources\Bills\Actions\DownloadBillPdfAction;
use App\Filament\Resources\Bills\Actions\MarkAsPaidAction;
use App\Models\Bill;
use App\Support\Money;
use Carbon\Carbon;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\RestoreAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DatePicker;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;

class BillsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->withCount('items'))
            ->defaultSort('bill_date', 'desc')
            ->columns([
                TextColumn::make('number')
                    ->label('Bill')
                    ->searchable()
                    ->sortable()
                    ->fontFamily('mono')
                    ->weight('medium')
                    ->copyable()
                    ->copyMessage('Bill number copied'),
                TextColumn::make('bill_date')
                    ->label('Date')
                    ->date('d M Y')
                    ->sortable(),
                TextColumn::make('type')
                    ->badge()
                    ->sortable(),
                TextColumn::make('party_name')
                    ->label('Customer / Paid to')
                    ->searchable(['party_name', 'party_phone', 'description'])
                    ->weight('medium')
                    ->wrap()
                    ->description(fn (Bill $record) => $record->description
                        ? Str::limit($record->description, 50)
                        : $record->party_phone),
                TextColumn::make('items_count')
                    ->label('Lines')
                    ->alignCenter()
                    ->color('gray')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('total')
                    ->label('Total')
                    ->sortable()
                    ->alignEnd()
                    ->weight('medium')
                    ->formatStateUsing(fn (Bill $record, $state) => ($record->type === BillType::In ? '+ ' : '− ').Money::format($state))
                    ->color(fn (Bill $record) => $record->type === BillType::In ? null : 'bronze'),
                TextColumn::make('status')
                    ->badge()
                    ->sortable(),
                // One badge beside the status instead of a second line under it:
                // how it was paid, or when it is due.
                TextColumn::make('payment')
                    ->label('Payment')
                    ->badge()
                    ->state(fn (Bill $record) => match (true) {
                        $record->status === BillStatus::Paid => $record->payment_method?->getLabel() ?? 'Paid',
                        $record->isOverdue() => 'Overdue · '.$record->due_date->format('d M'),
                        $record->due_date !== null => 'Due '.$record->due_date->format('d M Y'),
                        default => 'No due date',
                    })
                    ->icon(fn (Bill $record) => match (true) {
                        $record->status === BillStatus::Paid => $record->payment_method?->getIcon(),
                        $record->isOverdue() => Heroicon::OutlinedExclamationTriangle,
                        default => Heroicon::OutlinedCalendar,
                    })
                    ->color(fn (Bill $record) => match (true) {
                        $record->status === BillStatus::Paid => 'gray',
                        $record->isOverdue() => 'danger',
                        $record->due_date !== null => 'bronze',
                        default => 'gray',
                    }),
                TextColumn::make('creator.name')
                    ->label('Created by')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('type')
                    ->options(BillType::class),
                SelectFilter::make('status')
                    ->options(BillStatus::class),
                SelectFilter::make('payment_method')
                    ->label('Paid by')
                    ->options(PaymentMethod::class),
                Filter::make('overdue')
                    ->label('Overdue only')
                    ->toggle()
                    ->query(fn (Builder $query) => $query->pending()->whereNotNull('due_date')->whereDate('due_date', '<', today())),
                Filter::make('bill_date')
                    ->schema([
                        DatePicker::make('from')->native(false),
                        DatePicker::make('until')->native(false),
                    ])
                    ->columns(2)
                    ->query(fn (Builder $query, array $data) => $query
                        ->when($data['from'] ?? null, fn (Builder $q, $date) => $q->whereDate('bill_date', '>=', $date))
                        ->when($data['until'] ?? null, fn (Builder $q, $date) => $q->whereDate('bill_date', '<=', $date)))
                    ->indicateUsing(fn (array $data) => array_filter([
                        ($data['from'] ?? null) ? 'From '.Carbon::parse($data['from'])->format('d M Y') : null,
                        ($data['until'] ?? null) ? 'Until '.Carbon::parse($data['until'])->format('d M Y') : null,
                    ])),
                TrashedFilter::make(),
            ])
            ->recordActions([
                ViewAction::make()->hiddenLabel()->tooltip('View'),
                DownloadBillPdfAction::make()->hiddenLabel(),
                MarkAsPaidAction::make()
                    ->hiddenLabel()
                    ->tooltip('Mark as paid'),
                EditAction::make()->hiddenLabel()->tooltip('Edit'),
                DeleteAction::make()
                    ->hiddenLabel()
                    ->tooltip('Delete')
                    ->modalDescription(fn (Bill $record) => $record->type === BillType::In
                        ? 'The bill goes to the trash and its products are put back in stock. You can restore it later.'
                        : 'The bill goes to the trash. You can restore it later.'),
                RestoreAction::make()->hiddenLabel()->tooltip('Restore'),
                ForceDeleteAction::make()->hiddenLabel()->tooltip('Delete forever'),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                    RestoreBulkAction::make(),
                    ForceDeleteBulkAction::make(),
                ]),
            ])
            ->recordClasses(fn (Bill $record) => $record->isOverdue() ? 'bill-overdue' : null)
            ->emptyStateHeading('No bills yet')
            ->emptyStateDescription('Record a sale (money in) or an expense (money out).');
    }
}
