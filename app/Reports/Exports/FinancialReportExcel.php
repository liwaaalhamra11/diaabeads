<?php

namespace App\Reports\Exports;

use App\Reports\FinancialReport;
use App\Support\Money;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Builds the .xlsx version of a FinancialReport:
 * Summary · Breakdown · Bills · Products · Expenses.
 */
class FinancialReportExcel
{
    private const BLACK = '0A0A0A';

    private const BRONZE = '9B7346';

    private const LIGHT = 'F5F5F5';

    private const RED = 'B91C1C';

    private string $money;

    public function __construct(private readonly FinancialReport $report)
    {
        $symbol = trim(preg_replace('/[\d.,\s]/u', '', Money::format(0)) ?: Money::currency());
        // Negative amounts in red with a minus sign.
        $this->money = "\"{$symbol}\"#,##0.00;[Red]-\"{$symbol}\"#,##0.00";
    }

    public function filename(): string
    {
        return 'financial-report-'.$this->report->slug().'.xlsx';
    }

    public function download(): StreamedResponse
    {
        $spreadsheet = $this->build();

        return response()->streamDownload(function () use ($spreadsheet) {
            (new Xlsx($spreadsheet))->save('php://output');
        }, $this->filename(), [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    public function build(): Spreadsheet
    {
        $book = new Spreadsheet;
        $book->getProperties()
            ->setCreator(config('app.name'))
            ->setTitle($this->report->title().' — '.$this->report->label());
        $book->getDefaultStyle()->getFont()->setName('Arial')->setSize(10);

        $this->summarySheet($book->getActiveSheet());
        $this->breakdownSheet($book->createSheet());
        $this->billsSheet($book->createSheet());
        $this->productsSheet($book->createSheet());
        $this->expensesSheet($book->createSheet());

        $book->setActiveSheetIndex(0);

        return $book;
    }

    // ------------------------------------------------------------------

    private function summarySheet(Worksheet $sheet): void
    {
        $sheet->setTitle('Summary');
        $s = $this->report->summary();
        $out = $this->report->outstanding();

        $sheet->setCellValue('A1', config('app.name'));
        $sheet->setCellValue('A2', $this->report->title());
        $sheet->setCellValue('A3', $this->report->label());
        $sheet->setCellValue('A4', 'Generated '.now()->format('d M Y, H:i').' · amounts in '.Money::currency());
        $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(16);
        $sheet->getStyle('A2')->getFont()->setBold(true)->setSize(12)->getColor()->setRGB(self::BRONZE);
        $sheet->getStyle('A3:A4')->getFont()->getColor()->setRGB('6B6B6B');

        // Result banner.
        $sheet->setCellValue('A6', 'Result');
        $sheet->setCellValue('B6', FinancialReport::resultLabel($s['net']));
        $sheet->setCellValue('C6', $s['net']);
        $sheet->getStyle('A6:C6')->applyFromArray([
            'font' => ['bold' => true, 'size' => 12, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => $s['net'] < 0 ? self::RED : self::BLACK]],
        ]);
        $sheet->getStyle('C6')->getNumberFormat()->setFormatCode(str_replace(';[Red]', ';', $this->money));

        $rows = [
            ['INCOME (sales)', null, null],
            ['Total income', $s['income'], "{$s['income_count']} bill(s)"],
            ['  Received (paid)', $s['income_paid'], null],
            ['  Pending (customers will pay)', $s['income_pending'], null],
            ['  Discounts given', $s['discounts'], null],
            [null, null, null],
            ['EXPENSES', null, null],
            ['Total expenses', $s['expenses'], "{$s['expenses_count']} bill(s)"],
            ['  Paid', $s['expenses_paid'], null],
            ['  Pending (you will pay)', $s['expenses_pending'], null],
            [null, null, null],
            ['RESULT', null, null],
            ['Net result (income − expenses)', $s['net'], FinancialReport::resultLabel($s['net'])],
            ['Cash flow (received − paid)', $s['cash_flow'], FinancialReport::resultLabel($s['cash_flow'])],
            ['Cost of products sold', $s['cost_of_goods'], "{$s['items_sold']} piece(s)"],
            ['Profit on products (income − cost)', $s['gross_profit'], $s['gross_margin'] !== null ? $s['gross_margin'].'% margin' : null],
            [null, null, null],
            ['STILL UNPAID AT END OF PERIOD (all dates)', null, null],
            ['Customers owe you', $out['customers_owe'], "{$out['customers_owe_count']} bill(s)"],
            ['You owe', $out['you_owe'], "{$out['you_owe_count']} bill(s)"],
        ];

        $r = 8;
        foreach ($rows as [$label, $value, $note]) {
            $sheet->setCellValue("A{$r}", $label);
            if ($value !== null) {
                $sheet->setCellValue("B{$r}", $value);
                $sheet->getStyle("B{$r}")->getNumberFormat()->setFormatCode($this->money);
            }
            $sheet->setCellValue("C{$r}", $note);

            if ($label !== null && $value === null && str_starts_with((string) $label, strtoupper((string) $label))) {
                $sheet->getStyle("A{$r}:C{$r}")->applyFromArray([
                    'font' => ['bold' => true, 'color' => ['rgb' => self::BRONZE]],
                    'borders' => ['bottom' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => self::BRONZE]]],
                ]);
            } elseif ($label !== null && ! str_starts_with((string) $label, '  ')) {
                $sheet->getStyle("A{$r}:B{$r}")->getFont()->setBold(true);
            }
            $r++;
        }

        // Comparison with previous period.
        $r += 1;
        $sheet->setCellValue("A{$r}", 'COMPARED WITH '.strtoupper($this->report->previous()->label()));
        $sheet->getStyle("A{$r}:D{$r}")->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => self::BRONZE]],
            'borders' => ['bottom' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => self::BRONZE]]],
        ]);
        $r++;
        $this->header($sheet, $r, ['', 'This period', 'Previous', 'Change']);
        foreach ($this->report->comparison() as $row) {
            $r++;
            $sheet->fromArray([$row['label'], $row['current'], $row['previous'], $row['change'] !== null ? $row['change'] / 100 : '—'], null, "A{$r}");
            $sheet->getStyle("B{$r}:C{$r}")->getNumberFormat()->setFormatCode($this->money);
            $sheet->getStyle("D{$r}")->getNumberFormat()->setFormatCode('+0.0%;[Red]-0.0%;0.0%');
        }

        // Payment methods.
        $r += 2;
        $sheet->setCellValue("A{$r}", 'PAID AMOUNTS BY PAYMENT METHOD');
        $sheet->getStyle("A{$r}:D{$r}")->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => self::BRONZE]],
            'borders' => ['bottom' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => self::BRONZE]]],
        ]);
        $r++;
        $this->header($sheet, $r, ['Method', 'Received', 'Paid out', 'Net']);
        foreach ($this->report->paymentMethods() as $row) {
            $r++;
            $sheet->fromArray([$row['method']->getLabel(), $row['income'], $row['expenses'], $row['net']], null, "A{$r}");
            $sheet->getStyle("B{$r}:D{$r}")->getNumberFormat()->setFormatCode($this->money);
        }

        $sheet->getColumnDimension('A')->setWidth(44);
        foreach (['B', 'C', 'D'] as $col) {
            $sheet->getColumnDimension($col)->setWidth(20);
        }
        $sheet->getStyle('B:D')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
        $sheet->getStyle('A1:A6')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
    }

    private function breakdownSheet(Worksheet $sheet): void
    {
        if ($this->report->isDaily()) {
            $sheet->setTitle('Breakdown');
            $this->header($sheet, 1, ['Time', 'Bill', 'Type', 'Customer / Paid to', 'In', 'Out', 'Running balance']);
            $running = 0;
            $r = 1;
            foreach ($this->report->bills() as $bill) {
                $r++;
                $in = $bill->type->value === 'in' ? (float) $bill->total : 0;
                $out = $bill->type->value === 'out' ? (float) $bill->total : 0;
                $running += $in - $out;
                $sheet->fromArray([$bill->created_at?->format('H:i'), $bill->number, $bill->type->getLabel(), $bill->party_name, $in ?: null, $out ?: null, $running], null, "A{$r}");
            }
            $this->moneyColumns($sheet, ['E', 'F', 'G'], 2, $r);
            $this->totalsRow($sheet, $r + 1, 'A', ['E', 'F'], 2, $r);
            $this->widths($sheet, ['A' => 10, 'B' => 14, 'C' => 16, 'D' => 34, 'E' => 16, 'F' => 16, 'G' => 18]);

            return;
        }

        $sheet->setTitle('Daily breakdown');
        $this->header($sheet, 1, ['Date', 'Day', 'Bills', 'Income', 'Expenses', 'Net', 'Running balance']);
        $r = 1;
        foreach ($this->report->dailyRows() as $row) {
            $r++;
            $sheet->fromArray([$row['date']->format('Y-m-d'), $row['date']->format('l'), $row['bills'] ?: null, $row['income'], $row['expenses'], $row['net'], $row['running']], null, "A{$r}");
            if ($row['bills'] === 0) {
                $sheet->getStyle("A{$r}:G{$r}")->getFont()->getColor()->setRGB('A3A3A3');
            }
        }
        $this->moneyColumns($sheet, ['D', 'E', 'F', 'G'], 2, $r);
        $this->totalsRow($sheet, $r + 1, 'A', ['C', 'D', 'E', 'F'], 2, $r);
        $sheet->getStyle('C2:C'.($r + 1))->getNumberFormat()->setFormatCode('0');
        $this->widths($sheet, ['A' => 13, 'B' => 12, 'C' => 8, 'D' => 16, 'E' => 16, 'F' => 16, 'G' => 18]);
    }

    private function billsSheet(Worksheet $sheet): void
    {
        $sheet->setTitle('Bills');
        $this->header($sheet, 1, ['Date', 'Bill', 'Type', 'Customer / Paid to', 'Phone', 'Lines', 'Subtotal', 'Discount', 'Total', 'Status', 'Paid by', 'Due date', 'Description', 'Notes']);
        $r = 1;
        foreach ($this->report->bills() as $bill) {
            $r++;
            $sheet->fromArray([
                $bill->bill_date->format('Y-m-d'), $bill->number, $bill->type->getLabel(), $bill->party_name, $bill->party_phone,
                $bill->items_count, (float) $bill->subtotal, (float) $bill->discount, (float) $bill->total,
                $bill->status->getLabel(), $bill->payment_method?->getLabel(), $bill->due_date?->format('Y-m-d'),
                $bill->description, $bill->notes,
            ], null, "A{$r}");
            $sheet->getCell("B{$r}")->getStyle()->getFont()->setName('Courier New');
        }
        $this->moneyColumns($sheet, ['G', 'H', 'I'], 2, $r);
        $this->widths($sheet, ['A' => 12, 'B' => 13, 'C' => 15, 'D' => 28, 'E' => 15, 'F' => 7, 'G' => 13, 'H' => 12, 'I' => 13, 'J' => 10, 'K' => 10, 'L' => 12, 'M' => 40, 'N' => 40]);
        $sheet->getStyle("M2:N{$r}")->getAlignment()->setWrapText(true);
        $sheet->setAutoFilter("A1:N{$r}");
    }

    private function productsSheet(Worksheet $sheet): void
    {
        $sheet->setTitle('Products sold');
        $this->header($sheet, 1, ['Product', 'SKU', 'Pieces', 'Revenue', 'Cost', 'Profit']);
        $r = 1;
        foreach ($this->report->topProducts(null) as $row) {
            $r++;
            $sheet->fromArray([$row['name'], $row['sku'], $row['quantity'], $row['revenue'], $row['cost'], $row['profit']], null, "A{$r}");
        }
        $this->moneyColumns($sheet, ['D', 'E', 'F'], 2, $r);
        $this->totalsRow($sheet, $r + 1, 'A', ['C', 'D', 'E', 'F'], 2, $r);
        $sheet->getStyle('C2:C'.($r + 1))->getNumberFormat()->setFormatCode('0');
        $sheet->setCellValue('A'.($r + 3), 'Revenue is per line, before whole-bill discounts. Cost is the product cost when it was sold.');
        $sheet->getStyle('A'.($r + 3))->getFont()->setItalic(true)->getColor()->setRGB('6B6B6B');
        $this->widths($sheet, ['A' => 40, 'B' => 13, 'C' => 9, 'D' => 15, 'E' => 15, 'F' => 15]);
    }

    private function expensesSheet(Worksheet $sheet): void
    {
        $sheet->setTitle('Expenses');
        $this->header($sheet, 1, ['Paid to', 'Bills', 'Total', 'Paid', 'Pending']);
        $r = 1;
        foreach ($this->report->expensesByParty() as $row) {
            $r++;
            $sheet->fromArray([$row['party'], $row['bills'], $row['total'], $row['paid'], $row['pending']], null, "A{$r}");
        }
        $this->moneyColumns($sheet, ['C', 'D', 'E'], 2, $r);
        $this->totalsRow($sheet, $r + 1, 'A', ['B', 'C', 'D', 'E'], 2, $r);
        $this->widths($sheet, ['A' => 36, 'B' => 8, 'C' => 15, 'D' => 15, 'E' => 15]);
    }

    // ------------------------------------------------------------------

    private function header(Worksheet $sheet, int $row, array $titles): void
    {
        $sheet->fromArray($titles, null, "A{$row}");
        $last = chr(ord('A') + count($titles) - 1);
        $sheet->getStyle("A{$row}:{$last}{$row}")->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => self::BLACK]],
            'borders' => ['bottom' => ['borderStyle' => Border::BORDER_MEDIUM, 'color' => ['rgb' => self::BRONZE]]],
        ]);
        if ($row === 1) {
            $sheet->freezePane('A2');
        }
    }

    private function moneyColumns(Worksheet $sheet, array $columns, int $from, int $to): void
    {
        foreach ($columns as $col) {
            $sheet->getStyle("{$col}{$from}:{$col}".max($from, $to + 1))->getNumberFormat()->setFormatCode($this->money);
        }
    }

    /** A bold TOTAL row with SUM() formulas, so the sheet stays editable. */
    private function totalsRow(Worksheet $sheet, int $row, string $labelColumn, array $sumColumns, int $from, int $to): void
    {
        $sheet->setCellValue("{$labelColumn}{$row}", 'TOTAL');
        foreach ($sumColumns as $col) {
            $sheet->setCellValue("{$col}{$row}", $to >= $from ? "=SUM({$col}{$from}:{$col}{$to})" : 0);
        }
        $last = max(array_merge([$labelColumn], $sumColumns));
        $sheet->getStyle("A{$row}:{$last}{$row}")->applyFromArray([
            'font' => ['bold' => true],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => self::LIGHT]],
            'borders' => ['top' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => self::BLACK]]],
        ]);
    }

    private function widths(Worksheet $sheet, array $widths): void
    {
        foreach ($widths as $col => $width) {
            $sheet->getColumnDimension($col)->setWidth($width);
        }
    }
}
