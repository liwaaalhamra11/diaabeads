<?php

namespace App\Filament\Resources\Bills\Pages;

use App\Filament\Resources\Bills\BillResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;

class EditBill extends EditRecord
{
    protected static string $resource = BillResource::class;

    public function getTitle(): string
    {
        return "Bill {$this->getRecord()->number}";
    }

    protected function getRedirectUrl(): ?string
    {
        return $this->getResource()::getUrl('view', ['record' => $this->getRecord()]);
    }

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make()->color('gray'),
            DeleteAction::make(),
            ForceDeleteAction::make(),
            RestoreAction::make(),
        ];
    }
}
