<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One document a letter asks the client for. Lives on after the letter is
 * submitted so the team can tick off what has actually arrived.
 */
class LetterRequestedDocument extends Model
{
    protected $fillable = [
        'letter_id', 'field_key', 'requested_document_type_id', 'name', 'note', 'order',
        'is_received', 'received_at', 'received_by',
    ];

    protected $casts = [
        'is_received' => 'boolean',
        'received_at' => 'datetime',
    ];

    public function letter(): BelongsTo
    {
        return $this->belongsTo(Letter::class);
    }

    public function receiver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'received_by');
    }

    /** Master entry this item was picked from; null for a free-text item. */
    public function type(): BelongsTo
    {
        return $this->belongsTo(RequestedDocumentType::class, 'requested_document_type_id');
    }

    public function markReceived(?User $by = null): void
    {
        $this->update([
            'is_received' => true,
            'received_at' => now(),
            'received_by' => $by?->id,
        ]);
    }

    public function markPending(): void
    {
        $this->update([
            'is_received' => false,
            'received_at' => null,
            'received_by' => null,
        ]);
    }
}
