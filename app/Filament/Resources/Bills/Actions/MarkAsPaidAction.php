<?php

namespace App\Filament\Resources\Bills\Actions;

use App\Enums\BillStatus;
use App\Enums\BillType;
use App\Enums\PaymentMethod;
use App\Models\Bill;
use App\Support\Money;
use Filament\Actions\Action;
use Filament\Forms\Components\ToggleButtons;
use Filament\Support\Icons\Heroicon;

class MarkAsPaidAction
{
    public static function make(): Action
    {
        return Action::make('markPaid')
            ->label('Mark as paid')
            ->icon(Heroicon::OutlinedCheckCircle)
            ->color('bronze')
            ->visible(fn (Bill $record) => $record->status === BillStatus::Pending && ! $record->trashed())
            ->authorize('update')
            ->modalHeading(fn (Bill $record) => "Mark {$record->number} as paid")
            ->modalDescription(fn (Bill $record) => ($record->type === BillType::In ? 'Received from ' : 'Paid to ')
                ."{$record->party_name}: ".Money::format($record->total))
            ->modalWidth('md')
            ->schema([
                ToggleButtons::make('payment_method')
                    ->label('Paid by')
                    ->options(PaymentMethod::class)
                    ->default(PaymentMethod::Cash)
                    ->inline()
                    ->required(),
            ])
            ->action(fn (Bill $record, array $data) => $record->markAsPaid(
                $data['payment_method'] instanceof PaymentMethod ? $data['payment_method'] : PaymentMethod::from($data['payment_method'])
            ))
            ->successNotificationTitle('Bill marked as paid');
    }
}
