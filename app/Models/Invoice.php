<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\Activitylog\LogOptions;
use App\Traits\Trackable; 

class Invoice extends Model
{
    use HasFactory, LogsActivity, Trackable;

    protected $fillable = [
        'tax_report_id',
        'invoice_number',
        'company_name',
        'npwp',
        'type',
        'dpp',
        'ppn',
        'nihil',
        'file_path',
        'notes',
        'created_by',
        'is_revision',
        'original_invoice_id',
        'revision_number',
        'revision_reason'
    ];

    protected $casts = [
        'is_revision' => 'boolean',
        'nihil' => 'boolean',
        'revision_number' => 'integer'
    ];

    public function taxReport()
    {
        return $this->belongsTo(TaxReport::class);
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function bupots()
    {
        return $this->hasOne(Bupot::class);
    }

    // Relationship to the original invoice (if this is a revision)
    public function originalInvoice()
    {
        return $this->belongsTo(Invoice::class, 'original_invoice_id');
    }

    // Relationship to all revisions of this invoice (if this is the original)
    public function revisions()
    {
        return $this->hasMany(Invoice::class, 'original_invoice_id');
    }

    // Get the latest revision of this invoice
    public function latestRevision()
    {
        return $this->revisions()->orderBy('revision_number', 'desc')->first();
    }

    // Check if this invoice has any revisions
    public function hasRevisions()
    {
        return $this->revisions()->exists();
    }

    // Get the root invoice (original) if this is a revision
    public function getRootInvoice()
    {
        return $this->is_revision ? $this->originalInvoice : $this;
    }

    // Get all related invoices (original + all revisions)
    public function getAllVersions()
    {
        $root = $this->getRootInvoice();
        return Invoice::where('id', $root->id)
            ->orWhere('original_invoice_id', $root->id)
            ->orderBy('revision_number')
            ->get();
    }

    // Scope to get only original invoices (not revisions)
    public function scopeOriginals($query)
    {
        return $query->where('is_revision', false);
    }

    // Scope to get only revision invoices
    public function scopeRevisions($query)
    {
        return $query->where('is_revision', true);
    }

    // Get display name for the invoice (includes revision info)
    public function getDisplayNameAttribute()
    {
        $name = $this->invoice_number;
        if ($this->is_revision) {
            $name .= " (Rev. {$this->revision_number})";
        }
        return $name;
    }

    protected static function booted()
    {
        static::created(function ($invoice) {
            // Log dengan UserActivity (custom tracking)
            $invoice->logActivity(
                'invoice_created',
                "Faktur {$invoice->invoice_number} ({$invoice->type}) telah dibuat untuk {$invoice->taxReport->client->name}"
            );
            // The PPN summary is recalculated by InvoiceObserver.
        });

        static::updated(function ($invoice) {
            if ($invoice->wasChanged('dpp') || $invoice->wasChanged('ppn')) {
                $invoice->logChange(
                    'updated',
                    $invoice->getOriginal(),
                    $invoice->getChanges()
                );
            }
            // The PPN summary is recalculated by InvoiceObserver.
        });

        static::deleted(function ($invoice) {
            $invoice->logActivity(
                'invoice_deleted',
                "Faktur {$invoice->invoice_number} telah dihapus"
            );
            // The PPN summary is recalculated by InvoiceObserver.
        });
    }

    /**
     * Recalculate PPN tax summary for this invoice's tax report
     */
    protected function recalculateTaxSummary()
    {
        try {
            if ($this->taxReport) {
                // Get or create PPN summary
                $ppnSummary = $this->taxReport->taxCalculationSummaries()
                    ->firstOrCreate(
                        ['tax_type' => 'ppn'],
                        [
                            'pajak_masuk' => 0,
                            'pajak_keluar' => 0,
                            'selisih' => 0,
                            'status' => 'Nihil',
                            'kompensasi_diterima' => 0,
                            'kompensasi_tersedia' => 0,
                            'kompensasi_terpakai' => 0,
                            'saldo_final' => 0,
                            'status_final' => 'Nihil',
                            'report_status' => 'Belum Lapor',
                        ]
                    );

                // Recalculate using TaxCalculationSummary's recalculate method
                $ppnSummary->recalculate();
            }
        } catch (\Exception $e) {
            // Log error but don't break the invoice operation
            \Log::error('Failed to recalculate PPN summary for invoice ' . $this->id . ': ' . $e->getMessage());
        }
    }

    // Spatie ActivityLog tetap ada untuk detailed tracking
    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly([
                'tax_report_id',
                'invoice_number',
                'type',
                'dpp',
                'ppn',
                'is_revision',
                'original_invoice_id',
                'revision_number',
                'revision_reason'
            ])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs()
            ->setDescriptionForEvent(function(string $eventName) {
                $clientName = $this->taxReport?->client?->name ?? 'Klien';
                $invoiceNumber = $this->invoice_number ?? 'Tidak Diketahui';
                $invoiceType = $this->type ?? 'Umum';
                $userName = auth()->user()?->name ?? 'System';
                
                $revisionInfo = '';
                if ($this->is_revision) {
                    $revisionInfo = " (Revisi {$this->revision_number})";
                    if ($this->revision_reason) {
                        $revisionInfo .= " - Alasan: {$this->revision_reason}";
                    }
                }
                
                return match($eventName) {
                    'created' => $this->is_revision 
                        ? "[{$clientName}] 🔄 REVISI BARU: {$invoiceType} {$invoiceNumber}{$revisionInfo} | Dibuat oleh: {$userName}"
                        : "[{$clientName}] 📄 {$invoiceType} BARU: {$invoiceNumber} | Dibuat oleh: {$userName}",
                    'updated' => "[{$clientName}] 🔄 DIPERBARUI: {$invoiceType} {$invoiceNumber}{$revisionInfo} | Diperbarui oleh: {$userName}",
                    'deleted' => "[{$clientName}] 🗑️ DIHAPUS: {$invoiceType} {$invoiceNumber}{$revisionInfo} | Dihapus oleh: {$userName}",
                    default => "[{$clientName}] {$invoiceType} {$invoiceNumber}{$revisionInfo} telah {$eventName} oleh {$userName}"
                };
            });
    }
}