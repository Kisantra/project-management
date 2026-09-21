<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Master data: the documents the office commonly requests from clients.
 * Letters pick from this list instead of typing names by hand.
 */
class RequestedDocumentType extends Model
{
    public const CATEGORIES = [
        'keuangan'    => 'Keuangan',
        'perpajakan'  => 'Perpajakan',
        'legal'       => 'Legal & Perizinan',
        'kepegawaian' => 'Kepegawaian',
        'lainnya'     => 'Lainnya',
    ];

    protected $fillable = ['name', 'category', 'description', 'sort_order', 'is_active'];

    protected $casts = ['is_active' => 'boolean'];

    public function requestedDocuments(): HasMany
    {
        return $this->hasMany(LetterRequestedDocument::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('category')->orderBy('sort_order')->orderBy('name');
    }

    public function getCategoryLabelAttribute(): string
    {
        return self::CATEGORIES[$this->category] ?? ucfirst($this->category);
    }

    /** [category label => [id => name]] for a grouped select. */
    public static function groupedOptions(): array
    {
        return static::active()->ordered()->get()
            ->groupBy('category')
            ->mapWithKeys(fn ($items, $cat) => [
                (self::CATEGORIES[$cat] ?? ucfirst($cat)) => $items->pluck('name', 'id')->all(),
            ])
            ->all();
    }
}
