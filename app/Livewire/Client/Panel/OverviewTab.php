<?php

namespace App\Livewire\Client\Panel;

use Livewire\Component;
use App\Livewire\Client\Panel\Concerns\UsesCurrentClient;
use App\Models\Client;
use App\Models\UserActivity;
use App\Models\ClientDocument;
use App\Models\ClientDocumentRequirement;
use App\Models\SopLegalDocument;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Collection;

class OverviewTab extends Component
{
    use UsesCurrentClient;

    public Collection $clients;

    // Cache duration in seconds (5 minutes)
    protected int $cacheDuration = 300;

    protected $listeners = ['refreshOverview' => 'refresh'];

    public function mount()
    {
        $this->clients = $this->clientContext()->clients();
    }

    public function getSelectedClientProperty(): ?Client
    {
        return $this->currentClient();
    }

    /**
     * Clear cached data for current client
     */
    protected function clearClientCache()
    {
        if (!$this->selectedClientId) {
            return;
        }

        $userId = auth()->id();
        $clientId = $this->selectedClientId;

        $cacheKeys = [
            "overview_doc_checklist_{$userId}_{$clientId}",
            "overview_requirements_{$userId}_{$clientId}",
            "overview_stats_{$userId}_{$clientId}",
        ];

        foreach ($cacheKeys as $key) {
            Cache::forget($key);
        }
    }

    /**
     * Get document checklist based on SOP Legal Documents
     * Shows which documents are required and their upload status
     */
    public function getDocumentChecklistProperty(): Collection
    {
        if (!$this->selectedClientId) {
            return collect();
        }

        $client = $this->selectedClient;

        if (!$client) {
            return collect();
        }

        $userId = auth()->id();
        $clientId = $this->selectedClientId;
        $clientType = $client->client_type;

        return Cache::remember(
            "overview_doc_checklist_{$userId}_{$clientId}",
            $this->cacheDuration,
            function () use ($clientId, $clientType) {
                // Get applicable SOP documents for this client type
                $sopDocuments = SopLegalDocument::query()
                    ->forClientType($clientType)
                    ->active()
                    ->orderBy('category')
                    ->orderBy('order')
                    ->select(['id', 'name', 'description', 'category', 'is_required'])
                    ->get();

                // Get all uploaded documents for this client (indexed by sop_legal_document_id)
                $uploadedDocs = ClientDocument::query()
                    ->where('client_id', $clientId)
                    ->whereNotNull('sop_legal_document_id')
                    ->whereNotNull('file_path')
                    ->select([
                        'id',
                        'sop_legal_document_id',
                        'file_path',
                        'original_filename',
                        'status',
                        'user_id',
                        'updated_at',
                        'admin_notes'
                    ])
                    ->with(['user:id,name'])
                    ->get()
                    ->keyBy('sop_legal_document_id');

                // Map each SOP document with its upload status
                return $sopDocuments->map(function ($sopDoc) use ($uploadedDocs) {
                    $clientDoc = $uploadedDocs->get($sopDoc->id);

                    return [
                        'type' => 'sop_legal',
                        'sop_id' => $sopDoc->id,
                        'requirement_id' => null,
                        'name' => $sopDoc->name,
                        'description' => $sopDoc->description,
                        'category' => $sopDoc->category,
                        'is_required' => $sopDoc->is_required,
                        'is_uploaded' => $clientDoc !== null,
                        'uploaded_document' => $clientDoc,
                        'file_path' => $clientDoc?->file_path,
                        'uploaded_at' => $clientDoc?->updated_at,
                        'uploaded_by' => $clientDoc?->user,
                        'status' => $clientDoc?->status ?? 'required',
                        'due_date' => null,
                        'admin_notes' => $clientDoc?->admin_notes,
                    ];
                });
            }
        );
    }

    /**
     * Get additional document requirements created by admin
     * These are custom requirements specific to this client
     */
    public function getAdditionalRequirementsProperty(): Collection
    {
        if (!$this->selectedClientId) {
            return collect();
        }

        $userId = auth()->id();
        $clientId = $this->selectedClientId;

        return Cache::remember(
            "overview_requirements_{$userId}_{$clientId}",
            $this->cacheDuration,
            function () use ($clientId) {
                // Get all requirements for this client
                $requirements = ClientDocumentRequirement::query()
                    ->where('client_id', $clientId)
                    ->select([
                        'id',
                        'client_id',
                        'name',
                        'description',
                        'category',
                        'is_required',
                        'status',
                        'due_date'
                    ])
                    ->orderBy('is_required', 'desc')
                    ->orderBy('due_date')
                    ->orderBy('created_at', 'desc')
                    ->get();

                // Get uploaded documents for these requirements
                $requirementIds = $requirements->pluck('id')->toArray();

                $uploadedDocs = collect();
                if (!empty($requirementIds)) {
                    $uploadedDocs = ClientDocument::query()
                        ->where('client_id', $clientId)
                        ->whereIn('requirement_id', $requirementIds)
                        ->whereNotNull('file_path')
                        ->select([
                            'id',
                            'requirement_id',
                            'file_path',
                            'original_filename',
                            'status',
                            'user_id',
                            'updated_at',
                            'admin_notes'
                        ])
                        ->with(['user:id,name'])
                        ->get()
                        ->keyBy('requirement_id');
                }

                // Map requirements with upload status
                return $requirements->map(function ($req) use ($uploadedDocs) {
                    $clientDoc = $uploadedDocs->get($req->id);

                    // Document is uploaded if there's a file, regardless of requirement status
                    $isUploaded = $clientDoc !== null;

                    return [
                        'type' => 'requirement',
                        'sop_id' => null,
                        'requirement_id' => $req->id,
                        'name' => $req->name,
                        'description' => $req->description,
                        'category' => $req->category,
                        'is_required' => $req->is_required,
                        'is_uploaded' => $isUploaded,
                        'uploaded_document' => $clientDoc,
                        'file_path' => $clientDoc?->file_path,
                        'uploaded_at' => $clientDoc?->updated_at,
                        'uploaded_by' => $clientDoc?->user,
                        'status' => $isUploaded ? ($clientDoc->status ?? 'pending_review') : 'required',
                        'due_date' => $req->due_date,
                        'requirement_status' => $req->status, // pending, fulfilled, waived
                        'admin_notes' => $clientDoc?->admin_notes,
                    ];
                });
            }
        );
    }

    /**
     * Get combined document checklist (SOP + Requirements)
     * This merges both sources for a unified view
     */
    public function getAllDocumentsChecklistProperty(): Collection
    {
        $sopChecklist = $this->documentChecklist;
        $requirements = $this->additionalRequirements;

        // Merge both collections
        return $sopChecklist->concat($requirements);
    }

    /**
     * Get rejected documents that need re-upload
     */
    public function getRejectedDocumentsProperty(): Collection
    {
        return $this->allDocumentsChecklist->filter(function ($doc) {
            // Document is rejected
            return $doc['is_uploaded'] && $doc['status'] === 'rejected';
        })->values();
    }

    /**
     * Get pending documents (not uploaded) from all sources
     */
    public function getPendingDocumentsProperty(): Collection
    {
        return $this->allDocumentsChecklist->filter(function ($doc) {
            // Not uploaded yet
            if (!$doc['is_uploaded']) {
                // For requirements, also check if not waived
                if ($doc['type'] === 'requirement') {
                    return ($doc['requirement_status'] ?? 'pending') !== 'waived';
                }
                return true;
            }
            return false;
        })->values();
    }

    /**
     * Get all stats in a single optimized query batch
     * Using raw SQL for maximum performance
     */
    public function getStatsProperty(): array
    {
        if (!$this->selectedClientId) {
            return [
                'projects' => ['total' => 0, 'active' => 0, 'completed' => 0, 'pending' => 0],
                'tax_reports' => ['total' => 0, 'reported' => 0, 'pending' => 0, 'completion_percentage' => 0],
            ];
        }

        $userId = auth()->id();
        $clientId = $this->selectedClientId;

        return Cache::remember(
            "overview_stats_{$userId}_{$clientId}",
            $this->cacheDuration,
            function () use ($clientId) {
                // Single query for project stats, bucketed by status category
                $projectStats = DB::table('projects')
                    ->leftJoin('project_statuses', 'projects.status', '=', 'project_statuses.key')
                    ->where('projects.client_id', $clientId)
                    ->selectRaw("
                        COUNT(*) as total,
                        SUM(CASE WHEN project_statuses.category = 'active' THEN 1 ELSE 0 END) as active,
                        SUM(CASE WHEN project_statuses.category = 'done' THEN 1 ELSE 0 END) as completed,
                        SUM(CASE WHEN project_statuses.category = 'not_started' THEN 1 ELSE 0 END) as pending
                    ")
                    ->first();

                // Single query for tax report stats
                $taxStats = DB::table('tax_calculation_summaries')
                    ->join('tax_reports', 'tax_calculation_summaries.tax_report_id', '=', 'tax_reports.id')
                    ->where('tax_reports.client_id', $clientId)
                    ->selectRaw("
                        COUNT(*) as total,
                        SUM(CASE WHEN tax_calculation_summaries.report_status = 'Sudah Lapor' THEN 1 ELSE 0 END) as reported,
                        SUM(CASE WHEN tax_calculation_summaries.report_status = 'Belum Lapor' THEN 1 ELSE 0 END) as pending
                    ")
                    ->first();

                $taxTotal = $taxStats->total ?? 0;
                $taxReported = $taxStats->reported ?? 0;

                return [
                    'projects' => [
                        'total' => (int) ($projectStats->total ?? 0),
                        'active' => (int) ($projectStats->active ?? 0),
                        'completed' => (int) ($projectStats->completed ?? 0),
                        'pending' => (int) ($projectStats->pending ?? 0),
                    ],
                    'tax_reports' => [
                        'total' => (int) $taxTotal,
                        'reported' => (int) $taxReported,
                        'pending' => (int) ($taxStats->pending ?? 0),
                        'completion_percentage' => $taxTotal > 0 ? round(($taxReported / $taxTotal) * 100) : 0,
                    ],
                ];
            }
        );
    }

    /**
     * Shortcut accessors for stats
     */
    public function getProjectStatsProperty(): array
    {
        return $this->stats['projects'];
    }

    public function getTaxReportStatsProperty(): array
    {
        return $this->stats['tax_reports'];
    }

    /**
     * Download document
     */
    public function downloadDocument(int $documentId)
    {
        $document = ClientDocument::query()
            ->with(['client', 'requirement', 'sopLegalDocument'])
            ->where('id', $documentId)
            ->where('client_id', $this->selectedClientId)
            ->firstOrFail();

        if ($document->file_path && Storage::disk('public')->exists($document->file_path)) {
            return Storage::disk('public')->download(
                $document->file_path,
                $document->getDownloadFilename()
            );
        }

        Notification::make()
            ->title('File tidak ditemukan')
            ->body('Berkas dokumen ini tidak ada di server. Hubungi tim Kisantra.')
            ->warning()
            ->send();
    }

    /**
     * Refresh the overview data
     */
    public function refresh()
    {
        $this->clearClientCache();
        $this->clients = $this->clientContext()->clients();
    }

    /**
     * Get recent activities for the selected client
     */
    public function getRecentActivitiesProperty(): Collection
    {
        if (!$this->selectedClientId) {
            return collect();
        }

        return UserActivity::where('client_id', $this->selectedClientId)
            ->with('user:id,name')
            ->latest()
            ->limit(15)
            ->get();
    }

    public function render()
    {
        return view('livewire.client.panel.overview-tab', [
            'selectedClient' => $this->selectedClient,
            'allDocumentsChecklist' => $this->allDocumentsChecklist,
            'pendingDocuments' => $this->pendingDocuments,
            'rejectedDocuments' => $this->rejectedDocuments,
            'projectStats' => $this->projectStats,
            'taxReportStats' => $this->taxReportStats,
            'recentActivities' => $this->recentActivities,
        ]);
    }
}
