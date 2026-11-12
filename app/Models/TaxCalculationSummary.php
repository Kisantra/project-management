<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;

class TaxCalculationSummary extends Model
{
    use HasFactory;

    protected $fillable = [
        'tax_report_id',
        'tax_type',
        'pajak_masuk',
        'pajak_keluar',
        'selisih',
        'status',
        'kompensasi_diterima',
        'kompensasi_tersedia',
        'kompensasi_terpakai',
        'manual_kompensasi',
        'manual_kompensasi_notes',
        'saldo_final',
        'status_final',
        'notes',
        'calculated_at',
        'calculated_by',
        'bayar_status',
        'bayar_at',
        'bukti_bayar',
        'report_status',
        'reported_at',
        'bukti_lapor',
        'nomor_bukti_lapor',
        'no_activity',
        'no_activity_by',
        'no_activity_at',
    ];

    protected $casts = [
        'pajak_masuk' => 'decimal:2',
        'pajak_keluar' => 'decimal:2',
        'selisih' => 'decimal:2',
        'kompensasi_diterima' => 'decimal:2',
        'kompensasi_tersedia' => 'decimal:2',
        'kompensasi_terpakai' => 'decimal:2',
        'manual_kompensasi' => 'decimal:2',
        'saldo_final' => 'decimal:2',
        'calculated_at' => 'datetime',
        'bayar_at' => 'date',
        'no_activity' => 'boolean',
        'no_activity_at' => 'datetime',
    ];

    /**
     * Jenis pajak yang hanya wajib dilaporkan bila masa itu ada aktivitas.
     *
     * PPN dan PPh 21 wajib dilaporkan tiap masa meski nihil, jadi keduanya tidak
     * pernah bisa ditandai "tanpa aktivitas".
     */
    public const CONDITIONAL_TYPES = ['bupot', 'pph_badan'];

    public function isConditional(): bool
    {
        return \in_array($this->tax_type, self::CONDITIONAL_TYPES, true);
    }

    /** Siapa yang menyatakan masa ini tanpa aktivitas. */
    public function noActivityBy()
    {
        return $this->belongsTo(User::class, 'no_activity_by');
    }

    /**
     * Tandai masa ini tidak ada aktivitas: dianggap selesai tanpa SPT.
     *
     * report_status tetap disetel "Sudah Lapor" supaya seluruh perhitungan yang
     * sudah ada terus bekerja tanpa perlu mengenal nilai status baru. Kolom
     * no_activity yang membedakannya dari pelaporan sungguhan, dan dipakai
     * tempat-tempat yang memang perlu membedakan (mis. daftar SPT klien).
     */
    public function markNoActivity(?int $userId = null): void
    {
        $this->update([
            'no_activity' => true,
            'no_activity_by' => $userId,
            'no_activity_at' => now(),
            'report_status' => 'Sudah Lapor',
            'bayar_status' => 'Sudah Bayar',
            // Sengaja TIDAK mengisi reported_at, bukti_lapor, atau
            // nomor_bukti_lapor: tidak ada pelaporan yang terjadi.
        ]);
    }

    /**
     * Batalkan penandaan, kembalikan masa ini menjadi kewajiban.
     *
     * Status hanya dikembalikan ke "Belum" bila memang tidak ada bukti pelaporan.
     * Tanpa penjagaan ini, masa yang SPT-nya sudah diunggah akan kehilangan
     * status selesainya hanya karena penanda nihil dibersihkan.
     */
    public function clearNoActivity(): void
    {
        $adaPelaporanNyata = $this->bukti_lapor !== null;

        $this->update([
            'no_activity' => false,
            'no_activity_by' => null,
            'no_activity_at' => null,
        ] + ($adaPelaporanNyata ? [] : [
            'report_status' => 'Belum Lapor',
            'bayar_status' => 'Belum Bayar',
        ]));
    }

    /**
     * Relationships
     */
    public function taxReport()
    {
        return $this->belongsTo(TaxReport::class);
    }

    public function calculatedBy()
    {
        return $this->belongsTo(User::class, 'calculated_by');
    }

    /**
     * Scopes
     */
    public function scopePpn(Builder $query): Builder
    {
        return $query->where('tax_type', 'ppn');
    }

    public function scopePph(Builder $query): Builder
    {
        return $query->where('tax_type', 'pph');
    }

    public function scopeBupot(Builder $query): Builder
    {
        return $query->where('tax_type', 'bupot');
    }

    public function scopePphBadan(Builder $query): Builder
    {
        return $query->where('tax_type', 'pph_badan');
    }

    public function scopeLebihBayar(Builder $query): Builder
    {
        return $query->where('status_final', 'Lebih Bayar');
    }

    public function scopeKurangBayar(Builder $query): Builder
    {
        return $query->where('status_final', 'Kurang Bayar');
    }

    public function scopeSudahBayar(Builder $query): Builder
    {
        return $query->where('bayar_status', 'Sudah Bayar');
    }

    public function scopeBelumBayar(Builder $query): Builder
    {
        return $query->where('bayar_status', 'Belum Bayar');
    }

    /**
     * Accessors
     */
    public function getTaxTypeNameAttribute(): string
    {
        return match($this->tax_type) {
            'ppn' => 'PPN',
            'pph' => 'PPh',
            'bupot' => 'PPh Unifikasi',
            'pph_badan' => 'PPh Badan',
            default => 'Unknown'
        };
    }

    public function getStatusBadgeColorAttribute(): string
    {
        return match($this->status_final ?? $this->status) {
            'Lebih Bayar' => 'success',
            'Kurang Bayar' => 'warning',
            'Nihil' => 'gray',
            default => 'gray'
        };
    }

    public function getBayarStatusBadgeColorAttribute(): string
    {
        return match($this->bayar_status) {
            'Sudah Bayar' => 'success',
            'Belum Bayar' => 'danger',
            default => 'gray'
        };
    }

    public function getBayarStatusLabelAttribute(): string
    {
        return match($this->bayar_status) {
            'Sudah Bayar' => 'Sudah Dibayar',
            'Belum Bayar' => 'Belum Dibayar',
            default => 'Tidak Diketahui'
        };
    }

    public function getFormattedPajakMasukAttribute(): string
    {
        return 'Rp ' . number_format($this->pajak_masuk, 0, ',', '.');
    }

    public function getFormattedPajakKeluarAttribute(): string
    {
        return 'Rp ' . number_format($this->pajak_keluar, 0, ',', '.');
    }

    public function getFormattedSelisihAttribute(): string
    {
        return 'Rp ' . number_format(abs($this->selisih), 0, ',', '.');
    }

    public function getFormattedSaldoFinalAttribute(): string
    {
        return 'Rp ' . number_format(abs($this->saldo_final), 0, ',', '.');
    }

    public function getFormattedKompensasiTersediaAttribute(): string
    {
        return 'Rp ' . number_format($this->kompensasi_tersedia, 0, ',', '.');
    }

    public function getSisaKompensasiTersediaAttribute(): float
    {
        return max(0, $this->kompensasi_tersedia - $this->kompensasi_terpakai);
    }

    public function getFormattedSisaKompensasiAttribute(): string
    {
        return 'Rp ' . number_format($this->sisa_kompensasi_tersedia, 0, ',', '.');
    }

    public function getFormattedManualKompensasiAttribute(): string
    {
        return 'Rp ' . number_format($this->manual_kompensasi, 0, ',', '.');
    }

    public function getCompensationsReceivedCountAttribute(): int
    {
        return $this->taxReport
            ->compensationsReceived()
            ->where('tax_type', $this->tax_type)
            ->where('status', 'approved')
            ->count();
    }

    /**
     * Get count of excluded Faktur Keluaran (with prefixes 02, 03, 07, 08)
     */
    public function getExcludedFakturKeluarCountAttribute(): int
    {
        if (!$this->taxReport) {
            return 0;
        }

        return $this->taxReport->originalInvoices()
            ->where('type', 'Faktur Keluaran')
            ->where(function($query) {
                // Include invoices that start with any of the excluded prefixes
                foreach ($this->getExcludedInvoicePrefixes() as $prefix) {
                    $query->orWhere('invoice_number', 'like', $prefix . '%');
                }
            })
            ->count();
    }

    /**
     * Get total PPN amount from excluded Faktur Keluaran
     */
    public function getExcludedPpnAmountAttribute(): float
    {
        if (!$this->taxReport) {
            return 0;
        }

        return $this->taxReport->originalInvoices()
            ->where('type', 'Faktur Keluaran')
            ->where(function($query) {
                // Include invoices that start with any of the excluded prefixes
                foreach ($this->getExcludedInvoicePrefixes() as $prefix) {
                    $query->orWhere('invoice_number', 'like', $prefix . '%');
                }
            })
            ->sum('ppn');
    }

    /**
     * Get formatted excluded PPN amount
     */
    public function getFormattedExcludedPpnAmountAttribute(): string
    {
        return 'Rp ' . number_format($this->excluded_ppn_amount, 0, ',', '.');
    }

    /**
     * Check methods
     */
    public function isLebihBayar(): bool
    {
        return ($this->status_final ?? $this->status) === 'Lebih Bayar';
    }

    public function isKurangBayar(): bool
    {
        return ($this->status_final ?? $this->status) === 'Kurang Bayar';
    }

    public function isNihil(): bool
    {
        return ($this->status_final ?? $this->status) === 'Nihil';
    }

    public function canBeCompensationSource(): bool
    {
        return $this->isLebihBayar() && $this->sisa_kompensasi_tersedia > 0;
    }

    public function canReceiveCompensation(): bool
    {
        return $this->isKurangBayar();
    }

    /**
     * Check if tax has been paid
     */
    public function isSudahBayar(): bool
    {
        return $this->bayar_status === 'Sudah Bayar';
    }

    /**
     * Check if tax is unpaid
     */
    public function isBelumBayar(): bool
    {
        return $this->bayar_status === 'Belum Bayar';
    }

    /**
     * Mark tax as paid
     */
    public function markAsPaid(?string $buktiBayar = null, ?\Carbon\Carbon $bayarAt = null): bool
    {
        return $this->update([
            'bayar_status' => 'Sudah Bayar',
            'bayar_at' => $bayarAt ?? now(),
            'bukti_bayar' => $buktiBayar,
        ]);
    }

    /**
     * Mark tax as unpaid
     */
    public function markAsUnpaid(): bool
    {
        return $this->update([
            'bayar_status' => 'Belum Bayar',
            'bayar_at' => null,
            'bukti_bayar' => null,
        ]);
    }

    /**
     * Recalculate summary
     */
    public function recalculate(): self
    {
        // Get data berdasarkan tax_type
        $data = $this->calculateFromSource();
        
        // Update summary
        $this->fill($data);
        $this->calculated_at = now();
        $this->calculated_by = auth()->id();
        $this->save();
        
        return $this->fresh();
    }

    /**
     * Calculate from source data (invoices, income_taxes, bupots)
     */
    protected function calculateFromSource(): array
    {
        $taxReport = $this->taxReport;
        
        return match($this->tax_type) {
            'ppn' => $this->calculatePpn($taxReport),
            'pph' => $this->calculatePph($taxReport),
            'bupot' => $this->calculateBupot($taxReport),
            'pph_badan' => $this->calculatePphBadan($taxReport),
            default => []
        };
    }

    /**
     * Get invoice number prefixes that should be excluded from PPN calculation
     * These are special invoice types that don't count toward tax liability
     * 
     * @return array
     */
    protected function getExcludedInvoicePrefixes(): array
    {
        return ['02', '03', '07', '08'];
    }

    /**
     * Calculate PPN
     * 
     * Important: Faktur Keluaran with prefixes 02, 03, 07, 08 are excluded from calculation
     * These represent special transaction types that are not subject to PPN:
     * - 02: Exports (zero-rated transactions)
     * - 03: Transactions to free trade zones (tax-exempt special economic zones)
     * - 07: Transactions with certain exemptions (government-approved exemptions)
     * - 08: Other non-taxable transactions (various exempt categories)
     */
    protected function calculatePpn($taxReport): array
    {
        // Same rules as the PPN screens (excluded codes, business-related
        // Faktur Masuk, latest revision): see PpnBreakdown.
        $breakdown = \App\Services\TaxReport\PpnBreakdown::for($taxReport);
        $pajakMasuk = $breakdown->ppnMasuk();
        $pajakKeluar = $breakdown->ppnKeluar();

        $selisih = $pajakKeluar - $pajakMasuk;
        
        // Hitung kompensasi yang diterima (approved)
        $kompensasiDiterima = $taxReport->approvedCompensationsReceived()
            ->where('tax_type', 'ppn')
            ->sum('amount_compensated');
        
        // Hitung kompensasi yang sudah terpakai
        $kompensasiTerpakai = $taxReport->approvedCompensationsGiven()
            ->where('tax_type', 'ppn')
            ->sum('amount_compensated');
        
        // Keep existing manual_kompensasi value if it exists
        $manualKompensasi = $this->manual_kompensasi ?? 0;
        
        // Saldo final setelah kompensasi (termasuk manual kompensasi)
        $saldoFinal = $selisih - $kompensasiDiterima - $manualKompensasi;
        
        // Status awal (sebelum kompensasi)
        $status = $this->determineStatus($selisih);
        
        // Status final (setelah kompensasi)
        $statusFinal = $this->determineStatus($saldoFinal);
        
        // Kompensasi tersedia (jika lebih bayar)
        $kompensasiTersedia = $statusFinal === 'Lebih Bayar' ? abs($saldoFinal) : 0;
        
        return [
            'pajak_masuk' => $pajakMasuk,
            'pajak_keluar' => $pajakKeluar,
            'selisih' => $selisih,
            'status' => $status,
            'kompensasi_diterima' => $kompensasiDiterima,
            'kompensasi_tersedia' => $kompensasiTersedia,
            'kompensasi_terpakai' => $kompensasiTerpakai,
            'manual_kompensasi' => $manualKompensasi,
            'saldo_final' => $saldoFinal,
            'status_final' => $statusFinal,
            'bayar_status' => $this->bayar_status ?? 'Belum Bayar', // Keep existing status or default to Belum Bayar
        ];
    }

    /**
     * Calculate PPh
     */
    protected function calculatePph($taxReport): array
    {

        // ✅ NEW - Use 'pajak_penghasilan' column (as per IncomeTax model)
        $pajakTerutang = $taxReport->incomeTaxs()->sum('pajak_penghasilan');
        $pajakDipotong = 0; // Jika ada withholding
        
        $selisih = $pajakTerutang - $pajakDipotong;
        
        // Similar logic dengan PPN
        $kompensasiDiterima = $taxReport->approvedCompensationsReceived()
            ->where('tax_type', 'pph')
            ->sum('amount_compensated');
        
        $kompensasiTerpakai = $taxReport->approvedCompensationsGiven()
            ->where('tax_type', 'pph')
            ->sum('amount_compensated');
        
        // Keep existing manual_kompensasi value if it exists
        $manualKompensasi = $this->manual_kompensasi ?? 0;
        
        $saldoFinal = $selisih - $kompensasiDiterima - $manualKompensasi;
        $status = $this->determineStatus($selisih);
        $statusFinal = $this->determineStatus($saldoFinal);
        $kompensasiTersedia = $statusFinal === 'Lebih Bayar' ? abs($saldoFinal) : 0;
        
        return [
            'pajak_masuk' => $pajakDipotong,
            'pajak_keluar' => $pajakTerutang,
            'selisih' => $selisih,
            'status' => $status,
            'kompensasi_diterima' => $kompensasiDiterima,
            'kompensasi_tersedia' => $kompensasiTersedia,
            'kompensasi_terpakai' => $kompensasiTerpakai,
            'manual_kompensasi' => $manualKompensasi,
            'saldo_final' => $saldoFinal,
            'status_final' => $statusFinal,
            'bayar_status' => $this->bayar_status ?? 'Belum Bayar',
        ];
    }

    /**
     * Calculate Bupot
     */
    protected function calculateBupot($taxReport): array
    {
        // TODO: Implement berdasarkan Bupot model
        $pajakTerutang = $taxReport->bupots()->sum('bupot_amount');
        $pajakDipotong = 0;
        
        $selisih = $pajakTerutang - $pajakDipotong;
        
        // Similar logic
        $kompensasiDiterima = $taxReport->approvedCompensationsReceived()
            ->where('tax_type', 'bupot')
            ->sum('amount_compensated');
        
        $kompensasiTerpakai = $taxReport->approvedCompensationsGiven()
            ->where('tax_type', 'bupot')
            ->sum('amount_compensated');
        
        // Keep existing manual_kompensasi value if it exists
        $manualKompensasi = $this->manual_kompensasi ?? 0;
        
        $saldoFinal = $selisih - $kompensasiDiterima - $manualKompensasi;
        $status = $this->determineStatus($selisih);
        $statusFinal = $this->determineStatus($saldoFinal);
        $kompensasiTersedia = $statusFinal === 'Lebih Bayar' ? abs($saldoFinal) : 0;
        
        return [
            'pajak_masuk' => $pajakDipotong,
            'pajak_keluar' => $pajakTerutang,
            'selisih' => $selisih,
            'status' => $status,
            'kompensasi_diterima' => $kompensasiDiterima,
            'kompensasi_tersedia' => $kompensasiTersedia,
            'kompensasi_terpakai' => $kompensasiTerpakai,
            'manual_kompensasi' => $manualKompensasi,
            'saldo_final' => $saldoFinal,
            'status_final' => $statusFinal,
            'bayar_status' => $this->bayar_status ?? 'Belum Bayar',
        ];
    }

    /**
     * Calculate PPh Badan (Corporate Income Tax)
     */
    protected function calculatePphBadan($taxReport): array
    {
        // TODO: Implement calculation logic for PPh Badan
        // This will likely be based on annual corporate income
        // For now, returning a basic structure similar to other tax types
        
        $pajakTerutang = 0; // Will be calculated based on corporate income
        $pajakDipotong = 0; // Any advance payments or withholdings
        
        $selisih = $pajakTerutang - $pajakDipotong;
        
        // Kompensasi logic
        $kompensasiDiterima = $taxReport->approvedCompensationsReceived()
            ->where('tax_type', 'pph_badan')
            ->sum('amount_compensated');
        
        $kompensasiTerpakai = $taxReport->approvedCompensationsGiven()
            ->where('tax_type', 'pph_badan')
            ->sum('amount_compensated');
        
        // Keep existing manual_kompensasi value if it exists
        $manualKompensasi = $this->manual_kompensasi ?? 0;
        
        $saldoFinal = $selisih - $kompensasiDiterima - $manualKompensasi;
        $status = $this->determineStatus($selisih);
        $statusFinal = $this->determineStatus($saldoFinal);
        $kompensasiTersedia = $statusFinal === 'Lebih Bayar' ? abs($saldoFinal) : 0;
        
        return [
            'pajak_masuk' => $pajakDipotong,
            'pajak_keluar' => $pajakTerutang,
            'selisih' => $selisih,
            'status' => $status,
            'kompensasi_diterima' => $kompensasiDiterima,
            'kompensasi_tersedia' => $kompensasiTersedia,
            'kompensasi_terpakai' => $kompensasiTerpakai,
            'manual_kompensasi' => $manualKompensasi,
            'saldo_final' => $saldoFinal,
            'status_final' => $statusFinal,
            'bayar_status' => $this->bayar_status ?? 'Belum Bayar',
        ];
    }

    /**
     * Determine status berdasarkan amount
     */
    protected function determineStatus(float $amount): string
    {
        if ($amount > 0) {
            return 'Kurang Bayar';
        } elseif ($amount < 0) {
            return 'Lebih Bayar';
        }
        return 'Nihil';
    }

    /**
     * Auto-recalculate on boot
     */
    protected static function booted()
    {
        // Auto-set calculated info
        static::creating(function ($summary) {
            if (!$summary->calculated_at) {
                $summary->calculated_at = now();
            }
            if (!$summary->calculated_by && auth()->check()) {
                $summary->calculated_by = auth()->id();
            }
        });
    }
}