<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class LetterTemplate extends Model
{
    public const CATEGORIES = [
        'surat'          => 'Surat',
        'berita_acara'   => 'Berita Acara',
        'pengingat'      => 'Pengingat',
        'pemberitahuan'  => 'Pemberitahuan',
    ];

    public const FIELD_TYPES = [
        'text'     => 'Teks singkat',
        'textarea' => 'Paragraf',
        'list'     => 'Daftar (satu per baris)',
        'checklist' => 'Daftar dokumen (checklist, bisa dicentang saat diterima)',
        'date'     => 'Tanggal',
        'select'   => 'Pilihan',
        'currency' => 'Nominal rupiah',
        'number'   => 'Angka',
    ];

    /** Placeholders every template can use without declaring a field. */
    public const BUILTIN_PLACEHOLDERS = [
        'klien'         => 'Nama klien',
        'nomor'         => 'Nomor surat',
        'tanggal_surat' => 'Tanggal surat',
        'proyek'        => 'Nama proyek (bila dipilih)',
        'perusahaan'    => 'Nama kantor (dari pengaturan kop)',
    ];

    protected $fillable = [
        'code', 'name', 'description', 'category', 'number_prefix', 'icon', 'is_critical',
        'signers', 'fields', 'body', 'closing', 'checklist_as_attachment', 'sort_order', 'is_active',
    ];

    protected $casts = [
        'signers'     => 'array',
        'fields'      => 'array',
        'is_critical' => 'boolean',
        'is_active'   => 'boolean',
        'checklist_as_attachment' => 'boolean',
    ];

    public function letters(): HasMany
    {
        return $this->hasMany(Letter::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('sort_order')->orderBy('name');
    }

    public function getCategoryLabelAttribute(): string
    {
        return self::CATEGORIES[$this->category] ?? ucfirst($this->category);
    }

    /** Keys of checklist-type fields. */
    public function checklistKeys(): array
    {
        return collect($this->fieldDefinitions())->where('type', 'checklist')->keys()->all();
    }

    /** Field definitions keyed by field key, with defaults filled in. */
    public function fieldDefinitions(): array
    {
        return collect($this->fields ?? [])
            ->filter(fn ($f) => filled($f['key'] ?? null))
            ->map(fn ($f) => [
                'key'         => $f['key'],
                'label'       => $f['label'] ?? $f['key'],
                'type'        => $f['type'] ?? 'text',
                'required'    => (bool) ($f['required'] ?? false),
                'placeholder' => $f['placeholder'] ?? null,
                'help'        => $f['help'] ?? null,
                'options'     => $f['options'] ?? [],
                'default'     => $f['default'] ?? null,
            ])
            ->keyBy('key')
            ->all();
    }

    /** Signer slots in order, normalised. */
    public function signerSlots(): array
    {
        return collect($this->signers ?? [])
            ->values()
            ->map(fn ($s, $i) => [
                'order' => $i + 1,
                'label' => $s['label'] ?? 'Penandatangan',
                'roles' => array_values((array) ($s['roles'] ?? [])),
            ])
            ->all();
    }
}
