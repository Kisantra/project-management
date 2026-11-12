<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Sop extends Model
{
    use HasFactory;

    protected $casts = [
        'sort_order' => 'integer',
    ];

    protected static function booted(): void
    {
        // New SOPs join the end of the admin-defined order.
        static::creating(function (Sop $sop) {
            if (! $sop->sort_order) {
                $sop->sort_order = (int) static::query()->max('sort_order') + 1;
            }
        });
    }

    /** Admin-defined order (SOP page), then name for equal positions. */
    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('sort_order')->orderBy('name');
    }

    public function projects(): HasMany
    {
        return $this->hasMany(Project::class);
    }

    public function steps(): HasMany
    {
        return $this->hasMany(SopStep::class, 'sop_id')->orderBy('order');
    }
}
