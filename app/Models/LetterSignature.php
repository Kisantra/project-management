<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LetterSignature extends Model
{
    public const STATUS_PENDING = 'pending';
    public const STATUS_SIGNED = 'signed';
    public const STATUS_REJECTED = 'rejected';

    protected $fillable = [
        'letter_id', 'order', 'label', 'roles', 'user_id', 'status',
        'signed_at', 'verification_code', 'note',
    ];

    protected $casts = [
        'roles'     => 'array',
        'signed_at' => 'datetime',
    ];

    public function letter(): BelongsTo
    {
        return $this->belongsTo(Letter::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    public function isSigned(): bool
    {
        return $this->status === self::STATUS_SIGNED;
    }

    /**
     * Super-admin may sign any slot; otherwise the user needs one of the
     * slot's roles AND must be in the module-wide signer roles list.
     */
    public function canBeSignedBy(User $user): bool
    {
        if (! $this->isPending()) {
            return false;
        }

        if ($user->hasRole('super-admin')) {
            return true;
        }

        // A slot assigned to a specific person can only be signed by that person.
        if ($this->user_id) {
            return (int) $this->user_id === (int) $user->id;
        }

        return $user->hasAnyRole(config('letter.signer_roles', []))
            && $user->hasAnyRole($this->roles ?? []);
    }
}
