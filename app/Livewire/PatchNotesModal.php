<?php

namespace App\Livewire;

use App\Models\PatchNote;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Livewire\Attributes\On;
use Livewire\Component;

/**
 * Popup "Pembaruan Sistem".
 *
 * Dipasang di setiap halaman panel admin lewat render hook BODY_END, tapi hanya
 * membuka dirinya sendiri di Dashboard dan hanya bila ada patch terbit yang
 * belum dilihat (id > last_seen_patch_id). Di halaman lain komponen ini diam
 * (tanpa kueri) sampai event 'open-patch-notes' dikirim dari menu user.
 *
 * Tiap rilis dipecah menjadi:
 * - slide sorotan: item perubahan yang punya gambar sebelum/sesudah;
 * - ringkasan: seluruh item, dikelompokkan per tipe, sebagai langkah terakhir.
 */
class PatchNotesModal extends Component
{
    /** Jumlah rilis terakhir yang dimuat saat popup dibuka manual. */
    public const REPLAY_LIMIT = 3;

    public bool $open = false;

    /** Data patch siap render (array, bukan model). */
    public array $patches = [];

    public function mount(): void
    {
        if (! $this->eligible() || ! request()->routeIs('filament.admin.pages.dashboard')) {
            return;
        }

        $unseen = $this->published()
            ->when(auth()->user()->last_seen_patch_id, fn ($q, $seenId) => $q->where('id', '>', $seenId))
            ->get();

        if ($unseen->isNotEmpty()) {
            $this->show($unseen);
        }
    }

    #[On('open-patch-notes')]
    public function openLatest(): void
    {
        if (! $this->eligible()) {
            return;
        }

        $latest = $this->published()->limit(self::REPLAY_LIMIT)->get();

        if ($latest->isNotEmpty()) {
            $this->show($latest);
        }
    }

    public function dismiss(): void
    {
        $user = auth()->user();

        if ($user && ($latestId = PatchNote::published()->max('id')) && $latestId > (int) $user->last_seen_patch_id) {
            $user->forceFill(['last_seen_patch_id' => $latestId])->save();
        }

        $this->open = false;
        $this->patches = [];
    }

    protected function eligible(): bool
    {
        $user = auth()->user();

        return $user && ! $user->hasAnyRole(['client']);
    }

    protected function published()
    {
        return PatchNote::published()->orderByDesc('released_at')->orderByDesc('id');
    }

    protected function show(Collection $patches): void
    {
        $this->patches = $patches->map(fn (PatchNote $p) => $this->mapPatch($p))->all();
        $this->open = true;
    }

    protected function mapPatch(PatchNote $patch): array
    {
        $changes = collect($patch->changes ?? [])
            ->map(fn (array $c) => [
                'type'      => $c['type'] ?? 'improvement',
                'area'      => $c['area'] ?? null,
                'title'     => filled($c['title'] ?? null) ? $c['title'] : null,
                'text'      => trim($c['text'] ?? ''),
                'before'    => PatchNote::imageUrl($c['image_before'] ?? null),
                'after'     => PatchNote::imageUrl($c['image_after'] ?? null),
                'location'  => filled($c['location'] ?? null) ? $c['location'] : null,
                'link'      => $this->reachableLink($c['location_url'] ?? null),
            ])
            ->filter(fn ($c) => $c['text'] !== '')
            ->values();

        // Item bergambar menjadi slide; nomor slidenya dipakai tombol
        // "Lihat tampilan" di ringkasan untuk melompat ke sana.
        $slideNo = 0;
        $changes = $changes->map(function ($c) use (&$slideNo) {
            return $c + ['slide' => ($c['before'] || $c['after']) ? $slideNo++ : null];
        });

        return [
            'id'          => $patch->id,
            'version'     => $patch->version,
            'title'       => $patch->title,
            'description' => $patch->description,
            'released_at' => optional($patch->released_at)->translatedFormat('d M Y'),
            'slides'      => $changes->filter(fn ($c) => $c['before'] || $c['after'])->values()->all(),
            'groups'      => collect(array_keys(PatchNote::CHANGE_TYPES))
                ->mapWithKeys(fn ($type) => [$type => $changes->where('type', $type)->values()->all()])
                ->filter()
                ->all(),
        ];
    }

    /**
     * URL tujuan tombol "Buka halaman", atau null bila user tidak berhak membuka
     * halaman itu. Tanpa cek ini staf tanpa izin akan diantar ke halaman 403.
     */
    protected function reachableLink(?string $path): ?string
    {
        if (blank($path)) {
            return null;
        }

        try {
            $route = app('router')->getRoutes()->match(Request::create($path));
            $page = $route->getControllerClass();

            if ($page && method_exists($page, 'canAccess') && ! $page::canAccess()) {
                return null;
            }
        } catch (\Throwable) {
            // Rute tak dikenal atau cek akses butuh parameter: tetap tampilkan
            // tautannya, biar halaman tujuan yang memutuskan.
        }

        return url($path);
    }

    public function render()
    {
        return view('livewire.patch-notes-modal');
    }
}
