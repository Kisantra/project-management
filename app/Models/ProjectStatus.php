<?php

namespace App\Models;

use App\Support\Ui\Tone;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProjectStatus extends Model
{
    public const CATEGORY_NOT_STARTED = 'not_started';
    public const CATEGORY_ACTIVE      = 'active';
    public const CATEGORY_DONE        = 'done';
    public const CATEGORY_CLOSED      = 'closed';

    public const CATEGORIES = [
        self::CATEGORY_NOT_STARTED => 'Not Started',
        self::CATEGORY_ACTIVE      => 'Active',
        self::CATEGORY_DONE        => 'Done',
        self::CATEGORY_CLOSED      => 'Closed',
    ];

    public const SHAPES = ['empty', 'dashed', 'half', 'clock', 'check', 'x'];

    /** Design-system tone (App\Support\Ui\Tone); `color` is kept for the dashboard charts. */
    public function getToneSwatchAttribute(): array
    {
        return Tone::swatch(Tone::forProjectStatus($this));
    }

    protected $fillable = [
        'key',
        'label',
        'color',
        'tone',
        'shape',
        'category',
        'sort_order',
        'is_system',
    ];

    protected $casts = [
        'is_system'  => 'boolean',
        'sort_order' => 'integer',
    ];

    /**
     * Projects that currently have this status.
     * Joined by string key, not foreign id.
     */
    public function projects(): HasMany
    {
        return $this->hasMany(Project::class, 'status', 'key');
    }

    public function scopeInCategory(Builder $query, string $category): Builder
    {
        return $query->where('category', $category);
    }

    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderByRaw("FIELD(category, 'not_started', 'active', 'done', 'closed')")
                     ->orderBy('sort_order');
    }

    public function isInCategory(string $category): bool
    {
        return $this->category === $category;
    }

    /**
     * Lightened tint of `color`, used as the background fill on
     * status pills. Derived at runtime so users only need to pick one color.
     *
     * Example: '#16a34a' (green-600) → '#dcfce7' (green-100 ish)
     */
    public function getBgColorAttribute(): string
    {
        return $this->lighten($this->color, 0.85);
    }

    protected function lighten(?string $hex, float $amount = 0.85): string
    {
        if (!$hex) return '#f1f5f9';
        $hex = ltrim($hex, '#');
        if (strlen($hex) === 3) {
            $hex = $hex[0].$hex[0].$hex[1].$hex[1].$hex[2].$hex[2];
        }
        if (strlen($hex) !== 6) return '#f1f5f9';

        $r = hexdec(substr($hex, 0, 2));
        $g = hexdec(substr($hex, 2, 2));
        $b = hexdec(substr($hex, 4, 2));

        $r = (int) round($r + (255 - $r) * $amount);
        $g = (int) round($g + (255 - $g) * $amount);
        $b = (int) round($b + (255 - $b) * $amount);

        return sprintf('#%02x%02x%02x', $r, $g, $b);
    }

    /**
     * A status can only be deleted if it's not system-locked AND not in use.
     * The reassignment-on-delete flow comes later in Phase 4.
     */
    public function canBeDeleted(): bool
    {
        if ($this->is_system) {
            return false;
        }

        return $this->projects()->doesntExist();
    }
}
