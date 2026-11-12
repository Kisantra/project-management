<?php

namespace App\Filament\Resources\TaxReportResource\Pages;

use App\Filament\Resources\TaxReportResource;
use App\Services\TaxReport\ReportOverview;
use Filament\Resources\Pages\ViewRecord;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;

/**
 * Tax report of one client for one period (docs/mockups/tax-report-detail-redesign.html):
 * client header, period navigation, a strip of tax types that doubles as tabs,
 * then the workspace of the selected type. Tab state lives in the URL
 * (?jenis=…&tab=…) so reloads and month switches keep the place.
 */
class ViewTaxReport extends ViewRecord
{
    protected static string $resource = TaxReportResource::class;

    protected static string $view = 'filament.pages.tax-report.index';

    /** Tab open on first render, from ?jenis= (Alpine takes over afterwards). */
    public string $initialType = 'ppn';

    /** Sub-tabs of the PPh Unifikasi workspace, rendered by this page. */
    public const UNIFIKASI_TABS = ['bupot', 'spt'];

    public string $initialUnifikasiTab = 'bupot';

    public function mount(int|string $record): void
    {
        parent::mount($record);

        $requested = (string) request()->query('jenis', 'ppn');
        $this->initialType = in_array($requested, $this->overview->openTabs(), true) ? $requested : 'ppn';

        $tab = (string) request()->query('tab', '');
        $this->initialUnifikasiTab = $this->initialType === 'unifikasi' && in_array($tab, self::UNIFIKASI_TABS, true) ? $tab : 'bupot';
    }

    #[Computed]
    public function overview(): ReportOverview
    {
        return ReportOverview::for($this->record);
    }

    /** Any change that moves a total or a status re-renders the header and the strip. */
    #[On('tax-report-changed')]
    #[On('spt-updated')]
    #[On('compensationUpdated')]
    public function refreshOverview(): void
    {
        $this->record->unsetRelation('taxCalculationSummaries');
        unset($this->overview);
    }

    public function getTitle(): string|Htmlable
    {
        return $this->record->client->name . ' · ' . $this->overview->periodLabel();
    }

    public function getBreadcrumbs(): array
    {
        return [
            TaxReportResource::getUrl('index') => 'Laporan Pajak',
            $this->record->client->name,
            $this->overview->periodLabel(),
        ];
    }

    public function getHeader(): ?View
    {
        return view('filament.pages.tax-report.partials.header', [
            'overview' => $this->overview,
            'breadcrumbs' => $this->getBreadcrumbs(),
            'editUrl' => TaxReportResource::canEdit($this->record)
                ? TaxReportResource::getUrl('edit', ['record' => $this->record])
                : null,
        ]);
    }

    protected function getViewData(): array
    {
        return [
            'overview' => $this->overview,
        ];
    }
}
