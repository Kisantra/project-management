<?php

namespace App\Livewire\TaxReport\Ppn;

use App\Models\TaxReport;
use App\Models\TaxCompensation;
use App\Models\TaxCalculationSummary;
use Livewire\Component;
use Filament\Notifications\Notification;

class TaxReportKompensasi extends Component
{
    public $taxReportId;
    public $taxReport;
    
    // Next month target
    public $nextMonthTarget = null;
    public $nextMonthExists = false;
    
    // Compensation data
    public $receivedCompensations = [];
    public $givenCompensations = [];
    
    // Summary data
    public $kompensasiDiterima = 0;
    public $kompensasiTersedia = 0;
    public $kompensasiTerpakai = 0;
    public $manualKompensasi = 0;
    public $manualKompensasiNotes = '';
    public $saldoSebelumKompensasi = 0;
    public $saldoSetelahKompensasi = 0;
    public $statusSebelum = 'Nihil';
    public $statusSetelah = 'Nihil';
    
    // Form data - Compensate to next month
    public $compensationAmount = 0;
    public $compensationNotes = '';
    public $maxCompensationAmount = 0;
    
    // Manual compensation form
    public $editingManualKompensasi = false;
    public $tempManualKompensasi = 0;
    public $tempManualKompensasiNotes = '';
    
    // Approval/Rejection
    public $selectedCompensationId = null;
    public $rejectionReason = '';

    // Revision
    public $reviseCompensationId = null;
    public $reviseAmount = 0;
    public $reviseNotes = '';

    protected $listeners = [
        'refreshKompensasi' => 'loadData',
        'compensationCreated' => 'loadData',
        'compensationUpdated' => 'loadData',
        'tax-report-changed' => 'loadData',
        // "Ubah" on the PPN calculation ledger.
        'open-manual-kompensasi' => 'openManualKompensasiForm',
    ];

    /**
     * Mount component
     */
    public function mount($taxReportId)
    {
        $this->taxReportId = $taxReportId;
        $this->loadData();
    }

    /**
     * Load all compensation data
     */
    public function loadData()
    {
        $this->taxReport = TaxReport::with([
            'client',
            'ppnSummary',
            'approvedCompensationsReceived.sourceTaxReport.client',
            'approvedCompensationsGiven.targetTaxReport',
            'pendingCompensations.sourceTaxReport.client'
        ])->findOrFail($this->taxReportId);

        $this->loadSummaryData();
        $this->loadNextMonthTarget();
        $this->loadReceivedCompensations();
        $this->loadGivenCompensations();
    }

    /**
     * Load summary calculations
     */
    protected function loadSummaryData()
    {
        $ppnSummary = $this->taxReport->ppnSummary;
        
        if ($ppnSummary) {
            $this->kompensasiDiterima = $ppnSummary->kompensasi_diterima;
            $this->kompensasiTersedia = $ppnSummary->sisa_kompensasi_tersedia;
            $this->kompensasiTerpakai = $ppnSummary->kompensasi_terpakai;
            $this->manualKompensasi = $ppnSummary->manual_kompensasi ?? 0;
            $this->manualKompensasiNotes = $ppnSummary->manual_kompensasi_notes ?? '';
            $this->saldoSebelumKompensasi = $ppnSummary->selisih;
            $this->saldoSetelahKompensasi = $ppnSummary->saldo_final;
            $this->statusSebelum = $ppnSummary->status;
            $this->statusSetelah = $ppnSummary->status_final;
        }
    }

    /**
     * Load next month target (the ONLY period we can compensate to)
     */
    protected function loadNextMonthTarget()
    {
        // Get the next month name based on current report's month
        $currentMonth = $this->taxReport->month;
        $nextMonthName = $this->getNextMonthName($currentMonth);

        // Determine the correct year for the next month
        $nextYear = $this->taxReport->year;
        if ($currentMonth === 'December') {
            $nextYear = (int) $nextYear + 1;
        }

        // Find the next month's tax report
        $this->nextMonthTarget = TaxReport::where('client_id', $this->taxReport->client_id)
            ->where('id', '!=', $this->taxReportId)
            ->where('month', $nextMonthName)
            ->where('year', $nextYear)
            ->with(['ppnSummary'])
            ->first();

        $this->nextMonthExists = $this->nextMonthTarget !== null;

        // Calculate max compensation amount
        if ($this->nextMonthExists) {
            $this->calculateMaxCompensation();
        }
    }

    /**
     * Get next month name from current month
     * 
     * @param string $currentMonth Current month name (e.g., "January")
     * @return string Next month name (e.g., "February")
     */
    protected function getNextMonthName(string $currentMonth): string
    {
        $months = [
            'January' => 'February',
            'February' => 'March',
            'March' => 'April',
            'April' => 'May',
            'May' => 'June',
            'June' => 'July',
            'July' => 'August',
            'August' => 'September',
            'September' => 'October',
            'October' => 'November',
            'November' => 'December',
            'December' => 'January', // Wrap to next year
        ];

        return $months[$currentMonth] ?? 'January';
    }

    /**
     * Calculate maximum compensation amount
     * RULE: Only Lebih Bayar can be compensated to next month
     */
    protected function calculateMaxCompensation()
    {
        if ($this->statusSetelah === 'Lebih Bayar') {
            // Only Lebih Bayar can compensate - max is available balance
            $this->maxCompensationAmount = $this->kompensasiTersedia;
        } else {
            // Kurang Bayar and Nihil cannot create compensation
            $this->maxCompensationAmount = 0;
        }
    }

    /**
     * Load received compensations (from previous month)
     */
    protected function loadReceivedCompensations()
    {
        $this->receivedCompensations = $this->taxReport->approvedCompensationsReceived()
            ->where('tax_type', 'ppn')
            ->with(['sourceTaxReport', 'createdBy', 'approvedBy'])
            ->orderBy('created_at', 'desc')
            ->get();
    }

    /**
     * Load given compensations (to next month)
     */
    protected function loadGivenCompensations()
    {
        $this->givenCompensations = $this->taxReport->compensationsGiven()
            ->where('tax_type', 'ppn')
            ->whereIn('status', ['pending', 'approved', 'rejected'])
            ->with(['targetTaxReport.ppnSummary', 'createdBy', 'approvedBy'])
            ->orderBy('status', 'asc') // pending first
            ->orderBy('created_at', 'desc')
            ->get();
    }

    /**
     * Open manual kompensasi edit form
     */
    public function openManualKompensasiForm()
    {
        $this->editingManualKompensasi = true;
        $this->tempManualKompensasi = $this->manualKompensasi;
        $this->tempManualKompensasiNotes = $this->manualKompensasiNotes;
        $this->dispatch('open-modal', id: 'manual-kompensasi-modal');
    }

    /**
     * Save manual kompensasi
     */
    public function saveManualKompensasi()
    {
        $this->validate([
            'tempManualKompensasi' => 'required|numeric|min:0',
            'tempManualKompensasiNotes' => 'nullable|string|max:1000',
        ], [
            'tempManualKompensasi.required' => 'Jumlah manual kompensasi wajib diisi',
            'tempManualKompensasi.numeric' => 'Jumlah harus berupa angka',
            'tempManualKompensasi.min' => 'Jumlah tidak boleh negatif',
        ]);

        try {
            $ppnSummary = $this->taxReport->ppnSummary;
            
            if (!$ppnSummary) {
                throw new \Exception('PPN Summary tidak ditemukan');
            }

            // Update manual kompensasi
            $ppnSummary->update([
                'manual_kompensasi' => $this->tempManualKompensasi,
                'manual_kompensasi_notes' => $this->tempManualKompensasiNotes,
            ]);

            // Recalculate to apply the manual kompensasi
            $ppnSummary->recalculate();

            Notification::make()
                ->success()
                ->title('Manual Kompensasi Diperbarui')
                ->body('Kompensasi manual berhasil diperbarui dan saldo telah dikalkulasi ulang.')
                ->send();

            $this->dispatch('close-modal', id: 'manual-kompensasi-modal');
            $this->editingManualKompensasi = false;
            $this->loadData();
            // The PPN ledger and the page strip show the saldo too.
            $this->dispatch('tax-report-changed');
            
        } catch (\Exception $e) {
            Notification::make()
                ->danger()
                ->title('Error')
                ->body('Gagal menyimpan manual kompensasi: ' . $e->getMessage())
                ->send();
        }
    }

    /**
     * Cancel manual kompensasi edit
     */
    public function cancelManualKompensasiEdit()
    {
        $this->editingManualKompensasi = false;
        $this->tempManualKompensasi = $this->manualKompensasi;
        $this->tempManualKompensasiNotes = $this->manualKompensasiNotes;
        $this->resetValidation();
        $this->dispatch('close-modal', id: 'manual-kompensasi-modal');
    }

    /**
     * Open compensation modal
     * RULE: Only Lebih Bayar can create compensation
     */
    public function openCompensationModal()
    {
        // Check if status allows compensation - ONLY Lebih Bayar
        if ($this->statusSetelah !== 'Lebih Bayar') {
            Notification::make()
                ->warning()
                ->title('Tidak Dapat Membuat Kompensasi')
                ->body('Hanya periode dengan status Lebih Bayar yang dapat dikompensasi ke bulan berikutnya.')
                ->send();
            return;
        }

        // Check if has available balance
        if ($this->kompensasiTersedia <= 0) {
            Notification::make()
                ->warning()
                ->title('Tidak Ada Saldo Tersedia')
                ->body('Tidak ada saldo lebih bayar yang tersedia untuk dikompensasi.')
                ->send();
            return;
        }

        // Check if next month exists
        if (!$this->nextMonthExists) {
            Notification::make()
                ->warning()
                ->title('Periode Berikutnya Belum Ada')
                ->body('Buat laporan pajak untuk bulan berikutnya terlebih dahulu.')
                ->send();
            return;
        }

        // Check if compensation already exists and is pending/approved
        $existingCompensation = $this->givenCompensations
        ->where('target_tax_report_id', $this->nextMonthTarget->id)
        ->where('status', 'pending')  // ✅ Only check for pending
        ->first();

        if ($existingCompensation) {
            Notification::make()
                ->warning()
                ->title('Kompensasi Pending Sudah Ada')
                ->body('Terdapat kompensasi yang masih menunggu approval. Selesaikan atau batalkan kompensasi tersebut terlebih dahulu.')
                ->send();
            return;
        }

        // Auto-fill with maximum amount (default value)
        $this->compensationAmount = $this->maxCompensationAmount;
        $this->compensationNotes = $this->generateDefaultNotes();

        $this->dispatch('open-modal', id: 'compensation-modal');
    }

    /**
     * Generate default compensation notes
     */
    protected function generateDefaultNotes()
    {
        $currentMonth = $this->taxReport->month;
        $nextMonth = $this->nextMonthTarget->month;
        
        return "Kompensasi Lebih Bayar dari {$currentMonth} ke {$nextMonth}";
    }

    /**
     * Create compensation to next month
     */
    public function createCompensation()
    {
        // Validation
        $this->validate([
            'compensationAmount' => [
                'required',
                'numeric',
                'min:1',
                'max:' . $this->maxCompensationAmount
            ],
            'compensationNotes' => 'nullable|string|max:1000',
        ], [
            'compensationAmount.required' => 'Masukkan jumlah kompensasi',
            'compensationAmount.min' => 'Jumlah minimal Rp 1',
            'compensationAmount.max' => 'Jumlah melebihi saldo tersedia (Rp ' . number_format($this->maxCompensationAmount, 0, ',', '.') . ')',
        ]);

        // Double check next month exists
        if (!$this->nextMonthExists) {
            Notification::make()
                ->danger()
                ->title('Error')
                ->body('Periode berikutnya tidak ditemukan.')
                ->send();
            return;
        }

        try {
            // Create compensation record
            $compensation = TaxCompensation::create([
                'source_tax_report_id' => $this->taxReportId,
                'target_tax_report_id' => $this->nextMonthTarget->id,
                'tax_type' => 'ppn',
                'amount_compensated' => $this->compensationAmount,
                'status' => 'pending',
                'type' => 'manual',
                'notes' => $this->compensationNotes,
                'created_by' => auth()->id(),
            ]);

            Notification::make()
                ->success()
                ->title('Kompensasi Dibuat')
                ->body('Kompensasi ke bulan berikutnya berhasil dibuat dan menunggu approval.')
                ->send();

            $this->dispatch('close-modal', id: 'compensation-modal');
            $this->loadData();
            $this->dispatch('compensationCreated');
            
        } catch (\Exception $e) {
            Notification::make()
                ->danger()
                ->title('Error')
                ->body('Gagal membuat kompensasi: ' . $e->getMessage())
                ->send();
        }
    }

    /**
     * Open approve modal
     */
    public function openApproveModal($compensationId)
    {
        $this->selectedCompensationId = $compensationId;
        $this->dispatch('open-modal', id: 'approve-compensation-modal');
    }

    /**
     * Approve compensation
     */
    public function approveCompensation()
    {
        try {
            $compensation = TaxCompensation::findOrFail($this->selectedCompensationId);
            
            if (!$compensation->canBeApproved()) {
                throw new \Exception('Kompensasi tidak dapat disetujui pada status ini.');
            }

            $compensation->approve(auth()->id());

            Notification::make()
                ->success()
                ->title('Kompensasi Disetujui')
                ->body('Kompensasi telah disetujui dan saldo sudah diperbarui.')
                ->send();

            $this->dispatch('close-modal', id: 'approve-compensation-modal');
            $this->loadData();
            $this->dispatch('compensationUpdated');
            
        } catch (\Exception $e) {
            Notification::make()
                ->danger()
                ->title('Error')
                ->body('Gagal menyetujui kompensasi: ' . $e->getMessage())
                ->send();
        }
    }

    /**
     * Open reject modal
     */
    public function openRejectModal($compensationId)
    {
        $this->selectedCompensationId = $compensationId;
        $this->rejectionReason = '';
        $this->dispatch('open-modal', id: 'reject-compensation-modal');
    }

    /**
     * Reject compensation
     */
    public function rejectCompensation()
    {
        $this->validate([
            'rejectionReason' => 'required|string|min:10|max:500',
        ], [
            'rejectionReason.required' => 'Alasan penolakan wajib diisi',
            'rejectionReason.min' => 'Alasan minimal 10 karakter',
        ]);

        try {
            $compensation = TaxCompensation::findOrFail($this->selectedCompensationId);
            
            if (!$compensation->canBeApproved()) {
                throw new \Exception('Kompensasi tidak dapat ditolak pada status ini.');
            }

            $compensation->reject(auth()->id(), $this->rejectionReason);

            Notification::make()
                ->success()
                ->title('Kompensasi Ditolak')
                ->body('Kompensasi telah ditolak.')
                ->send();

            $this->dispatch('close-modal', id: 'reject-compensation-modal');
            $this->loadData();
            $this->dispatch('compensationUpdated');
            
        } catch (\Exception $e) {
            Notification::make()
                ->danger()
                ->title('Error')
                ->body('Gagal menolak kompensasi: ' . $e->getMessage())
                ->send();
        }
    }

    /**
     * Cancel compensation
     */
    public function cancelCompensation($compensationId)
    {
        try {
            $compensation = TaxCompensation::findOrFail($compensationId);
            
            if (!$compensation->canBeCancelled()) {
                throw new \Exception('Kompensasi tidak dapat dibatalkan pada status ini.');
            }

            $compensation->cancel();

            Notification::make()
                ->success()
                ->title('Kompensasi Dibatalkan')
                ->body('Kompensasi telah dibatalkan.')
                ->send();

            $this->loadData();
            $this->dispatch('compensationUpdated');
            
        } catch (\Exception $e) {
            Notification::make()
                ->danger()
                ->title('Error')
                ->body('Gagal membatalkan kompensasi: ' . $e->getMessage())
                ->send();
        }
    }

    /**
     * Get next month info for display
     */
    public function getNextMonthInfoProperty()
    {
        if (!$this->nextMonthTarget) {
            return null;
        }

        $summary = $this->nextMonthTarget->ppnSummary;
        
        // Determine year - if December -> January, add 1 year
        $nextYear = $this->taxReport->year;

        if ($this->taxReport->month === 'December' && $this->nextMonthTarget->month === 'January') {
            $nextYear = (int)$nextYear + 1;
        }
        
        return [
            'id' => $this->nextMonthTarget->id,
            'month' => $this->nextMonthTarget->month,
            'year' => $nextYear,
            'status' => $summary?->status_final ?? 'Belum Dihitung',
            'saldo' => $summary?->saldo_final ?? 0,
            'formatted_saldo' => 'Rp ' . number_format(abs($summary?->saldo_final ?? 0), 0, ',', '.'),
        ];
    }

    /**
     * Check if can create compensation
     * RULE: Only Lebih Bayar with available balance can compensate
     */
    public function getCanCreateCompensationProperty()
    {
        return $this->nextMonthExists 
            && $this->statusSetelah === 'Lebih Bayar'
            && $this->kompensasiTersedia > 0
            && !$this->givenCompensations
                ->where('status', 'pending')  // ✅ Only block if there's a PENDING one
                ->where('target_tax_report_id', $this->nextMonthTarget?->id)
                ->count();
    }

    /**
     * Get compensation type label
     */
    public function getCompensationTypeLabelProperty()
    {
        return 'Kompensasi Lebih Bayar';
    }

    /**
     * Get compensation type description
     */
    public function getCompensationTypeDescriptionProperty()
    {
        return 'Saldo lebih bayar akan mengurangi pajak terutang bulan berikutnya';
    }

    /**
     * Reset form
     */
    protected function resetForm()
    {
        $this->compensationAmount = 0;
        $this->compensationNotes = '';
        $this->rejectionReason = '';
        $this->resetValidation();
    }

    /**
     * Returns every approved outgoing compensation whose amount no longer
     * matches the current surplus, meaning the source month's data changed
     * after the compensation was approved.
     *
     * Two cases are flagged:
     *  - Over-compensated : amount_compensated > kompensasi_tersedia (critical)
     *  - Under-compensated: amount_compensated < kompensasi_tersedia (informational)
     */
    public function getStaleGivenCompensationsProperty()
    {
        $ppnSummary = $this->taxReport->ppnSummary;

        if (!$ppnSummary) {
            return collect();
        }

        $currentSurplus = $ppnSummary->kompensasi_tersedia;

        return $this->givenCompensations
            ->where('status', 'approved')
            ->filter(fn ($c) => (float) $c->amount_compensated !== (float) $currentSurplus);
    }

    /**
     * True when the sum of approved outgoing compensations exceeds the
     * current available surplus — i.e. we compensated more than we have.
     */
    public function getIsOverCompensatedProperty(): bool
    {
        $ppnSummary = $this->taxReport->ppnSummary;

        if (!$ppnSummary) {
            return false;
        }

        return $ppnSummary->kompensasi_terpakai > $ppnSummary->kompensasi_tersedia;
    }

    /**
     * Open the revise modal for a specific approved compensation.
     * Pre-fills the amount with the current correct surplus.
     */
    public function openReviseModal(int $compensationId): void
    {
        $ppnSummary = $this->taxReport->ppnSummary;

        $this->reviseCompensationId = $compensationId;
        $this->reviseAmount         = $ppnSummary ? (float) $ppnSummary->kompensasi_tersedia : 0;
        $this->reviseNotes          = '';

        $this->dispatch('open-modal', id: 'revise-compensation-modal');
    }

    /**
     * Execute the revision: cancel old compensation and create a new approved
     * one with the updated amount, then cascade-recalculate all future months.
     */
    public function confirmRevise(): void
    {
        $this->validate([
            'reviseAmount' => ['required', 'numeric', 'min:1'],
            'reviseNotes'  => ['nullable', 'string', 'max:1000'],
        ], [
            'reviseAmount.required' => 'Jumlah revisi wajib diisi',
            'reviseAmount.min'      => 'Jumlah minimal Rp 1',
        ]);

        try {
            $compensation = TaxCompensation::findOrFail($this->reviseCompensationId);

            $compensation->revise(
                newAmount: (float) $this->reviseAmount,
                userId:    auth()->id(),
                notes:     $this->reviseNotes ?: null,
            );

            Notification::make()
                ->success()
                ->title('Kompensasi Direvisi')
                ->body('Kompensasi berhasil diperbarui dan semua bulan berikutnya telah dikalkulasi ulang.')
                ->send();

            $this->dispatch('close-modal', id: 'revise-compensation-modal');
            $this->reviseCompensationId = null;
            $this->reviseAmount         = 0;
            $this->reviseNotes          = '';
            $this->loadData();
            $this->dispatch('compensationUpdated');

        } catch (\Throwable $e) {
            Notification::make()
                ->danger()
                ->title('Gagal Merevisi Kompensasi')
                ->body($e->getMessage())
                ->send();
        }
    }

    /**
     * Render component
     */
    public function render()
    {
        return view('livewire.tax-report.ppn.tax-report-kompensasi');
    }
}