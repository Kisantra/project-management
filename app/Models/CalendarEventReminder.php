<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CalendarEventReminder extends Model
{
    protected $fillable = ['calendar_event_id', 'minutes_before', 'remind_at', 'sent_at'];

    protected $casts = [
        'remind_at' => 'datetime',
        'sent_at'   => 'datetime',
    ];

    public function event(): BelongsTo
    {
        return $this->belongsTo(CalendarEvent::class, 'calendar_event_id');
    }

    public function label(): string
    {
        return CalendarEvent::REMINDER_OPTIONS[$this->minutes_before] ?? ($this->minutes_before . ' menit sebelum');
    }
}
