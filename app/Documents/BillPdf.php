<?php

namespace App\Documents;

use App\Models\Bill;
use Barryvdh\DomPDF\Facade\Pdf;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * A4 PDF of a single bill: a receipt/invoice for sales (IN) and an expense
 * record for OUT bills. Admin-only notes are never printed.
 */
class BillPdf
{
    public function __construct(private readonly Bill $bill) {}

    public function filename(): string
    {
        return 'bill-'.$this->bill->number.'.pdf';
    }

    public function output(): string
    {
        $this->bill->loadMissing(['items.product']);

        return Pdf::loadView('bills.pdf', ['bill' => $this->bill])
            ->setPaper('a4')
            ->setOption(['defaultFont' => 'DejaVu Sans', 'isRemoteEnabled' => false, 'isFontSubsettingEnabled' => true])
            ->output();
    }

    public function download(): StreamedResponse
    {
        $pdf = $this->output();

        return response()->streamDownload(fn () => print ($pdf), $this->filename(), [
            'Content-Type' => 'application/pdf',
        ]);
    }
}
