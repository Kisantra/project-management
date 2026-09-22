<?php

namespace App\Models;

use App\Traits\Trackable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Models\ClientGroup;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class Client extends Model
{
    use HasFactory,Trackable;

    protected $fillable = ['name', 'email', 'logo'];

    public function group(): BelongsTo
    {
        return $this->belongsTo(ClientGroup::class, 'group_id');
    }

    public function projects()
    {
        return $this->hasMany(Project::class);
    }

    public function applicationCredentials(): HasMany
    {
        return $this->hasMany(ApplicationClient::class);
    }

    public function activeApplications(): HasMany
    {
        return $this->hasMany(ApplicationClient::class)->where('is_active', true);
    }   

    public function applications()
    {
        return $this->hasMany(ApplicationClient::class);
    }

    public function taxreports()
    {
        return $this->hasMany(TaxReport::class);
    }

    /** Surat & berita acara issued for this client. */
    public function calendarEvents(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(\App\Models\CalendarEvent::class);
    }

    public function letters(): HasMany
    {
        return $this->hasMany(Letter::class);
    }

    /**
     * Get the PIC that manages this client
     */
    public function pic(): BelongsTo
    {
        return $this->belongsTo(Pic::class);
    }

    public function accountRepresentative(): BelongsTo
    {
        return $this->belongsTo(AccountRepresentative::class, 'ar_id');
    }

    public function userClients()
    {
        return $this->hasMany(UserClient::class);
    }

    public function contacts(): HasMany
    {
        return $this->hasMany(ClientContact::class);
    }
    
    /**
     * Relationship ke credential utama
     */
    public function clientCredential(): BelongsTo
    {
        return $this->belongsTo(ClientCredential::class, 'credential_id');
    }

    public function clientDocuments(): HasMany
    {
        return $this->hasMany(ClientDocument::class);
    }

    public function employees(): HasMany
    {
        return $this->hasMany(Employee::class);
    }

    /**
     * Get all affiliates of this client
     */
    public function affiliates(): HasMany
    {
        return $this->hasMany(ClientAffiliate::class);
    }

    public function communications()
    {
        return $this->hasMany(ClientCommunication::class);
    }

    public function chatThreads(): HasMany
    {
        return $this->hasMany(ChatThread::class);
    }

    public function requirementGroups(): HasMany
    {
        return $this->hasMany(ClientRequirementGroup::class);
    }

    public function documentRequirements(): HasMany
    {
        return $this->hasMany(ClientDocumentRequirement::class);
    }

    public function pendingRequirements()
    {
        return $this->documentRequirements()->pending();
    }

    /**
     * âœ… ADD THIS - Get fulfilled requirements
     */
    public function fulfilledRequirements()
    {
        return $this->documentRequirements()->fulfilled();
    }

    /**
     * âœ… ADD THIS - Get overdue requirements
     */
    public function overdueRequirements()
    {
        return $this->documentRequirements()->overdue();
    }

    /**
     * âœ… ADD THIS - Get requirement completion stats
     */
    public function getRequirementStatsAttribute(): array
    {
        $total = $this->documentRequirements()->count();
        $fulfilled = $this->documentRequirements()->fulfilled()->count();
        $pending = $this->documentRequirements()->pending()->count();
        $overdue = $this->documentRequirements()->overdue()->count();
        $waived = $this->documentRequirements()->waived()->count();
        
        return [
            'total' => $total,
            'fulfilled' => $fulfilled,
            'pending' => $pending,
            'overdue' => $overdue,
            'waived' => $waived,
            'completion_percentage' => $total > 0 ? round(($fulfilled / $total) * 100, 1) : 0,
        ];
    }

    /**
     * Get active affiliates only
     */
    public function activeAffiliates(): HasMany
    {
        return $this->hasMany(ClientAffiliate::class)->where('status', 'active');
    }

    public function getProjectsWithTeam()
    {
        return $this->projects()
                    ->with(['userProjects.user'])
                    ->orderBy('created_at', 'desc')
                    ->get();
    }

    /**
     * Relasi untuk Invoices
     */
    public function invoices(): HasManyThrough
    {
        return $this->hasManyThrough(Invoice::class, TaxReport::class);
    }

    /**
     * Get client folder path
     */
    public function getFolderPath(): string
    {
        $sluggedName = Str::slug($this->name);
        return "clients/{$sluggedName}";
    }

    /**
     * Get legal documents folder path
     */
    public function getLegalFolderPath(): string
    {
        return $this->getFolderPath() . '/Legal';
    }

    /**
     * Clean up client folder when client is deleted
     */
    public function cleanupClientFolder(): void
    {
        $folderPath = $this->getFolderPath();
        
        try {
            // Delete all files in client folder recursively
            Storage::disk('public')->deleteDirectory($folderPath);
            
            \Log::info("Cleaned up client folder: {$folderPath}");
        } catch (\Exception $e) {
            \Log::error("Failed to cleanup client folder: {$folderPath}", [
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Create client folders if they don't exist
     */
    public function ensureFoldersExist(): void
    {
        $legalFolderPath = $this->getLegalFolderPath();
        
        if (!Storage::disk('public')->exists($legalFolderPath)) {
            Storage::disk('public')->makeDirectory($legalFolderPath);
        }
    }

    public function getFormattedClientTypeAttribute(): string
    {
        if ($this->client_subtype) {
            return $this->client_type . ' - ' . $this->client_subtype;
        }
        return $this->client_type;
    }


    /**
     * Get SOP legal documents yang applicable untuk client ini
     */
    public function getApplicableSopDocuments()
    {
        return SopLegalDocument::forClientType($this->client_type)
                            ->active()
                            ->orderBy('category')
                            ->orderBy('order')
                            ->get();
    }

    /**
     * Get dokumen checklist dengan status upload
     */
    public function getLegalDocumentsChecklist()
    {
        // Ambil semua SOP dokumen yang applicable untuk tipe klien ini
        $sopDocuments = $this->getApplicableSopDocuments();
        
        // Map setiap SOP dengan status uploadnya
        return $sopDocuments->map(function ($sopDoc) {
            // Cari dokumen yang sudah ada untuk SOP ini
            $clientDoc = $this->clientDocuments()
                            ->where('sop_legal_document_id', $sopDoc->id)
                            ->latest()
                            ->first();
            
            return [
                'sop_id' => $sopDoc->id,
                'name' => $sopDoc->name,
                'description' => $sopDoc->description,
                'category' => $sopDoc->category,
                'is_required' => $sopDoc->is_required,
                'is_uploaded' => $clientDoc && $clientDoc->is_uploaded,
                'uploaded_document' => $clientDoc,
                'file_path' => $clientDoc?->file_path,
                'uploaded_at' => $clientDoc?->updated_at,
                'uploaded_by' => $clientDoc?->user,
                'status' => $clientDoc?->status ?? 'required',
                'status_label' => $clientDoc?->status_label ?? 'Belum Upload',
                'admin_notes' => $clientDoc?->admin_notes,
                'reviewed_by' => $clientDoc?->reviewer,
                'reviewed_at' => $clientDoc?->reviewed_at,
            ];
        });
    }

    /**
     * Get statistics dokumen legal
     */
    public function getLegalDocumentsStats()
    {
        $checklist = $this->getLegalDocumentsChecklist();
        
        $totalRequired = $checklist->where('is_required', true)->count();
        $uploadedRequired = $checklist->where('is_required', true)
                                    ->where('is_uploaded', true)
                                    ->count();
        
        // Count by status
        $validDocs = $this->clientDocuments()
                        ->whereNotNull('file_path')
                        ->whereNotNull('sop_legal_document_id')
                        ->where('status', 'valid')
                        ->count();
        
        $expiredDocs = $this->clientDocuments()
                        ->whereNotNull('file_path')
                        ->whereNotNull('sop_legal_document_id')
                        ->where('status', 'expired')
                        ->count();
        
        $pendingDocs = $this->clientDocuments()
                        ->whereNotNull('file_path')
                        ->whereNotNull('sop_legal_document_id')
                        ->where('status', 'pending_review')
                        ->count();
        
        $rejectedDocs = $this->clientDocuments()
                            ->whereNotNull('file_path')
                            ->whereNotNull('sop_legal_document_id')
                            ->where('status', 'rejected')
                            ->count();
        
        return [
            'total_documents' => $checklist->count(),
            'total_required' => $totalRequired,
            'total_optional' => $checklist->where('is_required', false)->count(),
            'uploaded' => $checklist->where('is_uploaded', true)->count(),
            'not_uploaded' => $checklist->where('is_uploaded', false)->count(),
            'valid' => $validDocs,
            'expired' => $expiredDocs,
            'pending_review' => $pendingDocs,
            'rejected' => $rejectedDocs,
            'completion_percentage' => $totalRequired > 0 
                ? round(($uploadedRequired / $totalRequired) * 100, 2) 
                : 100,
        ];
    }

    /**
     * Get missing required documents
     */
    public function getMissingRequiredDocuments()
    {
        return $this->getLegalDocumentsChecklist()
                    ->where('is_required', true)
                    ->where('is_uploaded', false)
                    ->pluck('name');
    }

    /**
     * Check if all required documents are uploaded
     */
    public function hasAllRequiredDocuments(): bool
    {
        return $this->getMissingRequiredDocuments()->isEmpty();
    }
    
    protected static function booted()
    {
        static::created(function ($client) {
            $client->logActivity(
                'client_created',
                "Klien baru '{$client->name}' telah ditambahkan ke sistem"
            );
        });

        static::updated(function ($client) {
            if ($client->wasChanged('status')) {
                $status = $client->status;
                $client->logActivity(
                    'client_status_changed',
                    "Status klien '{$client->name}' diubah menjadi: {$status}"
                );
            }

            if ($client->wasChanged('pic_id')) {
                $picName = $client->pic?->name ?? 'Tidak ada';
                $client->logActivity(
                    'client_pic_changed',
                    "PIC klien '{$client->name}' diubah menjadi: {$picName}"
                );
            }

            if ($client->wasChanged('ar_id')) {
                $arName = $client->ar?->name ?? 'Tidak ada';
                $client->logActivity(
                    'client_ar_changed',
                    "Account Representative klien '{$client->name}' diubah menjadi: {$arName}"
                );
            }

            // Log contract changes
            $contractFields = ['ppn_contract', 'pph_contract', 'bupot_contract', 'pph_badan_contract'];
            foreach ($contractFields as $field) {
                if ($client->wasChanged($field)) {
                    $status = $client->$field ? 'Aktif' : 'Tidak Aktif';
                    $contractType = str_replace('_contract', '', $field);
                    $client->logActivity(
                        'client_contract_changed',
                        "Kontrak {$contractType} klien '{$client->name}' diubah menjadi: {$status}"
                    );
                }
            }
        });

        static::deleted(function ($client) {
            $client->logActivity(
                'client_deleted',
                "Klien '{$client->name}' telah dihapus dari sistem"
            );
        });
    }

    // Custom method untuk document upload
    public function logDocumentUpload(string $filename, string $documentType = 'document')
    {
        $this->logActivity(
            'document_uploaded',
            "Dokumen '{$filename}' ({$documentType}) diunggah untuk klien '{$this->name}'"
        );
    }
}
