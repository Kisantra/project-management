<?php

namespace App\Services;

use App\Models\Client;
use App\Models\Letter;
use App\Models\LetterNumberCounter;
use App\Models\LetterNumberingSetting;
use App\Models\LetterRequestedDocument;
use App\Models\LetterSignature;
use App\Models\LetterTemplate;
use App\Models\RequestedDocumentType;
use App\Models\User;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Str;
use InvalidArgumentException;
use RuntimeException;

/**
 * Everything that changes a letter's state lives here so the page, the PDF
 * and any future API share one set of rules.
 */
class LetterService
{
    /* ------------------------------------------------------------------ */
    /* Lifecycle                                                           */
    /* ------------------------------------------------------------------ */

    public function createDraft(LetterTemplate $template, Client $client, User $creator, ?int $projectId = null): Letter
    {
        $defaults = collect($template->fieldDefinitions())
            ->mapWithKeys(fn ($f, $key) => [$key => $f['default']])
            ->all();

        $letter = Letter::create([
            'letter_template_id' => $template->id,
            'client_id'          => $client->id,
            'project_id'         => $projectId,
            'created_by'         => $creator->id,
            'subject'            => $template->name,
            'letter_date'        => now()->toDateString(),
            'values'             => $defaults,
            'signers'            => $this->defaultSigners($template, $creator, $projectId),
            'status'             => Letter::STATUS_DRAFT,
        ]);

        // Checklist fields keep their items in their own table, seeded from the default lines.
        foreach ($template->fieldDefinitions() as $key => $def) {
            if ($def['type'] !== 'checklist' || blank($def['default'])) {
                continue;
            }
            foreach ($this->splitLines((string) $def['default']) as $i => $name) {
                $type = RequestedDocumentType::active()->where('name', $name)->first();
                $letter->requestedDocuments()->create([
                    'field_key'                  => $key,
                    'requested_document_type_id' => $type?->id,
                    'name'                       => $name,
                    'order'                      => $i + 1,
                ]);
            }
        }

        $letter->logActivity('letter_created', "Draft {$template->name} untuk klien '{$client->name}' dibuat");

        return $letter;
    }

    /* ------------------------------------------------------------------ */
    /* Requested-document checklist                                        */
    /* ------------------------------------------------------------------ */

    /**
     * Add a checklist item, either from the master list (by id) or as free text.
     * The name is copied onto the item so the letter stays readable even if the
     * master entry is renamed or deleted later.
     */
    public function addRequestedDocument(Letter $letter, string $fieldKey, ?int $typeId = null, ?string $name = null, ?string $note = null): LetterRequestedDocument
    {
        if (! $letter->isEditable()) {
            throw new RuntimeException('Daftar dokumen hanya bisa diubah selama surat masih draft.');
        }

        $type = $typeId ? RequestedDocumentType::active()->find($typeId) : null;
        if ($typeId && ! $type) {
            throw new InvalidArgumentException('Jenis dokumen tidak ditemukan.');
        }

        $name = trim((string) ($type?->name ?? $name));
        if (mb_strlen($name) < 2) {
            throw new InvalidArgumentException('Pilih jenis dokumen atau tulis namanya (minimal 2 karakter).');
        }

        $duplicate = $letter->requestedDocuments()
            ->where('field_key', $fieldKey)
            ->where(fn ($q) => $type ? $q->where('requested_document_type_id', $type->id) : $q->where('name', $name))
            ->exists();
        if ($duplicate) {
            throw new InvalidArgumentException("'{$name}' sudah ada di daftar.");
        }

        $order = ((int) $letter->requestedDocuments()->where('field_key', $fieldKey)->max('order')) + 1;

        return $letter->requestedDocuments()->create([
            'field_key'                  => $fieldKey,
            'requested_document_type_id' => $type?->id,
            'name'                       => $name,
            'note'                       => $note ? trim($note) : null,
            'order'                      => $order,
        ]);
    }

    /** Qualifier shown after the name in the letter, e.g. "Januari–Desember 2024". */
    public function updateRequestedDocumentNote(Letter $letter, LetterRequestedDocument $doc, ?string $note): LetterRequestedDocument
    {
        if (! $letter->isEditable() || $doc->letter_id !== $letter->id) {
            throw new RuntimeException('Daftar dokumen hanya bisa diubah selama surat masih draft.');
        }

        $doc->update(['note' => filled($note) ? trim($note) : null]);

        return $doc->fresh();
    }

    public function removeRequestedDocument(Letter $letter, LetterRequestedDocument $doc): void
    {
        if (! $letter->isEditable() || $doc->letter_id !== $letter->id) {
            throw new RuntimeException('Daftar dokumen hanya bisa diubah selama surat masih draft.');
        }

        $doc->delete();
    }

    /** Receipt can be toggled at any stage: it tracks fulfilment after the letter went out. */
    public function toggleReceived(Letter $letter, LetterRequestedDocument $doc, User $user): LetterRequestedDocument
    {
        if ($doc->letter_id !== $letter->id) {
            throw new RuntimeException('Dokumen tidak termasuk surat ini.');
        }

        if ($doc->is_received) {
            $doc->markPending();
            $letter->logActivity('letter_document_pending', "Dokumen '{$doc->name}' pada surat {$letter->display_number} ditandai belum diterima");
        } else {
            $doc->markReceived($user);
            $letter->logActivity('letter_document_received', "Dokumen '{$doc->name}' pada surat {$letter->display_number} ditandai diterima");
        }

        return $doc->fresh();
    }

    protected function splitLines(string $text): array
    {
        return collect(preg_split("/\R/", $text))
            ->map(fn ($l) => trim(preg_replace('/^\s*(\d+[.)]|[-*•])\s*/', '', $l)))
            ->filter()
            ->values()
            ->all();
    }

    /**
     * Validate required fields, freeze the body, assign a number and open the
     * signature chain. Re-submitting a rejected letter starts a fresh chain.
     */
    public function submit(Letter $letter): Letter
    {
        if (! $letter->isEditable()) {
            throw new RuntimeException('Surat ini tidak bisa diajukan pada status saat ini.');
        }

        $missing = $this->missingRequiredFields($letter);
        if ($missing !== []) {
            throw new InvalidArgumentException('Isian wajib belum lengkap: ' . implode(', ', $missing) . '.');
        }

        return DB::transaction(function () use ($letter) {
            $template = $letter->template;

            if (! $letter->number) {
                [$number, $sequence, $year] = $this->nextNumber($template->number_prefix, $letter->letter_date ?? now());
                $letter->number = $number;
                $letter->sequence = $sequence;
                $letter->year = $year;
            }

            $letter->rendered_html = $this->renderBody($letter);
            $letter->status = Letter::STATUS_SUBMITTED;
            $letter->submitted_at = now();
            $letter->completed_at = null;
            $letter->pdf_path = null;
            $letter->save();

            $letter->signatures()->delete();
            foreach ($template->signerSlots() as $slot) {
                $letter->signatures()->create([
                    'order'   => $slot['order'],
                    'label'   => $slot['label'],
                    'roles'   => $slot['roles'],
                    'user_id' => $letter->assignedSigner($slot['order'])?->id,
                    'status'  => LetterSignature::STATUS_PENDING,
                ]);
            }

            // Templates without signers are complete on submit.
            if ($template->signerSlots() === []) {
                $letter->status = Letter::STATUS_SIGNED;
                $letter->completed_at = now();
                $letter->save();
            }

            $letter->logActivity('letter_submitted', "Surat {$letter->number} ({$template->name}) diajukan untuk ditandatangani");
            $letter->load('signatures');
            $this->notifyNextSigner($letter);

            return $letter;
        });
    }

    public function sign(Letter $letter, User $user): Letter
    {
        $slot = $letter->currentSignature();
        if (! $slot || ! $slot->canBeSignedBy($user)) {
            throw new RuntimeException('Anda tidak berwenang menandatangani surat ini pada giliran sekarang.');
        }

        return DB::transaction(function () use ($letter, $user, $slot) {
            $slot->update([
                'user_id'           => $user->id,
                'status'            => LetterSignature::STATUS_SIGNED,
                'signed_at'         => now(),
                'verification_code' => Str::upper(Str::random(8)),
            ]);

            $letter->logActivity('letter_signed', "Surat {$letter->number} ditandatangani sebagai {$slot->label}");
            $letter->load('signatures');

            if ($letter->currentSignature() === null) {
                $letter->update(['status' => Letter::STATUS_SIGNED, 'completed_at' => now(), 'pdf_path' => null]);
                $this->notifyCreator($letter, 'Surat selesai ditandatangani', "{$letter->number} · {$letter->subject}");
            } else {
                $this->notifyNextSigner($letter);
            }

            return $letter->fresh(['signatures', 'template', 'client']);
        });
    }

    public function reject(Letter $letter, User $user, string $note): Letter
    {
        $slot = $letter->currentSignature();
        if (! $slot || ! $slot->canBeSignedBy($user)) {
            throw new RuntimeException('Anda tidak berwenang menolak surat ini pada giliran sekarang.');
        }

        return DB::transaction(function () use ($letter, $user, $slot, $note) {
            $slot->update([
                'user_id'   => $user->id,
                'status'    => LetterSignature::STATUS_REJECTED,
                'signed_at' => now(),
                'note'      => $note,
            ]);
            $letter->update(['status' => Letter::STATUS_REJECTED, 'pdf_path' => null]);
            $letter->logActivity('letter_rejected', "Surat {$letter->number} ditolak oleh {$slot->label}: {$note}");
            $this->notifyCreator($letter, 'Surat dikembalikan', "{$letter->number} ditolak: " . Str::limit($note, 80));

            return $letter->fresh(['signatures', 'template', 'client']);
        });
    }

    /**
     * Permanently remove an unnumbered letter (draft, or a draft that was
     * canceled). Signatures and requested-document rows cascade; the activity
     * log row is written first and survives as the audit trail.
     */
    public function delete(Letter $letter): void
    {
        if (! $letter->canBeDeleted()) {
            throw new RuntimeException('Surat yang sudah bernomor tidak bisa dihapus. Gunakan "Batalkan" agar nomor tetap tercatat.');
        }

        DB::transaction(function () use ($letter) {
            $letter->logActivity('letter_deleted', "Draft surat \"{$letter->subject}\" dihapus");

            if ($letter->pdf_path) {
                Storage::disk(config('letter.pdf_disk'))->delete($letter->pdf_path);
            }

            $letter->delete();
        });
    }

    public function cancel(Letter $letter): Letter
    {
        if ($letter->status === Letter::STATUS_SIGNED) {
            throw new RuntimeException('Surat yang sudah selesai tidak bisa dibatalkan.');
        }

        $letter->update(['status' => Letter::STATUS_CANCELED]);
        $letter->logActivity('letter_canceled', 'Surat ' . ($letter->number ?? 'draft') . " ({$letter->subject}) dibatalkan");

        return $letter;
    }

    /* ------------------------------------------------------------------ */
    /* Numbering                                                           */
    /* ------------------------------------------------------------------ */

    /**
     * Draw the next number for a prefix on a date, using the numbering settings
     * and a locked counter row so concurrent submits never share a sequence.
     *
     * @return array{0:string,1:int,2:int} [number, sequence, year]
     */
    public function nextNumber(string $prefix, Carbon|string $date): array
    {
        $date = Carbon::parse($date);
        $setting = LetterNumberingSetting::current();
        $bucketPrefix = $setting->counterPrefix($prefix);
        $periodKey = $setting->periodKey($date);

        $counter = LetterNumberCounter::query()
            ->where('prefix', $bucketPrefix)
            ->where('period_key', $periodKey)
            ->lockForUpdate()
            ->first();

        if (! $counter) {
            // First use of this bucket: never go below what letters already carry.
            $counter = LetterNumberCounter::create([
                'prefix'        => $bucketPrefix,
                'period_key'    => $periodKey,
                'last_sequence' => $this->highestIssuedSequence($setting, $prefix, $date),
            ]);
            $counter = LetterNumberCounter::whereKey($counter->id)->lockForUpdate()->first();
        }

        $sequence = $counter->last_sequence + 1;
        $counter->update(['last_sequence' => $sequence]);

        return [$setting->format($prefix, $sequence, $date), $sequence, (int) $date->format('Y')];
    }

    /** What nextNumber() would hand out, without locking or consuming it. */
    public function peekNextSequence(string $prefix, Carbon|string $date, ?LetterNumberingSetting $setting = null): int
    {
        $date = Carbon::parse($date);
        $setting ??= LetterNumberingSetting::current();

        $counter = LetterNumberCounter::query()
            ->where('prefix', $setting->counterPrefix($prefix))
            ->where('period_key', $setting->periodKey($date))
            ->value('last_sequence');

        return (int) ($counter ?? $this->highestIssuedSequence($setting, $prefix, $date)) + 1;
    }

    /**
     * Highest sequence already stamped on letters for a counter row
     * (prefix bucket '*' = shared; period key 'all', 'YYYY' or 'YYYY-MM').
     * Used by the settings page to stop the counter being wound below it.
     */
    public function highestIssuedForCounter(string $counterPrefix, string $periodKey): int
    {
        $query = Letter::query()->whereNotNull('sequence');

        if ($counterPrefix !== '*') {
            $query->whereHas('template', fn ($q) => $q->where('number_prefix', $counterPrefix));
        }

        if (preg_match('/^(\d{4})-(\d{2})$/', $periodKey, $m)) {
            $query->whereYear('letter_date', (int) $m[1])->whereMonth('letter_date', (int) $m[2]);
        } elseif (preg_match('/^\d{4}$/', $periodKey)) {
            $query->where('year', (int) $periodKey);
        }

        return (int) $query->max('sequence');
    }

    /** Highest sequence already stamped on letters in this bucket (for a fresh counter). */
    protected function highestIssuedSequence(LetterNumberingSetting $setting, string $prefix, Carbon $date): int
    {
        $query = Letter::query()->whereNotNull('sequence');

        if ($setting->sequence_per_prefix) {
            $query->whereHas('template', fn ($q) => $q->where('number_prefix', $prefix));
        }

        match ($setting->reset_period) {
            'monthly' => $query->whereYear('letter_date', $date->year)->whereMonth('letter_date', $date->month),
            'never'   => null,
            default   => $query->where('year', $date->year),
        };

        return (int) $query->max('sequence');
    }

    /* ------------------------------------------------------------------ */
    /* Rendering                                                           */
    /* ------------------------------------------------------------------ */

    public function missingRequiredFields(Letter $letter): array
    {
        $values = $letter->values ?? [];

        $letter->loadMissing('requestedDocuments');

        $missingSigners = collect($letter->template->signerSlots())
            ->filter(fn ($slot) => $letter->assignedSigner($slot['order']) === null)
            ->map(fn ($slot) => 'Penandatangan ' . $slot['label'])
            ->values();

        return $missingSigners->merge(collect($letter->template->fieldDefinitions())
            ->filter(function ($f) use ($values, $letter) {
                if (! $f['required']) {
                    return false;
                }
                if ($f['type'] === 'checklist') {
                    return $letter->checklist($f['key'])->isEmpty();
                }

                return blank($values[$f['key']] ?? null);
            })
            ->pluck('label'))
            ->all();
    }

    /* ------------------------------------------------------------------ */
    /* Signers                                                             */
    /* ------------------------------------------------------------------ */

    /** Active users allowed to sign a slot, as [id => name] for a select. */
    public function signerCandidates(array $roles): array
    {
        return User::query()
            ->where(fn ($q) => $q->where('status', 'active')->orWhereNull('status'))
            ->whereHas('roles', fn ($q) => $q->whereIn('name', $roles))
            ->with('department')
            ->orderBy('name')
            ->get(['id', 'name', 'position', 'job_title', 'department_id'])
            ->mapWithKeys(fn ($u) => [$u->id => $u->name . ($u->signatureTitle() ? " ({$u->signatureTitle()})" : '')])
            ->all();
    }

    /**
     * Sensible default per slot: the project's PIC if they qualify, else the
     * creator if they qualify, else the only candidate, else nobody.
     */
    protected function defaultSigners(LetterTemplate $template, User $creator, ?int $projectId): array
    {
        $pic = $projectId ? \App\Models\Project::find($projectId)?->pic : null;
        $out = [];

        foreach ($template->signerSlots() as $slot) {
            $candidates = $this->signerCandidates($slot['roles']);
            $pick = null;

            if ($pic && isset($candidates[$pic->id])) {
                $pick = $pic->id;
            } elseif (isset($candidates[$creator->id])) {
                $pick = $creator->id;
            } elseif (count($candidates) === 1) {
                $pick = (int) array_key_first($candidates);
            }

            $out[(string) $slot['order']] = $pick;
        }

        return $out;
    }

    /**
     * Where a user's signature image lives: a public path (images/signature/x.png)
     * or an upload on the public disk. Returns ['url' => ..., 'path' => ...] or null.
     */
    public function signatureSources(?User $user): ?array
    {
        $path = $user?->signature_path;
        if (! $path) {
            return null;
        }

        $path = ltrim($path, '/');
        if (is_file(public_path($path))) {
            return ['url' => '/' . $path, 'path' => public_path($path)];
        }
        if (Storage::disk('public')->exists($path)) {
            return ['url' => Storage::disk('public')->url($path), 'path' => Storage::disk('public')->path($path)];
        }

        return null;
    }

    /**
     * Body HTML for preview and PDF. Values are escaped; list fields become
     * <ol>; "- " lines become <ul>; blank lines separate paragraphs.
     */
    public function renderBody(Letter $letter): string
    {
        $template = $letter->template;
        $defs = $template->fieldDefinitions();
        $values = $letter->values ?? [];

        $placeholders = [
            'klien'         => e($letter->client?->name ?? '—'),
            'nomor'         => e($letter->number ?? '—'),
            'tanggal_surat' => e($this->formatDate($letter->letter_date)),
            'proyek'        => e($letter->project?->name ?? '—'),
            'perusahaan'    => e(config('letter.company.name')),
        ];

        $letter->loadMissing('requestedDocuments');
        $asAttachment = (bool) $template->checklist_as_attachment;
        foreach ($defs as $key => $def) {
            $placeholders[$key] = match (true) {
                $def['type'] === 'checklist' && $asAttachment => '', // listed on the LAMPIRAN page instead
                $def['type'] === 'checklist'                  => $this->renderChecklist($def, $letter->checklist($key)),
                default                                       => $this->renderValue($def, $values[$key] ?? null),
            };
        }

        $paragraphs = preg_split("/\R{2,}/", trim($template->body));
        $html = '';

        foreach ($paragraphs as $paragraph) {
            $paragraph = trim($paragraph);
            if ($paragraph === '') {
                continue;
            }

            // A paragraph that is only a placeholder: lists render as lists, and an
            // optional field left blank drops the paragraph instead of printing "…".
            if (preg_match('/^\{\{\s*([a-z0-9_]+)\s*\}\}$/i', $paragraph, $m) && isset($defs[$m[1]])) {
                $def = $defs[$m[1]];
                $isBlank = $def['type'] === 'checklist'
                    ? $letter->checklist($m[1])->isEmpty()
                    : blank($values[$m[1]] ?? null);

                if (($isBlank && ! $def['required']) || ($def['type'] === 'checklist' && $asAttachment)) {
                    continue;
                }
                if (in_array($def['type'], ['list', 'checklist'], true)) {
                    $html .= $placeholders[$m[1]];
                    continue;
                }
            }

            // Bullet block
            if (preg_match('/^(-\s.+\R?)+$/', $paragraph)) {
                $items = collect(preg_split("/\R/", $paragraph))
                    ->map(fn ($l) => '<li>' . $this->interpolate(e(ltrim(substr(trim($l), 1))), $placeholders) . '</li>')
                    ->join('');
                $html .= "<ul>{$items}</ul>";
                continue;
            }

            $text = nl2br($this->interpolate(e($paragraph), $placeholders));
            $html .= "<p>{$text}</p>";
        }

        return $html;
    }

    protected function interpolate(string $escapedText, array $placeholders): string
    {
        // e() has escaped the braces' surroundings but not the braces themselves.
        return preg_replace_callback('/\{\{\s*([a-z0-9_]+)\s*\}\}/i', function ($m) use ($placeholders) {
            return $placeholders[$m[1]] ?? '<span class="ltr-missing">' . e('{' . $m[1] . '}') . '</span>';
        }, $escapedText);
    }

    protected function renderChecklist(array $def, $items): string
    {
        if ($items->isEmpty()) {
            return '<span class="ltr-blank">' . e($def['placeholder'] ?? '…') . '</span>';
        }

        return '<ol>' . $items->map(function ($doc) {
            $line = e($doc->name);
            if (filled($doc->note)) {
                $line .= ' ' . e($doc->note);
            }

            return '<li>' . $line . '</li>';
        })->join('') . '</ol>';
    }

    protected function renderValue(array $def, mixed $value): string
    {
        if (blank($value)) {
            return '<span class="ltr-blank">' . e($def['placeholder'] ?? '…') . '</span>';
        }

        return match ($def['type']) {
            'date'     => e($this->formatDate($value)),
            'list'     => '<ol>' . collect(preg_split("/\R/", (string) $value))
                ->map(fn ($l) => trim(preg_replace('/^\s*(\d+[.)]|[-*•])\s*/', '', $l)))
                ->filter()
                ->map(fn ($l) => '<li>' . e($l) . '</li>')
                ->join('') . '</ol>',
            'textarea' => nl2br(e((string) $value)),
            'select'   => e($def['options'][$value] ?? (string) $value),
            'currency' => 'Rp ' . number_format((float) preg_replace('/[^\d.]/', '', (string) $value), 0, ',', '.'),
            'number'   => e(number_format((float) $value, 0, ',', '.')),
            default    => e((string) $value),
        };
    }

    public function formatDate(mixed $value): string
    {
        if (blank($value)) {
            return '—';
        }

        try {
            return Carbon::parse($value)->locale('id')->translatedFormat('d F Y');
        } catch (\Throwable) {
            return (string) $value;
        }
    }

    /** Full document HTML (letterhead + body + signature block) for preview or PDF. */
    public function renderDocument(Letter $letter, bool $forPdf = false): HtmlString
    {
        $letter->loadMissing(['template', 'client', 'project', 'signatures.user', 'requestedDocuments.type']);

        $body = $letter->rendered_html && ! $letter->isEditable()
            ? $letter->rendered_html
            : $this->renderBody($letter);

        return new HtmlString(view('letters.document', [
            'letter'  => $letter,
            'body'    => $body,
            'company' => config('letter.company'),
            'forPdf'  => $forPdf,
            'slots'   => $letter->signatures->isNotEmpty()
                ? $letter->signatures
                : collect($letter->template->signerSlots())->map(function ($s) use ($letter) {
                    $stub = new LetterSignature($s + ['status' => 'pending']);
                    $stub->setRelation('user', $letter->assignedSigner($s['order']));

                    return $stub;
                }),
        ])->render());
    }

    /* ------------------------------------------------------------------ */
    /* PDF                                                                 */
    /* ------------------------------------------------------------------ */

    /** Path on the configured disk; generated on first request after any state change. */
    public function pdfPath(Letter $letter): string
    {
        $disk = Storage::disk(config('letter.pdf_disk'));

        if ($letter->pdf_path && $disk->exists($letter->pdf_path)) {
            return $letter->pdf_path;
        }

        $fontDir = storage_path('fonts');
        $fonts = collect(config('letter.pdf_fonts', []))
            ->map(fn ($file) => $fontDir . DIRECTORY_SEPARATOR . $file)
            ->filter(fn ($path) => is_file($path))
            ->all();
        $letterhead = config('letter.letterhead_image');
        $letterheadPath = $letterhead && is_file(public_path($letterhead)) ? public_path($letterhead) : null;

        $html = view('pdf.letter', [
            'document'   => $this->renderDocument($letter, forPdf: true),
            'letter'     => $letter,
            'fonts'      => $fonts + ['regular' => '', 'bold' => '', 'italic' => '', 'bolditalic' => ''],
            'letterhead' => $letterheadPath,
        ])->render();

        $pdf = Pdf::loadHTML($html)->setPaper('a4', 'portrait')->setOption('isFontSubsettingEnabled', true);
        $name = Str::slug($letter->number ?: 'draft-' . $letter->id) . '.pdf';
        $path = trim(config('letter.pdf_dir'), '/') . '/' . ($letter->year ?: now()->year) . '/' . $name;

        $disk->put($path, $pdf->output());
        $letter->forceFill(['pdf_path' => $path])->saveQuietly();

        return $path;
    }

    /**
     * Download name: "Perihal - Nama Klien - Nomor.pdf", e.g.
     * "Permintaan Dokumen atas SP2DK 2024 - Dendra Borneo Mandiri - S-003-KSN-IX-2026.pdf".
     * The legal-form prefix (PT, CV, ...) is dropped from the client name and
     * characters Windows rejects in file names are replaced.
     */
    public function downloadFileName(Letter $letter): string
    {
        $client = preg_replace('/^\s*(PT|CV|UD|PD|Firma|Koperasi|Yayasan)\.?\s+/iu', '', (string) $letter->client?->name);
        $number = $letter->number ?: 'Draft-' . $letter->id;

        $parts = array_filter([trim((string) $letter->subject), trim($client), str_replace('/', '-', $number)]);
        $name = implode(' - ', $parts);
        $name = preg_replace('/[\\/:*?"<>|]+/u', '-', $name);
        $name = trim(preg_replace('/\s+/u', ' ', $name));

        return Str::limit($name, 180, '') . '.pdf';
    }

    /* ------------------------------------------------------------------ */
    /* Notifications                                                       */
    /* ------------------------------------------------------------------ */

    protected function notifyNextSigner(Letter $letter): void
    {
        $slot = $letter->currentSignature();
        if (! $slot) {
            return;
        }

        $recipients = $slot->user_id
            ? User::whereKey($slot->user_id)->get()
            : User::query()->whereHas('roles', fn ($q) => $q->whereIn('name', $slot->roles ?? []))->get();

        foreach ($recipients as $user) {
            Notification::make()
                ->title('Surat menunggu tanda tangan Anda')
                ->body("{$letter->number} · {$letter->subject} · {$letter->client?->name}")
                ->icon('heroicon-o-pencil-square')
                ->actions([
                    \Filament\Notifications\Actions\Action::make('buka')
                        ->label('Buka surat')
                        ->url(\App\Filament\Pages\Letters\Show::getUrl(['record' => $letter])),
                ])
                ->sendToDatabase($user);
        }
    }

    protected function notifyCreator(Letter $letter, string $title, string $body): void
    {
        if (! $letter->creator) {
            return;
        }

        Notification::make()
            ->title($title)
            ->body($body)
            ->icon('heroicon-o-document-check')
            ->actions([
                \Filament\Notifications\Actions\Action::make('buka')
                    ->label('Buka surat')
                    ->url(\App\Filament\Pages\Letters\Show::getUrl(['record' => $letter])),
            ])
            ->sendToDatabase($letter->creator);
    }
}
