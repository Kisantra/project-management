<?php

namespace App\Livewire\Client\Panel\TaxReport;

use App\Models\Bupot;
use App\Models\TaxReport;
use App\Support\ClientPortal\ClientContext;
use App\Support\TaxPeriod;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Livewire\Attributes\On;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;

class TaxReportBupot extends Component
{
    // Properties for tax report data
    #[Locked]
    public $taxReportId;
    #[Locked]
    public $taxReport;
    public $hasAccess = false;
    
    // Bupot data
    public $bupots = [];
    public $totalBupotAmount = 0;
    public $bupotCount = 0;
    
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
            $this->loadBupots();
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
                    $query->where('tax_type', 'bupot')
                        ->select('id', 'tax_report_id', 'tax_type', 'report_status', 'reported_at');
                }
            ])
            ->select('id', 'client_id', 'month', 'year', 'created_at')
            ->find($this->taxReportId);
            
            // Get report status
            if ($this->taxReport) {
                $bupotSummary = $this->taxReport->taxCalculationSummaries->first();
                $this->reportStatus = $bupotSummary?->report_status ?? 'Belum Lapor';
                $this->reportedAt = $bupotSummary?->reported_at;
            }
        }
    }
    
    /**
     * Load bupots from the tax report
     */
    public function loadBupots()
    {
        if (!$this->taxReportId || !$this->hasAccess) {
            return;
        }
        
        // Use cache to avoid repeated queries (5 minutes cache)
        $cacheKey = "client_bupot_data_{$this->taxReportId}";

        $query = function () {
            return Bupot::where('tax_report_id', $this->taxReportId)
                ->select([
                    'id',
                    'company_name',
                    'npwp',
                    'bupot_type',
                    'pph_type',
                    'tax_period',
                    'dpp',
                    'bupot_amount',
                ])
                ->orderBy('created_at', 'desc')
                ->get()
                ->toArray();
        };

        try {
            $this->bupots = Cache::remember($cacheKey, 300, $query);
        } catch (\Exception $e) {
            $this->bupots = $query();
        }

        
        // Calculate totals
        $this->bupotCount = count($this->bupots);
        $this->totalBupotAmount = collect($this->bupots)->sum('bupot_amount');
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
            Cache::forget("client_bupot_data_{$this->taxReportId}");
            $this->loadTaxReportData();
            $this->loadBupots();
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
        return view('livewire.client.panel.tax-report.tax-report-bupot');
    }
}
