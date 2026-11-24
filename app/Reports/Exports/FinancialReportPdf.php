<?php

namespace App\Reports\Exports;

use App\Reports\FinancialReport;
use Barryvdh\DomPDF\Facade\Pdf;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** A4 PDF version of a FinancialReport. */
class FinancialReportPdf
{
    public function __construct(private readonly FinancialReport $report) {}

    public function filename(): string
    {
        return 'financial-report-'.$this->report->slug().'.pdf';
    }

    public function output(): string
    {
        return Pdf::loadView('reports.financial-pdf', ['report' => $this->report])
            ->setPaper('a4')
            // Embed only the characters used: keeps the file small.
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
