<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\Activitylog\LogOptions;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use App\Traits\Trackable;

class Project extends Model
{
    use HasFactory;

    use Trackable;

    use LogsActivity;

    protected $fillable = ['client_id', 'parent_project_id', 'department_id', 'name', 'description', 'status'];

    protected $casts = [
        'due_date' => 'date',
        'deliverable_files' => 'array', // Make sure this is present
    ];

    // Auto-logging events
    protected static function booted()
    {
        static::created(function ($project) {
            $project->logActivity(
                'project_created',
                "Proyek '{$project->name}' telah dibuat untuk klien {$project->client->name}"
            );
        });

        static::updated(function ($project) {
            if ($project->wasChanged('status')) {
                $project->logActivity(
                    'project_status_changed',
                    "Status proyek '{$project->name}' diubah dari {$project->getOriginal('status')} menjadi {$project->status}"
                );

                // Sync status to linked daily tasks based on the project status
                // category (so any user-created status still routes correctly).
                $taskStatus = match ($project->statusRecord?->category) {
                    'not_started' => 'pending',
                    'active'      => 'in_progress',
                    'done'        => 'completed',
                    'closed'      => 'cancelled',
                    default       => null,
                };

                if ($taskStatus) {
                    $project->dailyTasks()
                        ->whereNotIn('status', ['completed', 'cancelled'])
                        ->update(['status' => $taskStatus]);
                }
            }

            if ($project->wasChanged('priority')) {
                $project->logActivity(
                    'project_priority_changed',
                    "Prioritas proyek '{$project->name}' diubah dari {$project->getOriginal('priority')} menjadi {$project->priority}"
                );
            }
        });

        static::deleted(function ($project) {
            $project->logActivity(
                'project_deleted',
                "Proyek '{$project->name}' telah dihapus"
            );
        });
    }

    public function calendarEvents(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(CalendarEvent::class);
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['name', 'description', 'priority', 'type', 'due_date', 'status'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs()
            ->setDescriptionForEvent(function (string $eventName) {
                $clientName = $this->client->name ?? 'Klien';

                return match ($eventName) {
                    'created' => "[{$clientName}] 📂 PROYEK BARU: {$this->name} | Prioritas: {$this->priority}",
                    'updated' => match ($this->statusRecord?->category) {
                            'done'        => "[{$clientName}] ✅ PROYEK SELESAI: {$this->name}",
                            'active'      => "[{$clientName}] ⚡ PROYEK AKTIF: {$this->name}",
                            'closed'      => "[{$clientName}] ❌ PROYEK DIBATALKAN: {$this->name}",
                            'not_started' => "[{$clientName}] 📋 PROYEK BARU: {$this->name}",
                            default       => "[{$clientName}] Proyek {$this->name} diperbarui"
                        },
                    'deleted' => "[{$clientName}] 🗑️ PROYEK DIHAPUS: {$this->name}",
                    default => "[{$clientName}] Proyek {$this->name} telah di{$eventName}"
                };
            });
    }

    public function client()
    {
        return $this->belongsTo(Client::class);
    }

    public function parentProject(): BelongsTo
    {
        return $this->belongsTo(Project::class, 'parent_project_id');
    }

    public function childProjects(): HasMany
    {
        return $this->hasMany(Project::class, 'parent_project_id');
    }

    public function department()
    {
        return $this->belongsTo(Department::class);
    }

    /**
     * The full status definition row (label, color, shape, category).
     * Joined by string key — see project_statuses.key.
     */
    public function statusRecord()
    {
        return $this->belongsTo(ProjectStatus::class, 'status', 'key');
    }

    /**
     * Is this project's current status in the given category?
     * Pass one of ProjectStatus::CATEGORY_* constants.
     */
    public function isInStatusCategory(string $category): bool
    {
        return $this->statusRecord?->category === $category;
    }

    public function steps()
    {
        return $this->hasMany(ProjectStep::class);
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'user_projects')
            ->withPivot('role')
            ->withTimestamps();
    }

    public function userProject(): HasMany
    {
        return $this->hasMany(UserProject::class);
    }

    public function dailyTasks(): HasMany
    {
        return $this->hasMany(DailyTask::class);
    }

    public function notes(): HasMany
    {
        return $this->hasMany(ProjectNote::class)->latest();
    }

    public function chatThreads(): HasMany
    {
        return $this->hasMany(ChatThread::class);
    }

    public function sop(): BelongsTo
    {
        return $this->belongsTo(Sop::class);
    }

    public function pic(): BelongsTo
    {
        return $this->belongsTo(User::class, 'pic_id');
    }

    public function userProjects()
    {
        return $this->hasMany(UserProject::class);
    }

    public function teamMembers()
    {
        return $this->belongsToMany(User::class, 'user_projects')
            ->withPivot('role', 'specializations', 'assigned_date')
            ->withTimestamps();
    }

    /**
     * Get formatted deliverable files with full URLs
     */
    public function getDeliverableFilesWithUrlsAttribute(): array
    {
        if (!$this->deliverable_files) {
            return [];
        }

        return collect($this->deliverable_files)->map(function ($file) {
            return [
                'name' => $file['name'] ?? basename($file['path'] ?? ''),
                'path' => $file['path'] ?? null,
                'url' => isset($file['path']) ? \Storage::disk('public')->url($file['path']) : null,
                'size' => $file['size'] ?? null,
                'type' => $file['type'] ?? null,
                'uploaded_at' => $file['uploaded_at'] ?? null,
            ];
        })->toArray();
    }

    /**
     * Client-facing status label (Bahasa Indonesia).
     *
     * Known seeded statuses keep their specific phrasing for continuity.
     * Anything else (a user-created status) falls back to the category label.
     */
    public function getClientStatusLabelAttribute(): string
    {
        $specific = match ($this->status) {
            'draft'              => 'Belum Dimulai',
            'analysis'           => 'Sedang Dianalisis',
            'in_progress'        => 'Sedang Dikerjakan',
            'review'             => 'Dalam Review',
            'completed'          => 'Selesai',
            'completed_not_paid' => 'Selesai (Belum Dibayar)',
            'canceled'           => 'Dibatalkan',
            default              => null,
        };
        if ($specific !== null) return $specific;

        return match ($this->statusRecord?->category) {
            'not_started' => 'Belum Dimulai',
            'active'      => 'Sedang Dikerjakan',
            'done'        => 'Selesai',
            'closed'      => 'Dibatalkan',
            default       => $this->statusRecord?->label ?? ucfirst($this->status),
        };
    }

    /**
     * Status badge color for the client view.
     * Category-based so user-created statuses still get a sensible color.
     */
    public function getClientStatusColorAttribute(): string
    {
        return match ($this->statusRecord?->category) {
            'not_started' => 'gray',
            'active'      => 'blue',
            'done'        => 'green',
            'closed'      => 'red',
            default       => 'gray',
        };
    }

    /**
     * A project is "active" when its status is not in the done or closed bucket.
     * Works for any user-created status in the active / not_started buckets.
     */
    public function isActive(): bool
    {
        $cat = $this->statusRecord?->category;
        return $cat !== 'done' && $cat !== 'closed';
    }

    /**
     * Get days until due date
     */
    public function getDaysUntilDueAttribute(): ?int
    {
        if (!$this->due_date) {
            return null;
        }

        return now()->diffInDays($this->due_date, false);
    }

    /**
     * Check if project has deliverables
     */
    public function hasDeliverables(): bool
    {
        return !empty($this->deliverable_files);
    }

    /**
     * Get deliverables count
     */
    public function getDeliverablesCountAttribute(): int
    {
        return count($this->deliverable_files ?? []);
    }

}
