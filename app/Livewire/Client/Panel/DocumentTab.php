<?php

namespace App\Livewire\Client\Panel;

use App\Filament\Resources\ClientResource;
use App\Models\Client;
use App\Models\ClientRequirementGroup;
use App\Models\ClientDocument;
use App\Models\ClientDocumentRequirement;
use App\Models\SopLegalDocument;
use Filament\Notifications\Notification;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Textarea;
use App\Support\Ui\Tone;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\HtmlString;
use Livewire\Component;
use Livewire\Attributes\Locked;
use Livewire\Attributes\On;
use App\Livewire\Client\Panel\Concerns\UsesCurrentClient;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class DocumentTab extends Component implements HasForms
{
    use InteractsWithForms;
    use UsesCurrentClient;

    public $clients = [];

    public $checklist = [];
    public $additionalDocuments = [];
    public $requiredAdditionalDocuments = [];
    public $groups = [];

    // Search and Filter
    public $searchQuery = '';
    public $statusFilter = 'all';

    /** Status filter options (key => label), in menu order. */
    public const STATUS_FILTERS = [
        'all' => 'Semua Status',
        'valid' => 'Valid',
        'pending' => 'Dalam Review',
        'not_uploaded' => 'Belum Diunggah',
        'expired' => 'Kedaluwarsa',
        'rejected' => 'Ditolak',
    ];

    /** Badge per status key: [label, tone, dot]. Document statuses first, then requirement statuses. */
    private const STATUS_BADGES = [
        'valid' => ['Valid', Tone::BLUE, true],
        'pending_review' => ['Menunggu Review', Tone::OUTLINE, true],
        'rejected' => ['Ditolak', Tone::RED, true],
        'expired' => ['Kedaluwarsa', Tone::RED, true],
        'not_uploaded' => ['Belum Diunggah', Tone::NEUTRAL, false],
        'required' => ['Belum Diunggah', Tone::NEUTRAL, false],
        'overdue' => ['Terlambat', Tone::RED, true],
        'fulfilled' => ['Terpenuhi', Tone::BLUE, true],
        'pending' => ['Menunggu', Tone::OUTLINE, true],
        'waived' => ['Dikecualikan', Tone::NEUTRAL, false],
    ];

    // Modal state
    #[Locked]
    public $currentClient = null;
    public $selectedSopId = null;
    public $uploadFile = [];
    /** Name of the file as the client chose it (FileUpload::storeFileNamesIn). */
    public $uploadFileNames = [];
    public $documentName = '';
    public $isAdditionalDocument = false;
    public $selectedRequirementId = null;
    public $documentToDelete = null;
    public $documentDescription = '';

    // Preview state
    #[Locked]
    public $previewDocument = null;

    /** Set when the page is opened with ?unggah=sop-{id} or ?unggah=req-{id} (links from Ringkasan). */
    #[Locked]
    public bool $openUploadOnLoad = false;

    /** Latest uploaded document per requirement id, loaded once per request (see latestRequirementDocuments()). */
    protected ?Collection $latestRequirementDocumentsMemo = null;

    // Cache duration (5 minutes)
    protected int $cacheDuration = 300;

    public function mount()
    {
        $this->loadClients();

        if ($this->selectedClientId) {
            $this->loadClientData($this->selectedClientId);
            $this->openUploadFromQuery((string) request()->query('unggah', ''));
        }
    }

    /**
     * Ringkasan links straight to an upload: "sop-12" (SOP legal document) or
     * "req-7" (requirement). Anything that isn't one of this company's items
     * is ignored, so the page simply opens without the dialog.
     */
    protected function openUploadFromQuery(string $target): void
    {
        if (! preg_match('/^(sop|req)-(\d+)$/', $target, $match) || ! $this->currentClient) {
            return;
        }

        $id = (int) $match[2];

        if ($match[1] === 'sop' && $this->currentClient->getApplicableSopDocuments()->contains('id', $id)) {
            $this->prepareUpload(sopId: $id);
        } elseif ($match[1] === 'req' && ClientDocumentRequirement::where('client_id', $this->selectedClientId)->whereKey($id)->exists()) {
            $this->prepareUpload(requirementId: $id);
        } else {
            return;
        }

        // The modal's Alpine component isn't listening yet during mount; the view opens it after init.
        $this->openUploadOnLoad = true;
    }

    public function loadClients()
    {
        $this->clients = $this->clientContext()->clients();
    }

    /**
     * Clear cache for specific client
     */
    protected function clearClientCache($clientId)
    {
        $userId = auth()->id();
        Cache::forget("client_doc_stats_{$userId}_{$clientId}");
        Cache::forget("client_checklist_{$userId}_{$clientId}");
        Cache::forget("client_additional_docs_{$userId}_{$clientId}");
        Cache::forget("client_requirements_{$userId}_{$clientId}");
        Cache::forget("client_groups_{$userId}_{$clientId}");
    }

    public function loadClientData($clientId)
    {
        $this->clientContext()->authorize($clientId);

        $this->currentClient = Client::find($clientId);

        if (!$this->currentClient) {
            return;
        }

        $this->checklist = $this->getClientChecklist();
        $this->loadAdditionalDocuments();
        $this->loadRequiredAdditionalDocuments();
        $this->loadGroups();
    }

    /**
     * Get client checklist with caching
     */
    protected function getClientChecklist()
    {
        if (!$this->currentClient) {
            return collect([]);
        }

        $userId = auth()->id();
        $clientId = $this->currentClient->id;

        return Cache::remember(
            "client_checklist_{$userId}_{$clientId}",
            $this->cacheDuration,
            fn() => $this->currentClient->getLegalDocumentsChecklist()
        );
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                // For Legal Documents (SOP-based)
                Select::make('selectedSopId')
                    ->label('Jenis Dokumen')
                    ->options(function () {
                        if ($this->isAdditionalDocument || $this->selectedRequirementId || !$this->currentClient) {
                            return [];
                        }
                        return $this->currentClient->getApplicableSopDocuments()
                            ->pluck('name', 'id');
                    })
                    ->searchable()
                    ->required()
                    ->visible(fn() => !$this->isAdditionalDocument && !$this->selectedRequirementId),

                // For uploading to a requirement
                Select::make('selectedRequirementId')
                    ->label('Untuk Persyaratan')
                    ->options(function () {
                        if (!$this->currentClient)
                            return [];
                        return $this->currentClient->documentRequirements()
                            ->pluck('name', 'id');
                    })
                    ->required()
                    ->visible(fn() => $this->selectedRequirementId)
                    ->disabled(),

                // For additional documents
                TextInput::make('documentName')
                    ->label('Nama Dokumen')
                    ->placeholder('Masukkan nama dokumen')
                    ->required()
                    ->maxLength(150)
                    ->visible(fn() => $this->isAdditionalDocument && !$this->selectedRequirementId),

                FileUpload::make('uploadFile')
                    ->label('File Dokumen')
                    ->acceptedFileTypes([
                        'application/pdf',
                        'image/*',
                        'application/msword',
                        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                        'application/vnd.ms-excel',
                        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                        'text/csv',
                    ])
                    ->maxSize(10240) // 10MB
                    ->required()
                    ->placeholder(new HtmlString('Seret file ke sini atau <span class="filepond--label-action">pilih file</span>'))
                    ->helperText('Format: PDF, JPG, PNG, DOC, DOCX, XLS, XLSX, CSV (Maks. 10MB)')
                    ->disk('public')
                    ->directory(fn() => $this->getUploadDirectory())
                    // getState() already stores the file under a random name; keep the client's name for display and download.
                    ->storeFileNamesIn('uploadFileNames')
                    ->visibility('private')
                    ->uploadingMessage('Sedang mengunggah...'),

                Textarea::make('documentDescription')
                    ->label('Catatan')
                    ->placeholder('Tambahkan catatan untuk dokumen ini (opsional)')
                    ->rows(3)
                    ->maxLength(1000),
            ]);
    }

    /**
     * Get the upload directory based on client and document type
     * Format: clients/{slug}/Legal or clients/{slug}/Additional
     */
    protected function getUploadDirectory(): string
    {
        if (!$this->currentClient) {
            return 'temp';
        }

        // Create slug from client name
        $clientSlug = Str::slug($this->currentClient->name);

        // Determine subfolder based on document type
        if ($this->isAdditionalDocument || $this->selectedRequirementId) {
            // Additional documents or requirements go to 'Additional' folder
            return "clients/{$clientSlug}/Additional";
        } else {
            // Legal/SOP documents go to 'Legal' folder
            return "clients/{$clientSlug}/Legal";
        }
    }

    public function loadAdditionalDocuments()
    {
        if (!$this->currentClient) {
            $this->additionalDocuments = collect([]);
            return;
        }

        $userId = auth()->id();
        $clientId = $this->currentClient->id;

        $this->additionalDocuments = Cache::remember(
            "client_additional_docs_{$userId}_{$clientId}",
            $this->cacheDuration,
            function () {
                return $this->currentClient->clientDocuments()
                    ->additionalDocuments()
                    ->with('user:id,name')
                    ->select([
                        'id',
                        'client_id',
                        'user_id',
                        'file_path',
                        'original_filename',
                        'description',
                        'status',
                        'admin_notes',
                        'created_at',
                        'updated_at'
                    ])
                    ->latest()
                    ->get();
            }
        );
    }

    public function loadRequiredAdditionalDocuments()
    {
        if (!$this->currentClient) {
            $this->requiredAdditionalDocuments = collect([]);
            return;
        }

        $userId = auth()->id();
        $clientId = $this->currentClient->id;

        $this->requiredAdditionalDocuments = Cache::remember(
            "client_requirements_{$userId}_{$clientId}",
            $this->cacheDuration,
            function () {
                return $this->currentClient->documentRequirements()
                    ->with([
                        'createdBy:id,name',
                        'documents' => function ($query) {
                            $query->latest()
                                ->with('user:id,name')
                                ->select([
                                    'id',
                                    'client_id',
                                    'user_id',
                                    'requirement_id',
                                    'file_path',
                                    'original_filename',
                                    'status',
                                    'admin_notes',
                                    'created_at',
                                    'updated_at'
                                ]);
                        }
                    ])
                    ->latest()
                    ->get();
            }
        );
    }

    public function loadGroups()
    {
        if (!$this->currentClient) {
            $this->groups = [];
            return;
        }

        $userId = auth()->id();
        $clientId = $this->currentClient->id;

        $this->groups = Cache::remember(
            "client_groups_{$userId}_{$clientId}",
            $this->cacheDuration,
            function () {
                return $this->currentClient->requirementGroups()
                    ->active()
                    ->orderBy('name')
                    ->get()
                    ->toArray();
            }
        );
    }

    public function openUploadModal($clientId, $sopId = null, $isAdditional = false, $requirementId = null)
    {
        // The client id from the view is ignored beyond this check: uploads always go to the selected company.
        abort_unless((int) $clientId === $this->selectedClientId, 403);
        abort_if($requirementId && ! ClientDocumentRequirement::where('client_id', $this->selectedClientId)->whereKey($requirementId)->exists(), 403);

        $this->prepareUpload(
            sopId: $sopId ? (int) $sopId : null,
            isAdditional: (bool) $isAdditional,
            requirementId: $requirementId ? (int) $requirementId : null,
        );

        $this->dispatch('open-modal', id: 'upload-document-modal');
    }

    protected function prepareUpload(?int $sopId = null, bool $isAdditional = false, ?int $requirementId = null): void
    {
        $this->currentClient = Client::find($this->selectedClientId);
        $this->selectedSopId = $sopId;
        $this->isAdditionalDocument = $isAdditional;
        $this->selectedRequirementId = $requirementId;
        $this->resetModalFields();
    }

    public function closeUploadModal()
    {
        $this->resetModalFields();
        $this->dispatch('close-modal', id: 'upload-document-modal');
    }

    private function resetModalFields()
    {
        $this->uploadFile = [];
        $this->uploadFileNames = [];
        $this->documentName = '';
        $this->documentDescription = '';
        $this->resetValidation();
    }

    public function uploadDocument()
    {
        if (!$this->currentClient) {
            Notification::make()
                ->title('Error!')
                ->body('Client tidak ditemukan')
                ->danger()
                ->send();
            return;
        }

        // Both ids are public properties, so check them again against the selected company.
        abort_if($this->selectedRequirementId && ! ClientDocumentRequirement::where('client_id', $this->selectedClientId)->whereKey($this->selectedRequirementId)->exists(), 403);
        abort_if(! $this->isAdditionalDocument && ! $this->selectedRequirementId && ! $this->currentClient->getApplicableSopDocuments()->contains('id', (int) $this->selectedSopId), 403);

        $data = $this->form->getState();

        try {
            // Handle file upload
            if (!empty($data['uploadFile'])) {
                $uploadedFile = is_array($data['uploadFile']) ? $data['uploadFile'][0] : $data['uploadFile'];

                // Get original filename
                if (is_object($uploadedFile) && method_exists($uploadedFile, 'getClientOriginalName')) {
                    $originalName = $uploadedFile->getClientOriginalName();
                } else {
                    // Single uploads keep one name (a string); multiple uploads would be keyed by path.
                    $names = $this->uploadFileNames;
                    $originalName = (is_string($names) && $names !== '' ? $names : collect($names)->get($uploadedFile)) ?? basename($uploadedFile);
                }

                // Create sanitized filename with timestamp
                $extension = pathinfo($originalName, PATHINFO_EXTENSION);
                $baseFilename = pathinfo($originalName, PATHINFO_FILENAME);
                $sanitizedFilename = Str::slug($baseFilename);
                $filename = time() . '_' . $sanitizedFilename . '.' . $extension;

                // Get storage path based on document type
                $storagePath = $this->getUploadDirectory();

                // Store file
                if (is_object($uploadedFile) && method_exists($uploadedFile, 'storeAs')) {
                    $filePath = $uploadedFile->storeAs($storagePath, $filename, 'public');
                } else {
                    $filePath = $uploadedFile;
                }

                // Create document record
                $documentData = [
                    'client_id' => $this->currentClient->id,
                    'user_id' => auth()->id(),
                    'file_path' => $filePath,
                    'original_filename' => $originalName,
                    'description' => $this->descriptionFor($data),
                    'status' => 'pending_review',
                ];

                // Link to SOP Legal Document
                if (!$this->isAdditionalDocument && !$this->selectedRequirementId && $this->selectedSopId) {
                    $documentData['sop_legal_document_id'] = (int) $this->selectedSopId;
                }

                // Link to Requirement
                if ($this->selectedRequirementId) {
                    $documentData['requirement_id'] = $this->selectedRequirementId;
                }

                // Set category for additional documents
                if ($this->isAdditionalDocument && !$this->selectedRequirementId) {
                    $documentData['document_category'] = 'additional';
                }

                ClientDocument::create($documentData);

                // Clear cache
                $this->clearClientCache($this->currentClient->id);

                // Send notifications to other client users AND management
                $this->sendDocumentUploadNotification($this->currentClient, $originalName);

                $this->closeUploadModal();
                $this->loadClientData($this->currentClient->id);
                $this->loadClients(); // Refresh stats

                Notification::make()
                    ->title('Dokumen terunggah')
                    ->body('Dokumen berhasil diunggah dan menunggu review tim Kisantra.')
                    ->success()
                    ->duration(3000)
                    ->send();
            } else {
                Notification::make()
                    ->title('Belum ada file')
                    ->body('Pilih file yang akan diunggah.')
                    ->warning()
                    ->duration(3000)
                    ->send();
            }

        } catch (\Exception $e) {
            \Log::error('Document upload error: ' . $e->getMessage());

            Notification::make()
                ->title('Gagal mengunggah')
                ->body('Gagal mengunggah dokumen: ' . $e->getMessage())
                ->danger()
                ->duration(5000)
                ->send();
        }
    }

    /**
     * client_documents has one free-text column. For an additional document it
     * holds the name on the first line and the optional note below it (see
     * splitDescription()); for SOP and requirement uploads it is the note.
     */
    protected function descriptionFor(array $data): ?string
    {
        $note = trim((string) ($data['documentDescription'] ?? '')) ?: null;

        if ($this->isAdditionalDocument && ! $this->selectedRequirementId) {
            $name = trim((string) ($data['documentName'] ?? ''));

            return $note ? $name . "\n" . $note : ($name ?: null);
        }

        return $note;
    }

    /** @return array{0: ?string, 1: ?string} first line, rest */
    protected static function splitDescription(?string $description): array
    {
        $parts = preg_split('/\R/', trim((string) $description), 2);

        return [trim($parts[0] ?? '') ?: null, trim($parts[1] ?? '') ?: null];
    }

    public function downloadDocument($documentId)
    {
        $document = ClientDocument::with(['client', 'requirement', 'sopLegalDocument'])
            ->where('client_id', $this->selectedClientId)
            ->find($documentId);

        if (!$document || !$document->file_path) {
            Notification::make()
                ->title('File tidak ditemukan')
                ->body('File dokumen tidak dapat ditemukan atau sudah dihapus.')
                ->warning()
                ->send();
            return;
        }

        if (!Storage::disk('public')->exists($document->file_path)) {
            Notification::make()
                ->title('File tidak ditemukan')
                ->body('File dokumen tidak dapat ditemukan di server.')
                ->warning()
                ->send();
            return;
        }

        return response()->download(
            storage_path('app/public/' . $document->file_path),
            $document->getDownloadFilename()
        );
    }

    public function deleteDocument()
    {
        try {
            if (!$this->documentToDelete) {
                return;
            }

            $document = ClientDocument::where('client_id', $this->selectedClientId)->find($this->documentToDelete);

            if (!$document) {
                Notification::make()
                    ->title('Error!')
                    ->body('Dokumen tidak ditemukan')
                    ->danger()
                    ->send();
                return;
            }

            // Only allow client to delete their own documents that are not yet approved
            if ($document->user_id !== auth()->id() || $document->status === 'valid') {
                Notification::make()
                    ->title('Error!')
                    ->body('Anda tidak dapat menghapus dokumen ini')
                    ->danger()
                    ->send();
                return;
            }

            // Delete file from storage
            if ($document->file_path && Storage::disk('public')->exists($document->file_path)) {
                Storage::disk('public')->delete($document->file_path);
            }

            $clientId = $document->client_id;
            $document->delete();

            // Clear cache
            $this->clearClientCache($clientId);

            $this->loadClientData($clientId);
            $this->loadClients(); // Refresh stats

            Notification::make()
                ->title('Berhasil!')
                ->body('Dokumen berhasil dihapus!')
                ->success()
                ->duration(3000)
                ->send();

            $this->closeDeleteModal();
        } catch (\Exception $e) {
            \Log::error('Document deletion error: ' . $e->getMessage());

            Notification::make()
                ->title('Error!')
                ->body('Gagal menghapus dokumen: ' . $e->getMessage())
                ->danger()
                ->duration(5000)
                ->send();
        }
    }

    public function deleteDocumentConfirm($documentId)
    {
        $document = ClientDocument::where('client_id', $this->selectedClientId)->find($documentId);

        // Check if user can delete this document
        if (!$document || $document->user_id !== auth()->id() || $document->status === 'valid') {
            Notification::make()
                ->title('Error!')
                ->body('Anda tidak dapat menghapus dokumen ini')
                ->danger()
                ->send();
            return;
        }

        $this->documentToDelete = $documentId;
        $this->dispatch('open-modal', id: 'confirm-delete-modal');
    }

    public function closeDeleteModal()
    {
        $this->documentToDelete = null;
        $this->dispatch('close-modal', id: 'confirm-delete-modal');
    }

    public function previewDocuments($documentId)
    {
        $this->previewDocument = ClientDocument::with(['user:id,name', 'requirement', 'sopLegalDocument'])
            ->where('client_id', $this->selectedClientId)
            ->find($documentId);

        if ($this->previewDocument) {
            $this->dispatch('open-modal', id: 'preview-document-modal');
        } else {
            Notification::make()
                ->title('Error!')
                ->body('Dokumen tidak ditemukan')
                ->danger()
                ->duration(3000)
                ->send();
        }
    }

    public function closePreviewModal()
    {
        $this->previewDocument = null;
        $this->dispatch('close-modal', id: 'preview-document-modal');
    }

    public function updatePreviewDocumentStatus(int $documentId, string $status): void
    {
        if (! auth()->user()?->hasAnyRole(['project-manager', 'direktur', 'super-admin', 'verificator', 'staff'])) {
            return;
        }

        if (! in_array($status, ['pending_review', 'valid', 'rejected'], true)) {
            return;
        }

        try {
            $document = ClientDocument::with(['user:id,name', 'requirement', 'sopLegalDocument'])
                ->where('client_id', $this->selectedClientId)
                ->findOrFail($documentId);

            $document->update([
                'status' => $status,
                'reviewed_by' => auth()->id(),
                'reviewed_at' => now(),
            ]);

            $this->previewDocument = $document->fresh(['user:id,name', 'requirement', 'sopLegalDocument']);

            if ($this->currentClient) {
                $this->clearClientCache($this->currentClient->id);
                $this->loadClientData($this->currentClient->id);
            }

            Notification::make()
                ->title('Status dokumen diperbarui')
                ->success()
                ->send();
        } catch (\Exception $e) {
            Notification::make()
                ->title('Error!')
                ->body('Gagal memperbarui status dokumen: ' . $e->getMessage())
                ->danger()
                ->send();
        }
    }

    /**
     * ENHANCED: Send notifications to clients AND management users
     */
    protected function sendDocumentUploadNotification(Client $client, string $filename)
    {
        try {
            $uploaderName = auth()->user()->name;
            $clientName = $client->name;
            $currentUserId = auth()->id();

            // Determine document type for better message
            $docType = 'dokumen';
            if ($this->selectedRequirementId) {
                $requirement = \App\Models\ClientDocumentRequirement::find($this->selectedRequirementId);
                $docType = $requirement ? $requirement->name : 'dokumen persyaratan';
            } elseif ($this->selectedSopId) {
                $sopDoc = \App\Models\SopLegalDocument::find($this->selectedSopId);
                $docType = $sopDoc ? $sopDoc->name : 'dokumen legal';
            } elseif ($this->isAdditionalDocument) {
                $docType = 'dokumen tambahan';
            }

            // 1️⃣ NOTIFY OTHER CLIENT USERS (existing functionality)
            $clientUsers = \App\Models\User::whereHas('userClients', function ($query) use ($client) {
                $query->where('client_id', $client->id);
            })
                ->whereHas('roles', function ($query) {
                    $query->where('name', 'client');
                })
                ->where('id', '!=', $currentUserId)
                ->get();

            // 2️⃣ NOTIFY MANAGEMENT USERS (NEW!)
            // Get all users who have access to this client but are NOT client role
            $managementUsers = \App\Models\User::whereHas('userClients', function ($query) use ($client) {
                $query->where('client_id', $client->id);
            })
                ->whereHas('roles', function ($query) {
                    $query->whereIn('name', ['project-manager', 'direktur', 'super-admin', 'verificator', 'staff']);
                })
                ->where('id', '!=', $currentUserId)
                ->where('status', 'active')
                ->get();

            // 3️⃣ NOTIFY CLIENT PIC (if assigned and not already in the list)
            $picUsers = collect([]);
            if ($client->pic_id && $client->pic_id !== $currentUserId) {
                $pic = \App\Models\User::find($client->pic_id);
                if ($pic && $pic->status === 'active') {
                    $picUsers->push($pic);
                }
            }

            // 4️⃣ NOTIFY ACCOUNT REPRESENTATIVE (if assigned and not already in the list)
            $arUsers = collect([]);
            if ($client->ar_id && $client->ar_id !== $currentUserId) {
                $ar = \App\Models\User::find($client->ar_id);
                if ($ar && $ar->status === 'active') {
                    $arUsers->push($ar);
                }
            }

            // Merge all recipients and remove duplicates
            $allRecipients = $clientUsers
                ->merge($managementUsers)
                ->merge($picUsers)
                ->merge($arUsers)
                ->unique('id');

            // 🔍 DEBUG: Log recipient information
            \Log::info('Document Upload Notification Recipients', [
                'client_id' => $client->id,
                'client_name' => $clientName,
                'uploader' => $uploaderName,
                'uploader_id' => $currentUserId,
                'document' => $filename,
                'doc_type' => $docType,
                'total_recipients' => $allRecipients->count(),
                'client_users' => $clientUsers->count(),
                'management_users' => $managementUsers->count(),
                'pic_users' => $picUsers->count(),
                'ar_users' => $arUsers->count(),
                'recipient_ids' => $allRecipients->pluck('id')->toArray(),
                'recipient_names' => $allRecipients->pluck('name')->toArray(),
            ]);

            if ($allRecipients->isEmpty()) {
                \Log::warning('No recipients found for document upload notification', [
                    'client_id' => $client->id,
                    'uploader_id' => $currentUserId,
                ]);
                return;
            }

            $clientUrl = ClientResource::getUrl('view', ['record' => $client], panel: 'admin');

            // Send notification to each recipient
            foreach ($allRecipients as $user) {
                // Customize message based on user role
                $isManagement = $user->hasAnyRole(['project-manager', 'direktur', 'super-admin', 'verificator', 'staff']);

                if ($isManagement) {
                    // Management notification
                    $title = 'Dokumen Baru dari Client';
                    $body = sprintf(
                        "<span style='color: #f59e0b; font-weight: 500;'>%s</span><br><strong>Document:</strong> %s<br><strong>Uploaded at:</strong> %s",
                        $clientName,
                        $docType,
                        now()->format('d M Y • H:i')
                    );
                } else {
                    // Client notification
                    $title = 'Dokumen Baru Diupload';
                    $body = sprintf(
                        "<span style='color: #f59e0b; font-weight: 500;'>%s</span><br><strong>Document:</strong> %s<br><strong>Uploaded at:</strong> %s",
                        $clientName,
                        $docType,
                        now()->format('d M Y • H:i')
                    );
                }

                Notification::make()
                    ->title($title)
                    ->body($body)
                    ->icon('heroicon-o-document-arrow-up')
                    ->color('info')
                    ->iconColor('primary')
                    ->info()
                    ->persistent()
                    ->actions([
                        \Filament\Notifications\Actions\Action::make('view')
                            ->label('View Client')
                            ->url($clientUrl)
                            ->markAsRead(),
                        \Filament\Notifications\Actions\Action::make('Mark As Read')
                            ->markAsRead(),
                    ])
                    ->sendToDatabase($user)
                    ->broadcast($user);

                // 🔍 DEBUG: Log each notification sent
                \Log::debug('Notification sent to user', [
                    'recipient_id' => $user->id,
                    'recipient_name' => $user->name,
                    'recipient_email' => $user->email,
                    'recipient_roles' => $user->roles->pluck('name')->toArray(),
                    'is_management' => $isManagement,
                ]);
            }

            // 🔍 DEBUG: Final success log
            \Log::info('Document upload notifications sent successfully', [
                'total_sent' => $allRecipients->count(),
                'client_id' => $client->id,
            ]);

        } catch (\Exception $e) {
            // Enhanced error logging
            \Log::error('Failed to send document upload notification', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'client_id' => $client->id ?? null,
                'uploader_id' => auth()->id() ?? null,
            ]);
        }
    }

    #[On('refresh-data')]
    public function refreshData()
    {
        $this->loadClients();
        if ($this->selectedClientId) {
            $this->clearClientCache($this->selectedClientId);
            $this->loadClientData($this->selectedClientId);
        }
    }

    public function updatedStatusFilter($value): void
    {
        if (! array_key_exists((string) $value, self::STATUS_FILTERS)) {
            $this->statusFilter = 'all';
        }
    }

    /**
     * Latest uploaded document per requirement, in one query. The view used to
     * call getLatestDocument() (a query each time) several times per row.
     *
     * @return Collection<int, ClientDocument>
     */
    protected function latestRequirementDocuments(): Collection
    {
        if ($this->latestRequirementDocumentsMemo !== null) {
            return $this->latestRequirementDocumentsMemo;
        }

        $ids = collect($this->requiredAdditionalDocuments)->pluck('id')->filter();

        return $this->latestRequirementDocumentsMemo = $ids->isEmpty()
            ? collect()
            : ClientDocument::query()
                ->where('client_id', $this->selectedClientId)
                ->whereIn('requirement_id', $ids)
                ->whereNotNull('file_path')
                ->with('user:id,name')
                ->orderByDesc('created_at')
                ->orderByDesc('id')
                ->get()
                ->unique('requirement_id')
                ->keyBy('requirement_id');
    }

    /** Status key used by the filter and the badge: the document's status, or why there is none. */
    protected function legalStatus(array $item): string
    {
        return $item['is_uploaded'] ? ($item['uploaded_document']->status ?? 'pending_review') : 'not_uploaded';
    }

    protected function requirementStatus(ClientDocumentRequirement $requirement): string
    {
        if ($document = $this->latestRequirementDocuments()->get($requirement->id)) {
            return $document->status ?? 'pending_review';
        }

        return match (true) {
            $requirement->isOverdue() => 'overdue',
            in_array($requirement->status, ['fulfilled', 'waived'], true) => $requirement->status,
            default => 'not_uploaded',
        };
    }

    protected static function isExpired(?ClientDocument $document): bool
    {
        return $document !== null
            && ($document->status === 'expired' || ($document->expired_at && Carbon::parse($document->expired_at)->isPast()));
    }

    protected function matchesStatus(string $status, ?ClientDocument $document, ?string $filter = null): bool
    {
        return match ($filter ?? $this->statusFilter) {
            'valid' => $status === 'valid',
            'pending' => $status === 'pending_review',
            'not_uploaded' => in_array($status, ['not_uploaded', 'overdue'], true),
            'expired' => self::isExpired($document),
            'rejected' => $status === 'rejected',
            default => true,
        };
    }

    protected function matchesSearch(?string ...$haystack): bool
    {
        $needle = mb_strtolower(trim((string) $this->searchQuery));

        return $needle === '' || str_contains(mb_strtolower(implode(' ', array_filter($haystack))), $needle);
    }

    /** "31 Mei 2026": the app locale is still English, the portal copy is not. */
    protected static function date($value): ?string
    {
        return $value ? Carbon::parse($value)->locale('id')->translatedFormat('d M Y') : null;
    }

    /** @return array{label: string, tone: string, dot: bool} */
    protected static function badge(string $status): array
    {
        [$label, $tone, $dot] = self::STATUS_BADGES[$status] ?? ['Tidak Diketahui', Tone::NEUTRAL, false];

        return ['label' => $label, 'tone' => $tone, 'dot' => $dot];
    }

    /** Meta parts shared by uploaded documents: number, validity date, admin note. */
    protected static function documentMeta(?ClientDocument $document, int $noteLimit = 55): array
    {
        if (! $document) {
            return [];
        }

        $meta = [];

        if ($document->document_number) {
            $meta[] = ['text' => 'No. ' . $document->document_number, 'tone' => null];
        }

        if ($document->expired_at) {
            $expired = Carbon::parse($document->expired_at)->isPast();
            $meta[] = ['text' => 's.d. ' . self::date($document->expired_at) . ($expired ? ', kedaluwarsa' : ''), 'tone' => $expired ? 'red' : null];
        }

        if ($document->admin_notes) {
            $meta[] = ['text' => 'Catatan admin: ' . Str::limit($document->admin_notes, $noteLimit), 'tone' => null];
        }

        return $meta;
    }

    protected function legalRow(array $item): array
    {
        $document = $item['is_uploaded'] ? $item['uploaded_document'] : null;

        return [
            'key' => 'sop-' . $item['sop_id'],
            'state' => $document ? 'ok' : 'missing',
            'name' => $item['name'],
            'tags' => $item['is_required'] ? [['text' => 'Wajib', 'tone' => Tone::OUTLINE]] : [],
            'meta' => self::documentMeta($document),
            'status' => self::badge($this->legalStatus($item)),
            'actions' => $document
                ? [['preview', $document->id], ['download', $document->id], ['reupload', 'sop', $item['sop_id']]]
                : [['upload', 'sop', $item['sop_id']]],
        ];
    }

    protected function requirementRow(ClientDocumentRequirement $requirement, bool $withCategory = false, bool $child = false): array
    {
        $document = $this->latestRequirementDocuments()->get($requirement->id);
        $overdue = $requirement->isOverdue();

        $tags = [];
        if ($requirement->is_required) {
            $tags[] = ['text' => 'Wajib', 'tone' => Tone::OUTLINE];
        }
        // Without a document the status badge already says "Terlambat".
        if ($overdue && $document) {
            $tags[] = ['text' => 'Terlambat', 'tone' => Tone::RED];
        }
        if ($withCategory) {
            $tags[] = ['text' => $requirement->category_badge['text'], 'tone' => Tone::NEUTRAL];
        }

        $meta = [];
        if ($requirement->due_date) {
            $meta[] = ['text' => 'Tenggat ' . self::date($requirement->due_date), 'tone' => $overdue ? 'red' : null];
        }
        if ($requirement->description) {
            $meta[] = ['text' => Str::limit($requirement->description, $withCategory ? 60 : 50), 'tone' => null];
        }

        $actions = [['upload', 'req', $requirement->id]];
        if ($document) {
            $actions = [['preview', $document->id]];
            if ($document->status !== 'valid') {
                $actions[] = ['reupload', 'req', $requirement->id];
            }
        }

        return [
            'key' => 'req-' . $requirement->id,
            'state' => $document ? 'ok' : 'missing',
            'child' => $child,
            'name' => $requirement->name,
            'tags' => $tags,
            'meta' => $meta,
            'status' => self::badge($this->requirementStatus($requirement)),
            'actions' => $actions,
        ];
    }

    protected function additionalRow(ClientDocument $document): array
    {
        [$title, $note] = self::splitDescription($document->description);
        $title ??= $document->original_filename;

        $meta = [];
        if ($document->original_filename && $document->original_filename !== $title) {
            $meta[] = ['text' => $document->original_filename, 'tone' => null, 'file' => true];
        }
        $meta[] = ['text' => self::date($document->created_at), 'tone' => null];
        if ($note) {
            $meta[] = ['text' => Str::limit($note, 60), 'tone' => null];
        }
        if ($document->admin_notes) {
            $meta[] = ['text' => 'Catatan admin: ' . Str::limit($document->admin_notes, 40), 'tone' => null];
        }

        $actions = [['preview', $document->id], ['download', $document->id]];
        // Same rule as deleteDocument(): own upload, not yet validated.
        if ($document->status !== 'valid' && (int) $document->user_id === (int) auth()->id()) {
            $actions[] = ['delete', $document->id];
        }

        return [
            'key' => 'doc-' . $document->id,
            'state' => 'ok',
            'name' => $title,
            'tags' => [],
            'meta' => $meta,
            'status' => self::badge($document->status ?? 'pending_review'),
            'actions' => $actions,
        ];
    }

    /** Everything the view draws, filtered by search and status; the summary strip is unfiltered. */
    protected function viewData(): array
    {
        $checklist = collect($this->checklist);
        $requirements = collect($this->requiredAdditionalDocuments);
        $additional = collect($this->additionalDocuments);
        $latest = $this->latestRequirementDocuments();

        // Status of every item, unfiltered: the strip and the counts in the status menu.
        $statuses = collect()
            ->concat($checklist->map(fn ($item) => [$this->legalStatus($item), $item['is_uploaded'] ? $item['uploaded_document'] : null]))
            ->concat($requirements->map(fn ($r) => [$this->requirementStatus($r), $latest->get($r->id)]))
            ->concat($additional->map(fn ($d) => [$d->status ?? 'pending_review', $d]));

        $valid = $statuses->where(0, 'valid')->count();
        $total = $statuses->count();
        $needUpload = $statuses->filter(fn ($s) => in_array($s[0], ['not_uploaded', 'overdue'], true))->count();

        $stats = [
            'total' => $total,
            'valid' => $valid,
            'review' => $statuses->where(0, 'pending_review')->count(),
            'need' => $needUpload,
            'pct' => $total > 0 ? (int) round($valid / $total * 100) : 0,
        ];

        $filterCounts = collect(self::STATUS_FILTERS)->map(
            fn ($label, $key) => $statuses->filter(fn ($s) => $this->matchesStatus($s[0], $s[1], $key))->count()
        );

        $legal = $checklist
            ->filter(fn ($item) => $this->matchesSearch($item['name'], $item['description'] ?? null)
                && $this->matchesStatus($this->legalStatus($item), $item['is_uploaded'] ? $item['uploaded_document'] : null))
            ->map(fn ($item) => $this->legalRow($item))
            ->values();

        $requirementMatches = fn (ClientDocumentRequirement $r) => $this->matchesSearch($r->name, $r->description)
            && $this->matchesStatus($this->requirementStatus($r), $latest->get($r->id));

        $groups = collect($this->groups)->map(function (array $group) use ($requirements, $requirementMatches) {
            $all = $requirements->where('group_id', $group['id']);
            $done = $all->filter(fn ($r) => ($doc = $this->latestRequirementDocuments()->get($r->id))
                ? $doc->status === 'valid'
                : $r->status === 'fulfilled')->count();
            // Cached as an array: the date is an ISO string in UTC, so bring it back to the app's timezone.
            $due = $group['due_date'] ? Carbon::parse($group['due_date'])->timezone(config('app.timezone')) : null;

            return [
                'id' => $group['id'],
                'name' => $group['name'],
                'year' => $group['year'],
                'overdue' => $due?->isPast() ?? false,
                'due' => self::date($due),
                'done' => $done,
                'total' => $all->count(),
                'pct' => $all->count() > 0 ? (int) round($done / $all->count() * 100) : 0,
                'rows' => $all->filter($requirementMatches)->map(fn ($r) => $this->requirementRow($r, child: true))->values(),
            ];
        });

        $filtering = $this->filtersActive();

        return [
            'stats' => $stats,
            'filterCounts' => $filterCounts,
            'filtering' => $filtering,
            'statusLabel' => self::STATUS_FILTERS[$this->statusFilter] ?? self::STATUS_FILTERS['all'],
            'legalRows' => $legal,
            // While filtering, a group without matches would only be noise.
            'groupBlocks' => $filtering ? $groups->filter(fn ($g) => $g['rows']->isNotEmpty())->values() : $groups,
            'groupCount' => $groups->count(),
            'otherRequirementRows' => $requirements
                ->filter(fn ($r) => is_null($r->group_id))
                ->filter($requirementMatches)
                ->map(fn ($r) => $this->requirementRow($r, withCategory: true))
                ->values(),
            'additionalRows' => $additional
                ->filter(function ($d) {
                    [$title, $note] = self::splitDescription($d->description);

                    return $this->matchesSearch($title, $note, $d->original_filename, $d->admin_notes)
                        && $this->matchesStatus($d->status ?? 'pending_review', $d);
                })
                ->map(fn ($d) => $this->additionalRow($d))
                ->values(),
            'additionalTotal' => $additional->count(),
            'preview' => $this->previewData(),
        ];
    }

    /** What the preview modal shows for $previewDocument. */
    protected function previewData(): ?array
    {
        $document = $this->previewDocument;

        if (! $document) {
            return null;
        }

        $extension = strtolower(pathinfo($document->file_path ?? '', PATHINFO_EXTENSION));
        $isAdditional = ! $document->requirement && ! $document->sopLegalDocument;
        [$title, $rest] = self::splitDescription($document->description);

        return [
            'id' => $document->id,
            'url' => $document->file_path ? asset('storage/' . $document->file_path) : null,
            'kind' => in_array($extension, ['jpg', 'jpeg', 'png', 'gif', 'webp'], true) ? 'image' : ($extension === 'pdf' ? 'pdf' : 'other'),
            'title' => $document->requirement?->name ?? $document->sopLegalDocument?->name ?? $title ?? $document->original_filename,
            'label' => $document->requirement ? 'Persyaratan' : ($document->sopLegalDocument ? 'Jenis Dokumen' : 'Dokumen'),
            'note' => $isAdditional ? $rest : (trim((string) $document->description) ?: null),
            'filename' => $document->original_filename,
            'uploader' => $document->user->name ?? '-',
            'uploadedAgo' => $document->created_at?->locale('id')->diffForHumans(),
            'uploadedAt' => $document->created_at?->locale('id')->translatedFormat('d M Y, H:i'),
            'number' => $document->document_number,
            'expires' => self::date($document->expired_at),
            'expired' => self::isExpired($document),
            'adminNotes' => $document->admin_notes,
            'badge' => self::badge($document->status ?? 'pending_review'),
        ];
    }

    protected function filtersActive(): bool
    {
        return trim((string) $this->searchQuery) !== '' || $this->statusFilter !== 'all';
    }

    /**
     * Reset filters
     */
    public function resetFilters()
    {
        $this->searchQuery = '';
        $this->statusFilter = 'all';
    }

    public function render()
    {
        return view('livewire.client.panel.document-tab', $this->selectedClientId && $this->currentClient ? $this->viewData() : []);
    }
}
