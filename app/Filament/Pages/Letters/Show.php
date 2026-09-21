<?php

namespace App\Filament\Pages\Letters;

use App\Models\Letter;
use App\Services\LetterService;
use Filament\Forms;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\RawJs;
use Filament\Support\Enums\MaxWidth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\HtmlString;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

/**
 * One letter: fill in the fields (left), live letterhead preview (right),
 * submit for signature, and the approval box for signers.
 */
class Show extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-document-text';
    protected static bool $shouldRegisterNavigation = false;
    protected static ?string $slug = 'surat/{record}';
    protected static string $view = 'filament.pages.letters.show';

    public Letter $record;

    /** Form state (subject, letter_date, project_id, values.*); autosaved while draft. */
    public ?array $data = [];
    public string $rejectNote = '';

    /** Signature image upload state (sign modal). */
    public ?array $signature = ['signature_path' => null];

    protected function getForms(): array
    {
        return ['form', 'signatureForm'];
    }

    public function signatureForm(Form $form): Form
    {
        return $form->statePath('signature')->schema([
            Forms\Components\FileUpload::make('signature_path')
                ->label('Gambar tanda tangan')
                ->helperText('PNG dengan latar transparan, hasil pindaian tanda tangan basah. Dicetak di atas nama Anda pada surat.')
                ->image()->imagePreviewHeight('96')
                ->disk('public')->directory('signatures')->visibility('public')
                ->acceptedFileTypes(['image/png', 'image/jpeg', 'image/webp'])
                ->maxSize(1024)
                ->getUploadedFileNameForStorageUsing(fn ($file) => 'user-' . auth()->id() . '-' . time() . '.' . $file->getClientOriginalExtension()),
        ]);
    }

    public function saveSignature(): void
    {
        $state = $this->signatureForm->getState();
        $path = $state['signature_path'] ?? null;

        if (! $path) {
            Notification::make()->title('Pilih gambar tanda tangan dulu')->warning()->send();

            return;
        }

        $user = auth()->user();
        if ($user->signature_path && $user->signature_path !== $path) {
            \Illuminate\Support\Facades\Storage::disk('public')->delete($user->signature_path);
        }
        $user->forceFill(['signature_path' => $path])->save();

        Notification::make()->title('Tanda tangan tersimpan')->body('Akan dipakai pada setiap surat yang Anda tandatangani.')->success()->send();
    }


    public static function canAccess(): bool
    {
        return auth()->user()->can('surat.*');
    }

    public function mount(Letter $record): void
    {
        $this->record = $record->load(['template', 'client', 'project', 'signatures.user', 'creator', 'requestedDocuments']);

        $this->form->fill([
            'subject'     => $this->record->subject,
            'letter_date' => $this->record->letter_date?->toDateString(),
            'project_id'  => $this->record->project_id,
            'values'      => $this->record->values ?? [],
            'signers'     => $this->record->signers ?? [],
        ]);
        $this->signatureForm->fill(['signature_path' => auth()->user()->signature_path]);
    }

    /* ------------------------------------------------------------------ */
    /* Form                                                                */
    /* ------------------------------------------------------------------ */

    public function form(Form $form): Form
    {
        $editable = $this->record->isEditable();

        $schema = [
            Forms\Components\TextInput::make('subject')->label('Perihal')->required()
                ->live(onBlur: true)->disabled(! $editable),
            Forms\Components\Grid::make(2)->schema([
                Forms\Components\DatePicker::make('letter_date')->label('Tanggal surat')
                    ->native(false)->displayFormat('d M Y')->closeOnDateSelection()
                    ->live()->disabled(! $editable),
                Forms\Components\Select::make('project_id')->label('Proyek')
                    ->options($this->projectOptions)->native(false)->searchable()->placeholder('Tidak terkait')
                    ->live()->disabled(! $editable),
            ]),
        ];

        foreach ($this->fields as $key => $field) {
            $schema[] = $this->makeField($key, $field, $editable);
        }

        // One select per signer slot, limited to users holding the slot's roles.
        $service = app(LetterService::class);
        $signerSelects = [];
        foreach ($this->record->template->signerSlots() as $slot) {
            $signerSelects[] = Forms\Components\Select::make('signers.' . $slot['order'])
                ->label('Penandatangan: ' . $slot['label'])
                ->options($service->signerCandidates($slot['roles']))
                ->searchable()->native(false)->required()
                ->placeholder('Pilih ' . $slot['label'])
                ->helperText('Nama, jabatan, dan tanda tangannya langsung tampil di naskah. Hanya orang ini yang bisa menandatangani slot ini.')
                ->live()->disabled(! $editable);
        }
        if ($signerSelects) {
            $schema[] = Forms\Components\Section::make('Penandatangan')->compact()->schema($signerSelects);
        }

        return $form->statePath('data')->schema($schema);
    }

    protected function makeField(string $key, array $field, bool $editable): Forms\Components\Component
    {
        $name = "values.{$key}";
        $label = $field['label'];
        $required = (bool) $field['required'];

        $component = match ($field['type']) {
            'textarea' => Forms\Components\Textarea::make($name)->rows(4)->autosize()
                ->placeholder($field['placeholder'])->live(onBlur: true),
            'list' => Forms\Components\Textarea::make($name)->rows(4)->autosize()
                ->placeholder($field['placeholder'] ?: 'Satu butir per baris')
                ->helperText($field['help'] ?: 'Satu butir per baris; nomor ditambahkan otomatis.')
                ->extraInputAttributes(['class' => 'font-mono text-[13px]'])->live(onBlur: true),
            'date' => Forms\Components\DatePicker::make($name)->native(false)->displayFormat('d M Y')
                ->closeOnDateSelection()->placeholder('Pilih tanggal')->live(),
            'select' => Forms\Components\Select::make($name)->options($field['options'])->native(false)
                ->placeholder('Pilih…')->live(),
            'currency' => Forms\Components\TextInput::make($name)->prefix('Rp')
                ->mask(RawJs::make('$money($input, \',\', \'.\', 0)'))->stripCharacters('.')
                ->placeholder($field['placeholder'] ?: '0')->inputMode('numeric')->live(onBlur: true),
            'number' => Forms\Components\TextInput::make($name)->numeric()
                ->placeholder($field['placeholder'])->live(onBlur: true),
            'checklist' => Forms\Components\View::make('filament.pages.letters.partials.checklist')
                ->viewData([
                    'letter'     => $this->record,
                    'key'        => $key,
                    'field'      => $field,
                    'editable'   => $editable,
                    'canManage'  => $this->canManage,
                    'docOptions' => $this->documentOptions($key),
                ]),
            default => Forms\Components\TextInput::make($name)->placeholder($field['placeholder'])->live(onBlur: true),
        };

        if ($component instanceof Forms\Components\Field) {
            $component->label($label)->required($required)->disabled(! $editable);
            if ($field['help'] && $field['type'] !== 'list') {
                $component->helperText($field['help']);
            }
        }

        return $component;
    }

    public function getTitle(): string
    {
        return $this->record->subject;
    }

    public function getMaxContentWidth(): MaxWidth
    {
        return MaxWidth::Full;
    }

    public function getBreadcrumbs(): array
    {
        return [
            Index::getUrl() => 'Surat & Berita Acara',
            '#' => $this->record->display_number,
        ];
    }

    /* ------------------------------------------------------------------ */
    /* Draft editing                                                       */
    /* ------------------------------------------------------------------ */

    public function updated(string $name): void
    {
        if ($this->record->isEditable() && str_starts_with($name, 'data.')) {
            $this->persistDraft();
        }
    }

    /**
     * Save the raw form state as the draft. Masked inputs (currency) keep their
     * display characters in $data, so they are normalised here.
     */
    protected function persistDraft(): void
    {
        $data = $this->data ?? [];
        $values = $data['values'] ?? [];

        foreach ($this->fields as $key => $field) {
            if ($field['type'] === 'currency' && isset($values[$key]) && $values[$key] !== '') {
                $values[$key] = (int) preg_replace('/\D+/', '', (string) $values[$key]);
            }
        }

        $signers = collect($data['signers'] ?? [])
            ->mapWithKeys(fn ($id, $order) => [(string) $order => $id ? (int) $id : null])
            ->all();

        $this->record->forceFill([
            'values'      => $values,
            'signers'     => $signers,
            'subject'     => trim((string) ($data['subject'] ?? '')) !== '' ? $data['subject'] : $this->record->template->name,
            'letter_date' => $data['letter_date'] ?: now()->toDateString(),
            'project_id'  => $data['project_id'] ?: null,
        ])->save();

        $this->record->load('project');
    }

    public function getFieldsProperty(): array
    {
        return $this->record->template->fieldDefinitions();
    }

    public function getProjectOptionsProperty(): array
    {
        return \App\Models\Project::query()
            ->where('client_id', $this->record->client_id)
            ->orderByDesc('created_at')
            ->pluck('name', 'id')
            ->all();
    }

    public function getPreviewProperty(): HtmlString
    {
        return app(LetterService::class)->renderDocument($this->record->fresh(['template', 'client', 'project', 'signatures.user', 'requestedDocuments']));
    }

    public function getMissingProperty(): array
    {
        return app(LetterService::class)->missingRequiredFields($this->record);
    }

    public function getCanSignProperty(): bool
    {
        return $this->record->canBeSignedBy(auth()->user());
    }

    public function getCanManageProperty(): bool
    {
        $user = auth()->user();

        return $this->record->created_by === $user->id
            || $user->hasAnyRole(['super-admin', 'direktur', 'project-manager']);
    }

    /* ------------------------------------------------------------------ */
    /* Requested-document checklist                                        */
    /* ------------------------------------------------------------------ */

    /**
     * Master list for the picker: [['group' => 'Perpajakan', 'items' => [['id', 'name', 'used']]], ...].
     * Items already on this field's checklist are flagged rather than hidden so
     * the picker can show them ticked.
     */
    public function documentOptions(string $fieldKey): array
    {
        $used = $this->record->checklist($fieldKey)->pluck('requested_document_type_id')->filter()->flip();

        return collect(\App\Models\RequestedDocumentType::groupedOptions())
            ->map(fn ($items, $group) => [
                'group' => $group,
                'items' => collect($items)->map(fn ($name, $id) => [
                    'id'   => $id,
                    'name' => $name,
                    'used' => $used->has($id),
                ])->values()->all(),
            ])
            ->values()
            ->all();
    }

    public function addDocument(string $fieldKey, int $typeId, LetterService $service): void
    {
        if (! $this->canManage) {
            return;
        }

        try {
            $service->addRequestedDocument($this->record, $fieldKey, $typeId);
            $this->record->load('requestedDocuments');
        } catch (Throwable $e) {
            Notification::make()->title($e->getMessage())->warning()->send();
        }
    }

    public function addCustomDocument(string $fieldKey, string $name, LetterService $service): void
    {
        if (! $this->canManage) {
            return;
        }

        try {
            $service->addRequestedDocument($this->record, $fieldKey, null, $name);
            $this->record->load('requestedDocuments');
        } catch (Throwable $e) {
            Notification::make()->title($e->getMessage())->warning()->send();
        }
    }

    public function updateDocumentNote(int $id, ?string $note, LetterService $service): void
    {
        if (! $this->canManage) {
            return;
        }

        $doc = $this->record->requestedDocuments->firstWhere('id', $id);
        if ($doc) {
            try {
                $service->updateRequestedDocumentNote($this->record, $doc, $note);
                $this->record->load('requestedDocuments');
            } catch (Throwable $e) {
                Notification::make()->title($e->getMessage())->warning()->send();
            }
        }
    }

    public function removeDocument(int $id, LetterService $service): void
    {
        if (! $this->canManage) {
            return;
        }

        $doc = $this->record->requestedDocuments->firstWhere('id', $id);
        if ($doc) {
            try {
                $service->removeRequestedDocument($this->record, $doc);
                $this->record->load('requestedDocuments');
                // Let the picker re-enable the master entry without a reload.
                $this->dispatch('letter-doc-removed', typeId: $doc->requested_document_type_id);
            } catch (Throwable $e) {
                Notification::make()->title($e->getMessage())->warning()->send();
            }
        }
    }

    public function toggleReceived(int $id, LetterService $service): void
    {
        if (! $this->canManage) {
            return;
        }

        $doc = $this->record->requestedDocuments->firstWhere('id', $id);
        if ($doc) {
            $service->toggleReceived($this->record, $doc, auth()->user());
            $this->record->load('requestedDocuments');
        }
    }

    /* ------------------------------------------------------------------ */
    /* Actions                                                             */
    /* ------------------------------------------------------------------ */

    public function submit(LetterService $service): void
    {
        if (! $this->canManage) {
            return;
        }

        try {
            $this->persistDraft();
            $this->record = $service->submit($this->record)->load(['template', 'client', 'project', 'signatures.user', 'creator']);
            Notification::make()->title('Surat diajukan')->body("Nomor {$this->record->number} · menunggu {$this->record->currentSignature()?->label}")->success()->send();
        } catch (Throwable $e) {
            Notification::make()->title('Belum bisa diajukan')->body($e->getMessage())->danger()->send();
        }
    }

    public function sign(LetterService $service): void
    {
        try {
            $this->record = $service->sign($this->record, auth()->user());
            $this->dispatch('close-modal', id: 'ttd-surat');
            $next = $this->record->currentSignature();
            Notification::make()
                ->title('Tanda tangan tersimpan')
                ->body($next ? "Giliran berikutnya: {$next->label}" : 'Surat selesai dan siap diunduh.')
                ->success()->send();
        } catch (Throwable $e) {
            Notification::make()->title('Gagal menandatangani')->body($e->getMessage())->danger()->send();
        }
    }

    public function reject(LetterService $service): void
    {
        $note = trim($this->rejectNote);
        if ($note === '') {
            Notification::make()->title('Tulis alasan penolakan')->warning()->send();

            return;
        }

        try {
            $this->record = $service->reject($this->record, auth()->user(), $note);
            $this->rejectNote = '';
            $this->dispatch('close-modal', id: 'tolak-surat');
            Notification::make()->title('Surat dikembalikan ke pembuat')->success()->send();
        } catch (Throwable $e) {
            Notification::make()->title('Gagal menolak')->body($e->getMessage())->danger()->send();
        }
    }

    public function cancel(LetterService $service): void
    {
        if (! $this->canManage) {
            return;
        }

        try {
            $this->record = $service->cancel($this->record)->load(['template', 'client', 'project', 'signatures.user', 'creator']);
            $this->dispatch('close-modal', id: 'batal-surat');
            Notification::make()->title('Surat dibatalkan')->success()->send();
        } catch (Throwable $e) {
            Notification::make()->title('Tidak bisa dibatalkan')->body($e->getMessage())->danger()->send();
        }
    }

    public function delete(LetterService $service): void
    {
        if (! $this->canManage) {
            return;
        }

        try {
            $subject = $this->record->subject;
            $service->delete($this->record);

            Notification::make()->title('Draft dihapus')->body($subject)->success()->send();
            $this->redirect(Index::getUrl(), navigate: true);
        } catch (Throwable $e) {
            $this->dispatch('close-modal', id: 'hapus-surat');
            Notification::make()->title('Tidak bisa dihapus')->body($e->getMessage())->danger()->send();
        }
    }

    public function downloadPdf(LetterService $service): StreamedResponse
    {
        $path = $service->pdfPath($this->record);

        return Storage::disk(config('letter.pdf_disk'))->download($path, $service->downloadFileName($this->record));
    }
}
