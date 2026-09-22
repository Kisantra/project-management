<?php

namespace App\Models;

use App\Traits\Trackable;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * One entry on the team calendar: an appointment with a client, a reminder to
 * request data, or any other dated event. Shared by the whole internal team;
 * participants are the people who get reminded.
 */
class CalendarEvent extends Model
{
    use Trackable;

    public const KIND_APPOINTMENT = 'appointment';
    public const KIND_DATA_REQUEST = 'data_request';
    public const KIND_REMINDER = 'reminder';
    public const KIND_OTHER = 'other';

    /** Label + the hue family the calendar paints the chip with. */
    public const KINDS = [
        self::KIND_APPOINTMENT  => ['label' => 'Janji temu',       'tone' => 'cyan'],
        self::KIND_DATA_REQUEST => ['label' => 'Permintaan data',  'tone' => 'amber'],
        self::KIND_REMINDER     => ['label' => 'Pengingat',        'tone' => 'violet'],
        self::KIND_OTHER        => ['label' => 'Acara lain',       'tone' => 'slate'],
    ];

    public const STATUS_SCHEDULED = 'scheduled';
    public const STATUS_DONE = 'done';
    public const STATUS_CANCELED = 'canceled';

    public const STATUSES = [
        self::STATUS_SCHEDULED => 'Terjadwal',
        self::STATUS_DONE      => 'Selesai',
        self::STATUS_CANCELED  => 'Dibatalkan',
    ];

    /** minutes before start => label */
    public const REMINDER_OPTIONS = [
        0     => 'Saat dimulai',
        15    => '15 menit sebelum',
        60    => '1 jam sebelum',
        1440  => '1 hari sebelum',
        4320  => '3 hari sebelum',
        10080 => '1 minggu sebelum',
    ];

    protected $fillable = [
        'title', 'kind', 'starts_at', 'ends_at', 'all_day', 'location', 'description',
        'client_id', 'project_id', 'status', 'created_by',
    ];

    protected $casts = [
        'starts_at' => 'datetime',
        'ends_at'   => 'datetime',
        'all_day'   => 'boolean',
    ];

    /* ------------------------------------------------------------------ */
    /* Relations                                                           */
    /* ------------------------------------------------------------------ */

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

    public function participants(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'calendar_event_participants')->withTimestamps();
    }

    public function reminders(): HasMany
    {
        return $this->hasMany(CalendarEventReminder::class)->orderBy('remind_at');
    }

    /* ------------------------------------------------------------------ */
    /* Scopes                                                              */
    /* ------------------------------------------------------------------ */

    /** Events overlapping the [from, to] window (inclusive of both ends). */
    public function scopeBetween(Builder $query, Carbon $from, Carbon $to): Builder
    {
        return $query->where('starts_at', '<=', $to->copy()->endOfDay())
            ->where(fn ($q) => $q->where('ends_at', '>=', $from->copy()->startOfDay())
                ->orWhere(fn ($w) => $w->whereNull('ends_at')->where('starts_at', '>=', $from->copy()->startOfDay())));
    }

    /** Events the user created or is invited to. */
    public function scopeInvolving(Builder $query, User $user): Builder
    {
        return $query->where(fn ($q) => $q->where('created_by', $user->id)
            ->orWhereHas('participants', fn ($p) => $p->where('users.id', $user->id)));
    }

    /* ------------------------------------------------------------------ */
    /* Helpers                                                             */
    /* ------------------------------------------------------------------ */

    public function kindLabel(): string
    {
        return self::KINDS[$this->kind]['label'] ?? $this->kind;
    }

    public function tone(): string
    {
        return self::KINDS[$this->kind]['tone'] ?? 'slate';
    }

    public function statusLabel(): string
    {
        return self::STATUSES[$this->status] ?? $this->status;
    }

    public function isScheduled(): bool
    {
        return $this->status === self::STATUS_SCHEDULED;
    }

    public function isPast(): bool
    {
        return ($this->ends_at ?? $this->starts_at)->isPast();
    }

    /** "09.30–11.00", "09.30", or "Seharian". */
    public function timeLabel(): string
    {
        if ($this->all_day) {
            return 'Seharian';
        }

        $start = $this->starts_at->format('H.i');

        return $this->ends_at && ! $this->ends_at->equalTo($this->starts_at)
            ? $start . '–' . $this->ends_at->format('H.i')
            : $start;
    }

    /** "Sel, 22 Sep 2026" */
    public function dateLabel(): string
    {
        return $this->starts_at->locale('id')->translatedFormat('D, d M Y');
    }

    /** Whether the user may edit or delete this event. */
    public function canBeManagedBy(User $user): bool
    {
        return (int) $this->created_by === (int) $user->id
            || $user->hasAnyRole(['super-admin', 'direktur', 'project-manager']);
    }
}
