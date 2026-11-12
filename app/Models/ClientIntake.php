<?php

namespace App\Models;

use App\Filament\Resources\ClientIntakeResource;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Klien masuk dari Client Management (CM): satu baris per lead CM yang
 * mencapai Deal. Menunggu dicek = belum diproses dan belum dibatalkan di CM.
 */
class ClientIntake extends Model
{
    /** Waiting longer than this many days marks an intake as overdue (red) in the list. */
    public const OVERDUE_DAYS = 3;

    protected $fillable = [
        'cm_lead_id',
        'name',
        'entity',
        'service',
        'deal_value',
        'marketing_owner',
        'deal_at',
        'lead_url',
        'payload',
        'suggested_client_id',
        'suggested_by',
        'client_id',
        'project_id',
        'matched',
        'completed_at',
        'completed_by',
        'withdrawn_at',
        'withdrawn_reason',
        'withdrawn_detail',
        'withdrawn_note',
        'handoff_sent_at',
        'handoff_error',
    ];

    protected $casts = [
        'cm_lead_id' => 'integer',
        'deal_value' => 'integer',
        'matched' => 'boolean',
        'deal_at' => 'date',
        'payload' => 'array',
        'completed_at' => 'datetime',
        'withdrawn_at' => 'datetime',
        'handoff_sent_at' => 'datetime',
    ];

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function suggestedClient(): BelongsTo
    {
        return $this->belongsTo(Client::class, 'suggested_client_id');
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function completedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'completed_by');
    }

    public function scopePending(Builder $query): Builder
    {
        return $query->whereNull('completed_at')->whereNull('withdrawn_at');
    }

    public function scopeCompleted(Builder $query): Builder
    {
        return $query->whereNotNull('completed_at')->whereNull('withdrawn_at');
    }

    public function scopeWithdrawn(Builder $query): Builder
    {
        return $query->whereNotNull('withdrawn_at');
    }

    public function isPending(): bool
    {
        return $this->completed_at === null && $this->withdrawn_at === null;
    }

    public function isCompleted(): bool
    {
        return $this->completed_at !== null;
    }

    public function isWithdrawn(): bool
    {
        return $this->withdrawn_at !== null;
    }

    /** Whole days since the intake arrived. */
    public function waitingDays(): int
    {
        return (int) abs($this->created_at->copy()->startOfDay()->diffInDays(now()->startOfDay()));
    }

    public function isOverdue(): bool
    {
        return $this->isPending() && $this->waitingDays() > self::OVERDUE_DAYS;
    }

    /** Pending intakes that arrived more than OVERDUE_DAYS days ago. */
    public function scopeOverdue(Builder $query): Builder
    {
        return $query->pending()->where('created_at', '<', now()->startOfDay()->subDays(self::OVERDUE_DAYS));
    }

    /** sent | failed | queued: whether Client Management heard that the deal was processed. */
    public function handoffState(): string
    {
        return match (true) {
            $this->handoff_sent_at !== null => 'sent',
            filled($this->handoff_error) => 'failed',
            default => 'queued',
        };
    }

    /** Nilai yang dikirim CM untuk satu kunci payload, mis. 'npwp' atau 'contact.phone'. */
    public function sent(string $key): mixed
    {
        return data_get($this->payload, $key);
    }

    public function statusLabel(): string
    {
        return match (true) {
            $this->isWithdrawn() => 'Batal di Client Management',
            $this->isCompleted() => 'Selesai',
            default => 'Menunggu dicek',
        };
    }

    /** Label asal klien untuk badge di daftar Clients. */
    public function originLabel(): string
    {
        return match (true) {
            $this->isWithdrawn() => 'Deal batal di Client Management',
            $this->matched => 'Klien lama · layanan baru',
            default => 'Dari Client Management',
        };
    }

    public static function formatRupiah(?int $value): ?string
    {
        return $value === null ? null : 'Rp ' . number_format($value, 0, ',', '.');
    }

    /** Ringkasan satu baris: "PT X · PPh Badan. Deal 10 Nov 2026 oleh Dimas." */
    public function summaryLine(): string
    {
        $line = $this->name;

        if (filled($this->service)) {
            $line .= ' · ' . $this->service;
        }

        $line .= '.';

        if ($this->deal_at) {
            $line .= ' Deal ' . $this->deal_at->locale('id')->translatedFormat('j M Y');
            $line .= filled($this->marketing_owner) ? " oleh {$this->marketing_owner}." : '.';
        } elseif (filled($this->marketing_owner)) {
            $line .= " Deal oleh {$this->marketing_owner}.";
        }

        return $line;
    }

    /**
     * Halaman "Cek & proses". Panel disebut eksplisit karena URL ini juga
     * dibuat dari request API (di luar konteks panel).
     */
    public function reviewUrl(): string
    {
        return ClientIntakeResource::getUrl('proses', ['record' => $this], panel: 'admin');
    }
}
