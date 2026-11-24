<?php

namespace App\Filament\Pages;

use App\Reports\Exports\FinancialReportExcel;
use App\Reports\Exports\FinancialReportPdf;
use App\Reports\FinancialReport;
use BackedEnum;
use Carbon\CarbonImmutable;
use Filament\Actions\Action;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Url;
use Symfony\Component\HttpFoundation\StreamedResponse;
use UnitEnum;

class FinancialReports extends Page
{
    protected string $view = 'filament.pages.financial-reports';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedChartBar;

    protected static string|BackedEnum|null $activeNavigationIcon = Heroicon::ChartBar;

    protected static string|UnitEnum|null $navigationGroup = 'Finance';

    protected static ?string $navigationLabel = 'Reports';

    protected static ?int $navigationSort = 2;

    protected static ?string $slug = 'reports';

    protected static ?string $title = 'Financial reports';

    /** daily | monthly */
    #[Url]
    public string $period = FinancialReport::MONTHLY;

    /** Y-m-d, used for daily reports. */
    #[Url]
    public string $day = '';

    /** Y-m, used for monthly reports. */
    #[Url]
    public string $month = '';

    public static function canAccess(): bool
    {
        return (bool) auth('admin')->user()?->hasAdminPermission('reports.view');
    }

    public static function canExport(): bool
    {
        return (bool) auth('admin')->user()?->hasAdminPermission('reports.export');
    }

    public function mount(): void
    {
        $this->period = in_array($this->period, [FinancialReport::DAILY, FinancialReport::MONTHLY], true)
            ? $this->period
            : FinancialReport::MONTHLY;
        $this->day = $this->validDate($this->day, 'Y-m-d') ?? today()->format('Y-m-d');
        $this->month = $this->validDate($this->month, 'Y-m') ?? today()->format('Y-m');
    }

    #[Computed]
    public function report(): FinancialReport
    {
        return $this->period === FinancialReport::DAILY
            ? FinancialReport::daily($this->validDate($this->day, 'Y-m-d') ?? today()->format('Y-m-d'))
            : FinancialReport::monthly(($this->validDate($this->month, 'Y-m') ?? today()->format('Y-m')).'-01');
    }

    public function setPeriod(string $period): void
    {
        $this->period = $period === FinancialReport::DAILY ? FinancialReport::DAILY : FinancialReport::MONTHLY;
    }

    public function previousPeriod(): void
    {
        $this->goTo($this->report->previous());
    }

    public function nextPeriod(): void
    {
        $this->goTo($this->report->next());
    }

    public function currentPeriod(): void
    {
        $this->day = today()->format('Y-m-d');
        $this->month = today()->format('Y-m');
    }

    /** Open the daily report for one day of the monthly breakdown. */
    public function openDay(string $date): void
    {
        if ($date = $this->validDate($date, 'Y-m-d')) {
            $this->day = $date;
            $this->period = FinancialReport::DAILY;
        }
    }

    public function isCurrentPeriod(): bool
    {
        return $this->report->start->lte(today()) && $this->report->end->gte(today());
    }

    protected function goTo(FinancialReport $report): void
    {
        $this->day = $report->start->format('Y-m-d');
        $this->month = $report->start->format('Y-m');
    }

    protected function validDate(?string $value, string $format): ?string
    {
        if (blank($value)) {
            return null;
        }

        try {
            $date = CarbonImmutable::createFromFormat('!'.$format, $value);
        } catch (\Throwable) {
            return null;
        }

        return $date && $date->format($format) === $value ? $value : null;
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('exportExcel')
                ->label('Excel')
                ->icon(Heroicon::OutlinedTableCells)
                ->color('gray')
                ->tooltip('Download this report as an Excel file')
                ->visible(static::canExport())
                ->action(fn (): StreamedResponse => (new FinancialReportExcel($this->report))->download()),
            Action::make('exportPdf')
                ->label('PDF')
                ->icon(Heroicon::OutlinedDocumentArrowDown)
                ->tooltip('Download this report as a PDF')
                ->visible(static::canExport())
                ->action(fn (): StreamedResponse => (new FinancialReportPdf($this->report))->download()),
        ];
    }
}
