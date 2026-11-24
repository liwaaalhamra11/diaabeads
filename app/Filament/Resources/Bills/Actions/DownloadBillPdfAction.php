<?php

namespace App\Filament\Resources\Bills\Actions;

use App\Documents\BillPdf;
use App\Models\Bill;
use Filament\Actions\Action;
use Filament\Support\Icons\Heroicon;

class DownloadBillPdfAction
{
    public static function make(): Action
    {
        return Action::make('downloadPdf')
            ->label('PDF')
            ->tooltip('Download as PDF')
            ->icon(Heroicon::OutlinedDocumentArrowDown)
            ->color('gray')
            ->authorize('view')
            ->action(fn (Bill $record) => (new BillPdf($record))->download());
    }
}
