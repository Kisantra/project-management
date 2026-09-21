<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Spatie\Activitylog\Traits\CausesActivity;
use Spatie\Activitylog\LogOptions;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use SolutionForest\FilamentAccessManagement\Concerns\FilamentUserHelpers;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use League\CommonMark\Node\Block\Document;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Filament\Models\Contracts\HasAvatar;
use Edwink\FilamentUserActivity\Traits\UserActivityTrait;

class User extends Authenticatable implements FilamentUser, HasAvatar
{
    use HasApiTokens, HasFactory, Notifiable, CausesActivity;
    use FilamentUserHelpers;
    use UserActivityTrait;
    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'department_id',
        'position',
        'job_title',
        'status',
        'avatar_path',
        'avatar_url',
        'signature_path',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'email_verified_at' => 'datetime',
        'password' => 'hashed',
    ];

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function documents()
    {
        return $this->hasMany(Document::class);
    }

    public function getAvatarUrl()
    {
        return filament()->getUserAvatarUrl($this);
    }

    public function clientDocuments(): HasMany
    {
        return $this->hasMany(ClientDocument::class);
    }

    public function comments()
    {
        return $this->morphMany(Comment::class, 'commentable');
    }

    public function submittedDocuments()
    {
        return $this->hasMany(SubmittedDocument::class);
    }

    public function userClients()
    {
        return $this->hasMany(UserClient::class);
    }

    public function chatThreads(): BelongsToMany
    {
        return $this->belongsToMany(ChatThread::class, 'chat_participants')
            ->withPivot(['role', 'last_read_at', 'muted_at'])
            ->withTimestamps();
    }

    public function chatParticipants(): HasMany
    {
        return $this->hasMany(ChatParticipant::class);
    }

    public function chatMessages(): HasMany
    {
        return $this->hasMany(ChatMessage::class);
    }

    public function createdChatThreads(): HasMany
    {
        return $this->hasMany(ChatThread::class, 'created_by_id');
    }

    public function userProjects()
    {
        return $this->hasMany(UserProject::class);
    }
    
    public function dailyTaskAssignments(): HasMany
    {
        return $this->hasMany(DailyTaskAssignment::class);
    }

    public function activities()
    {
        return $this->actions();
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['name', 'email', 'password'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs()
            ->setDescriptionForEvent(function(string $eventName) {
                return match ($eventName) {
                    'created' => "Akun pengguna baru dibuat: {$this->name}",
                    'updated' => "Detail akun pengguna diubah: {$this->name}",
                    'deleted' => "Akun pengguna dihapus: {$this->name}",
                    default => "Akun pengguna {$this->name} telah di{$eventName}"
                };
            });
    }

    public function getFilamentAvatarUrl(): ?string
    {
        return $this->avatar_url;
    }

    /**
     * Get the user's avatar URL or return a default
     */
    public function getAvatarAttribute(): string
    {
        // If there's an uploaded file, use that first
        if ($this->avatar_path && \Storage::disk('public')->exists($this->avatar_path)) {
            return \Storage::disk('public')->url($this->avatar_path);
        }
        
        // Otherwise use the URL if provided
        if ($this->avatar_url) {
            return $this->avatar_url;
        }
        
        // Fall back to generated avatar
        return 'https://ui-avatars.com/api/?name=' . urlencode($this->name) . '&color=7F9CF5&background=EBF4FF&size=300';
    }

    /**
     * Delete the old avatar file when updating
     */
    public function deleteOldAvatar(): void
    {
        if ($this->avatar_path && \Storage::disk('public')->exists($this->avatar_path)) {
            \Storage::disk('public')->delete($this->avatar_path);
        }
    }

    /**
     * Remove the stored signature file (only files we uploaded to the public
     * disk; hand-placed files under public/images are left alone).
     */
    public function deleteOldSignature(): void
    {
        if ($this->signature_path && \Storage::disk('public')->exists($this->signature_path)) {
            \Storage::disk('public')->delete($this->signature_path);
        }
    }

    /**
     * Web URL of the signature image, whether it lives on the public disk or
     * was placed by hand under public/. Null when there is none.
     */
    /**
     * Title printed under the name in a letter's signature block.
     * Explicit job_title wins; otherwise "Tax Manager" is derived from
     * department + position (a Director is printed without the department).
     */
    public function signatureTitle(): ?string
    {
        if (filled($this->job_title)) {
            return $this->job_title;
        }

        $position = trim((string) $this->position);
        if ($position === '') {
            return null;
        }

        $department = trim((string) $this->department?->name);
        if ($department === '' || strcasecmp($position, 'Director') === 0) {
            return $position;
        }

        return "{$department} {$position}";
    }

    public function signatureUrl(): ?string
    {
        return app(\App\Services\LetterService::class)->signatureSources($this)['url'] ?? null;
    }

    /**
     * Set avatar path and automatically populate avatar_url with storage/ prefix
     */
    public function setAvatarPathAttribute($value): void
    {
        $this->attributes['avatar_path'] = $value;
        
        // If we're setting a file path, also set the avatar_url with storage/ prefix
        if ($value) {
            $this->attributes['avatar_url'] = 'storage/' . $value;
        }
    }

    public function canAccessPanel(Panel $panel): bool
    {
        return true; // Or add your custom logic here
    }
}
