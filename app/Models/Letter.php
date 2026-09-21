<?php

namespace App\Models;

use App\Traits\Trackable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Letter extends Model
{
    use Trackable;

    public const STATUS_DRAFT = 'draft';
    public const STATUS_SUBMITTED = 'submitted';
    public const STATUS_SIGNED = 'signed';
    public const STATUS_REJECTED = 'rejected';
    public const STATUS_CANCELED = 'canceled';

    public const STATUSES = [
        self::STATUS_DRAFT     => 'Draft',
        self::STATUS_SUBMITTED => 'Menunggu tanda tangan',
        self::STATUS_SIGNED    => 'Selesai',
        self::STATUS_REJECTED  => 'Ditolak',
        self::STATUS_CANCELED  => 'Dibatalkan',
    ];

    protected $fillable = [
        'letter_template_id', 'client_id', 'project_id', 'created_by',
        'number', 'sequence', 'year', 'letter_date', 'subject', 'values', 'signers',
        'rendered_html', 'status', 'submitted_at', 'completed_at', 'pdf_path',
    ];

    protected $casts = [
        'values'       => 'array',
        'signers'      => 'array',
        'letter_date'  => 'date',
        'submitted_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    public function template(): BelongsTo
    {
        return $this->belongsTo(LetterTemplate::class, 'letter_template_id');
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function signatures(): HasMany
    {
        return $this->hasMany(LetterSignature::class)->orderBy('order');
    }

    public function requestedDocuments(): HasMany
    {
        return $this->hasMany(LetterRequestedDocument::class)->with('type')->orderBy('order')->orderBy('id');
    }

    /** Requested documents for one checklist field. */
    public function checklist(string $fieldKey)
    {
        return $this->requestedDocuments->where('field_key', $fieldKey)->values();
    }

    public function scopeStatus(Builder $query, ?string $status): Builder
    {
        return $status ? $query->where('status', $status) : $query;
    }

    public function getStatusLabelAttribute(): string
    {
        return self::STATUSES[$this->status] ?? ucfirst($this->status);
    }

    public function getStatusColorAttribute(): string
    {
        return match ($this->status) {
            self::STATUS_SIGNED    => 'success',
            self::STATUS_SUBMITTED => 'warning',
            self::STATUS_REJECTED,
            self::STATUS_CANCELED  => 'danger',
            default                => 'gray',
        };
    }

    public function isDraft(): bool
    {
        return $this->status === self::STATUS_DRAFT;
    }

    public function isEditable(): bool
    {
        return in_array($this->status, [self::STATUS_DRAFT, self::STATUS_REJECTED], true);
    }

    /**
     * Only letters that never received a number may be removed; a numbered
     * letter stays in the register (cancel it instead) so the sequence has no gaps.
     */
    public function canBeDeleted(): bool
    {
        return $this->number === null
            && in_array($this->status, [self::STATUS_DRAFT, self::STATUS_CANCELED], true);
    }

    public function isAwaitingSignature(): bool
    {
        return $this->status === self::STATUS_SUBMITTED;
    }

    /** The signature slot whose turn it is, or null. */
    public function currentSignature(): ?LetterSignature
    {
        return $this->signatures->firstWhere('status', LetterSignature::STATUS_PENDING);
    }

    /** Can this user sign the current slot? */
    public function canBeSignedBy(User $user): bool
    {
        if (! $this->isAwaitingSignature()) {
            return false;
        }

        $slot = $this->currentSignature();

        return $slot !== null && $slot->canBeSignedBy($user);
    }

    /** User chosen for a signer slot while drafting, or null. */
    public function assignedSigner(int $order): ?User
    {
        $id = $this->signers[(string) $order] ?? $this->signers[$order] ?? null;

        return $id ? User::find($id) : null;
    }

    public function getDisplayNumberAttribute(): string
    {
        return $this->number ?? 'Belum bernomor';
    }
}
