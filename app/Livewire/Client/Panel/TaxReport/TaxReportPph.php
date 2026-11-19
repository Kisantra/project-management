<?php

namespace App\Livewire\Client\Panel\TaxReport;

use App\Models\IncomeTax;
use App\Models\TaxReport;
use App\Support\ClientPortal\ClientContext;
use App\Support\TaxPeriod;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Livewire\Attributes\On;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;

class TaxReportPph extends Component
{
    // Properties for tax report data
    #[Locked]
    public $taxReportId;
    #[Locked]
    public $taxReport;
    public $hasAccess = false;
    
    // PPh calculations
    public $pph21Total = 0;
    public $pph21Count = 0;
    public $pph21Bruto = 0;
    
    public $pph23Total = 0;
    public $pph23Count = 0;
    public $pph23Bruto = 0;
    
    public $pph42Total = 0;
    public $pph42Count = 0;
    public $pph42Bruto = 0;
    
    public $totalPph = 0;
    public $totalBuktiPotong = 0;
    public $totalDpp = 0;
    
    // Report status
    public $reportStatus = 'Belum Lapor';
    public $reportedAt = null;
    
    /**
     * Mount the component with tax report ID
     */
    public function mount($taxReportId = null)
    {
        $this->taxReportId = $taxReportId;
        
        // Verify access and load data
        $this->verifyAccess();
        
        if ($this->hasAccess) {
            $this->loadTaxReportData();
            $this->calculatePph();
        }
    }
    
    /**
     * Verify user has access to the client
     */
    protected function verifyAccess()
    {
        if (!$this->taxReportId) {
            $this->hasAccess = false;
            return;
        }
        
        $taxReport = TaxReport::select('id', 'client_id')->find($this->taxReportId);
        
        if (!$taxReport) {
            $this->hasAccess = false;
            return;
        }
        
        $this->hasAccess = app(ClientContext::class)->owns($taxReport->client_id);
    }
    
    /**
     * Load tax report data with minimal queries
     */
    protected function loadTaxReportData()
    {
        if ($this->taxReportId) {
            $this->taxReport = TaxReport::with([
                'client:id,name',
                'taxCalculationSummaries' => function($query) {
                    $query->where('tax_type', 'pph')
                        ->select('id', 'tax_report_id', 'tax_type', 'report_status', 'reported_at');
                }
            ])
            ->select('id', 'client_id', 'month', 'year', 'created_at')
            ->find($this->taxReportId);
            
            // Get report status
            if ($this->taxReport) {
                $pphSummary = $this->taxReport->taxCalculationSummaries->first();
                $this->reportStatus = $pphSummary?->report_status ?? 'Belum Lapor';
                $this->reportedAt = $pphSummary?->reported_at;
            }
        }
    }
    
    /**
     * Calculate PPh totals from income_taxes table
     */
    public function calculatePph()
    {
        if (!$this->taxReportId || !$this->hasAccess) {
            return;
        }
        
        // Use cache to avoid repeated calculations (5 minutes cache)
        $cacheKey = "client_pph_calculations_v2_{$this->taxReportId}";

        $query = function () {
            return DB::table('income_taxes')
                ->where('tax_report_id', $this->taxReportId)
                ->select([
                    // PPh 21
                    DB::raw('SUM(CASE WHEN jenis_pajak = "Pasal 21" THEN pajak_penghasilan ELSE 0 END) as pph21_total'),
                    DB::raw('COUNT(CASE WHEN jenis_pajak = "Pasal 21" THEN 1 END) as pph21_count'),
                    DB::raw('SUM(CASE WHEN jenis_pajak = "Pasal 21" THEN dasar_pengenaan_pajak ELSE 0 END) as pph21_bruto'),

                    // PPh 23
                    DB::raw('SUM(CASE WHEN jenis_pajak = "Pasal 23" THEN pajak_penghasilan ELSE 0 END) as pph23_total'),
                    DB::raw('COUNT(CASE WHEN jenis_pajak = "Pasal 23" THEN 1 END) as pph23_count'),
                    DB::raw('SUM(CASE WHEN jenis_pajak = "Pasal 23" THEN dasar_pengenaan_pajak ELSE 0 END) as pph23_bruto'),

                    // PPh 4(2)
                    DB::raw('SUM(CASE WHEN jenis_pajak IN ("Pasal 4(2)", "Pasal 4 ayat 2") THEN pajak_penghasilan ELSE 0 END) as pph42_total'),
                    DB::raw('COUNT(CASE WHEN jenis_pajak IN ("Pasal 4(2)", "Pasal 4 ayat 2") THEN 1 END) as pph42_count'),
                    DB::raw('SUM(CASE WHEN jenis_pajak IN ("Pasal 4(2)", "Pasal 4 ayat 2") THEN dasar_pengenaan_pajak ELSE 0 END) as pph42_bruto'),

                    // Totals
                    DB::raw('SUM(pajak_penghasilan) as total_pph'),
                    DB::raw('SUM(dasar_pengenaan_pajak) as total_dpp'),
                    DB::raw('COUNT(*) as total_bukti_potong')
                ])
                ->first();
        };

        try {
            $calculations = Cache::remember($cacheKey, 300, $query);
        } catch (\Exception $e) {
            $calculations = $query();
        }
        
        // Assign to component properties
        $this->pph21Total = $calculations->pph21_total ?? 0;
        $this->pph21Count = $calculations->pph21_count ?? 0;
        $this->pph21Bruto = $calculations->pph21_bruto ?? 0;
        
        $this->pph23Total = $calculations->pph23_total ?? 0;
        $this->pph23Count = $calculations->pph23_count ?? 0;
        $this->pph23Bruto = $calculations->pph23_bruto ?? 0;
        
        $this->pph42Total = $calculations->pph42_total ?? 0;
        $this->pph42Count = $calculations->pph42_count ?? 0;
        $this->pph42Bruto = $calculations->pph42_bruto ?? 0;
        
        $this->totalPph = $calculations->total_pph ?? 0;
        $this->totalDpp = $calculations->total_dpp ?? 0;
        $this->totalBuktiPotong = $calculations->total_bukti_potong ?? 0;
    }
    
    /**
     * Handle tax report change event from parent component
     */
    #[On('taxReportChanged')]
    public function handleTaxReportChange($taxReportId)
    {
        $this->taxReportId = $taxReportId;
        $this->verifyAccess();
        
        if ($this->hasAccess) {
            // Clear cache for new data
            Cache::forget("client_pph_calculations_v2_{$this->taxReportId}");
            $this->loadTaxReportData();
            $this->calculatePph();
        }
    }
    
    /**
     * Get formatted period name
     */
    public function getPeriodNameProperty()
    {
        if (!$this->taxReport) {
            return '-';
        }

        return TaxPeriod::label($this->taxReport->month, $this->taxReport->year ?: $this->taxReport->created_at?->year);
    }
    
    /**
     * Get client name
     */
    public function getClientNameProperty()
    {
        return $this->taxReport->client->name ?? '-';
    }

    /**
     * Render the component
     */
    public function render()
    {
        return view('livewire.client.panel.tax-report.tax-report-pph');
    }
}
