<?php

namespace App\Filament\Resources\Bills\Pages;

use App\Enums\BillType;
use App\Filament\Resources\Bills\Actions\DownloadBillPdfAction;
use App\Filament\Resources\Bills\Actions\MarkAsPaidAction;
use App\Filament\Resources\Bills\BillResource;
use App\Models\Bill;
use Filament\Actions\ActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Resources\Pages\ViewRecord;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;

class ViewBill extends ViewRecord
{
    protected static string $resource = BillResource::class;

    public function getTitle(): string|Htmlable
    {
        return "Bill {$this->getRecord()->number}";
    }

    public function getSubheading(): ?string
    {
        /** @var Bill $bill */
        $bill = $this->getRecord();

        return collect([
            $bill->type === BillType::In ? 'Sale' : 'Expense',
            $bill->status->getLabel(),
            ($bill->type === BillType::In ? 'from ' : 'to ').$bill->party_name,
            $bill->trashed() ? 'In trash' : null,
        ])->filter()->join('  ·  ');
    }

    protected function getHeaderActions(): array
    {
        return [
            MarkAsPaidAction::make(),
            DownloadBillPdfAction::make()->label('Download PDF'),
            EditAction::make()
                ->icon(Heroicon::OutlinedPencilSquare)
                ->color('gray'),
            ActionGroup::make([
                DeleteAction::make()
                    ->modalDescription(fn (Bill $record) => $record->type === BillType::In
                        ? 'The bill goes to the trash and its products are put back in stock. You can restore it later.'
                        : 'The bill goes to the trash. You can restore it later.'),
                RestoreAction::make(),
                ForceDeleteAction::make(),
            ]),
        ];
    }
}
