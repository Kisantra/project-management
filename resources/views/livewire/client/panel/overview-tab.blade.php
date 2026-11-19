{{--
    Ringkasan (client portal home), docs/mockups/client-portal-redesign.html.
    Header, rejected-documents callout, one summary strip, then "Dokumen Wajib"
    (to upload / archive) beside the activity feed. Styles: client-portal/overview.css.
--}}
@php
    use App\Filament\Client\Pages\Documents;
    use App\Support\ClientPortal\ClientContext;
    use App\Support\Ui\Tone;
    use Carbon\Carbon;
    use Illuminate\Support\Facades\Storage;

    $uploadUrl = fn (array $doc) => Documents::getUrl([
        'unggah' => $doc['type'] === 'requirement' ? 'req-' . $doc['requirement_id'] : 'sop-' . $doc['sop_id'],
    ]);
    $date = fn ($value, string $format = 'd M Y') => Carbon::parse($value)->locale('id')->translatedFormat($format);
@endphp

<div class="kp-a">
    @if ($clients->isEmpty())
        <div class="kp-a-card kp-a-empty kp-a-empty--page">
            <span class="kp-a-empty__icon"><x-heroicon-o-link class="h-5 w-5" /></span>
            <b>Akun belum terhubung</b>
            <span>Akun Anda belum terhubung dengan perusahaan mana pun. Tim Kisantra perlu memberi akses sebelum ringkasan bisa ditampilkan.</span>
        </div>
    @else
        @php
            $statusTone = Tone::forClientStatus($selectedClient?->status);
            $uploaded = $allDocumentsChecklist->where('is_uploaded', true);
            $docUploaded = $uploaded->count();
            $docTotal = $allDocumentsChecklist->count();
            $docPct = $docTotal > 0 ? (int) round($docUploaded / $docTotal * 100) : 0;
            $projectPct = fn (int $n) => $projectStats['total'] > 0 ? (int) round($n / $projectStats['total'] * 100) : 0;
            $cells = [
                ['label' => 'Proyek aktif', 'value' => $projectStats['active'], 'total' => $projectStats['total'], 'pct' => $projectPct($projectStats['active']), 'note' => 'proyek sedang berjalan'],
                ['label' => 'Selesai', 'value' => $projectStats['completed'], 'total' => $projectStats['total'], 'pct' => $projectPct($projectStats['completed']),
                    'note' => 'proyek berhasil diselesaikan' . ($projectStats['pending'] > 0 ? ' · ' . $projectStats['pending'] . ' tertunda' : '')],
                ['label' => 'Laporan pajak', 'value' => $taxReportStats['reported'], 'total' => $taxReportStats['total'], 'pct' => (int) $taxReportStats['completion_percentage'], 'note' => 'laporan sudah dilaporkan'],
                ['label' => 'Dokumen', 'value' => $docUploaded, 'total' => $docTotal, 'pct' => $docPct, 'note' => 'dokumen telah diunggah'],
            ];
        @endphp

        {{-- Header --}}
        <header class="kp-a-head">
            <span class="kp-a-head__tile">
                @if ($selectedClient?->logo)
                    <img src="{{ Storage::url($selectedClient->logo) }}" alt="" onerror="this.remove()">
                @endif
                {{ ClientContext::initials($selectedClient?->name) }}
            </span>
            <div class="kp-a-head__text">
                <h1>
                    <span>{{ $selectedClient?->name ?? 'Ringkasan' }}</span>
                    @if ($selectedClient)
                        <x-kis.badge :tone="$statusTone" dot>{{ $selectedClient->status === 'Active' ? 'Aktif' : 'Tidak aktif' }}</x-kis.badge>
                    @endif
                </h1>
                <p>Ringkasan Manajemen &amp; Operasional</p>
            </div>
            <div class="kp-a-head__acts">
                <button type="button" wire:click="refresh" wire:loading.attr="disabled" class="kis-btn kis-btn--outline">
                    <x-heroicon-m-arrow-path class="h-4 w-4 kp-a-muted" wire:loading.class="animate-spin" wire:target="refresh" />
                    Segarkan
                </button>
            </div>
        </header>

        {{-- Rejected documents: the one red callout on this page --}}
        @if ($rejectedDocuments->isNotEmpty())
            <section class="kp-ov-alert" aria-labelledby="kp-ov-alert-title">
                <span class="kp-ov-alert__icon"><x-heroicon-m-exclamation-triangle class="h-4 w-4" /></span>
                <div class="kp-ov-alert__body">
                    <h2 id="kp-ov-alert-title">{{ $rejectedDocuments->count() }} dokumen ditolak</h2>
                    <ul>
                        @foreach ($rejectedDocuments->take(3) as $doc)
                            <li>
                                <b>{{ $doc['name'] }}</b>
                                <span>{{ $doc['admin_notes'] ?: 'Periksa kembali berkasnya lalu unggah ulang.' }}</span>
                                <a href="{{ $uploadUrl($doc) }}" class="kp-a-link">Unggah ulang</a>
                            </li>
                        @endforeach
                    </ul>
                </div>
            </section>
        @endif

        {{-- Summary strip --}}
        <section class="kp-a-card kp-a-strip" aria-label="Ringkasan angka">
            @foreach ($cells as $cell)
                <div class="kp-a-strip__cell">
                    <span class="kp-a-label">{{ $cell['label'] }}</span>
                    <p class="kp-a-strip__fig">{{ $cell['value'] }}<small>/ {{ $cell['total'] }}</small></p>
                    <div class="kp-a-meter" role="img" aria-label="{{ $cell['pct'] }}%">
                        <span class="kp-a-meter__bar"><i style="width: {{ $cell['pct'] }}%"></i></span>
                        <b>{{ $cell['pct'] }}%</b>
                    </div>
                    <p class="kp-a-strip__note">{{ $cell['note'] }}</p>
                </div>
            @endforeach
        </section>

        <div class="kp-ov-grid">
            {{-- Required documents --}}
            <section class="kp-a-card kp-ov-docs" x-data="{ tab: 'pending' }">
                <header class="kp-a-card__head kp-ov-docs__head">
                    <div class="min-w-0">
                        <h2 class="kp-a-card__title">Dokumen Wajib</h2>
                        <p class="kp-a-card__sub">{{ $docUploaded }} dari {{ $docTotal }} dokumen telah diunggah</p>
                    </div>
                    <div class="kp-ov-progress">
                        <span class="kp-ov-ring" style="--p: {{ $docPct }}" role="img" aria-label="{{ $docPct }}% diunggah"><b>{{ $docPct }}%</b></span>
                        <span class="kp-ov-progress__cap"><b>{{ $pendingDocuments->count() }}</b>perlu diunggah</span>
                    </div>
                </header>

                <div class="kp-ov-docs__tabs">
                    <div class="kis-rail__wrap kp-ov-rail" role="tablist" aria-label="Dokumen wajib">
                        <button type="button" role="tab" class="kis-rail__chip" x-bind:class="tab === 'pending' && 'is-on'"
                                x-bind:aria-selected="tab === 'pending'" x-on:click="tab = 'pending'">
                            <x-heroicon-o-arrow-up-tray class="h-4 w-4" /> Perlu Diunggah <em>{{ $pendingDocuments->count() }}</em>
                        </button>
                        <button type="button" role="tab" class="kis-rail__chip" x-bind:class="tab === 'archive' && 'is-on'"
                                x-bind:aria-selected="tab === 'archive'" x-on:click="tab = 'archive'">
                            <x-heroicon-o-archive-box class="h-4 w-4" /> Arsip <em>{{ $uploaded->count() }}</em>
                        </button>
                    </div>
                </div>

                {{-- To upload --}}
                <ul class="kp-a-list" x-show="tab === 'pending'" role="tabpanel">
                    @forelse ($pendingDocuments as $doc)
                        @php
                            $due = $doc['due_date'] ? Carbon::parse($doc['due_date']) : null;
                            $overdue = $due?->isPast();
                        @endphp
                        <li class="kp-a-row">
                            <span class="kp-a-icon"><x-heroicon-o-document-text class="h-[18px] w-[18px]" /></span>
                            <div class="kp-a-row__text">
                                <p class="kp-a-row__title">
                                    <span>{{ $doc['name'] }}</span>
                                    <x-kis.badge size="sm">{{ $doc['type'] === 'requirement' ? 'Administratif' : 'Standar Legal' }}</x-kis.badge>
                                    @if ($doc['is_required'])
                                        <x-kis.badge size="sm" tone="outline">Wajib</x-kis.badge>
                                    @endif
                                </p>
                                <p @class(['kp-a-row__meta', 'kp-a-red' => $overdue])>
                                    @if ($due)
                                        {{ $overdue ? 'Lewat tenggat' : 'Tenggat' }} {{ $date($due) }}
                                    @else
                                        Tidak ada tenggat
                                    @endif
                                </p>
                            </div>
                            <a href="{{ $uploadUrl($doc) }}" class="kis-btn kis-btn--outline kis-btn--sm kp-a-btn-blue">
                                <x-heroicon-m-arrow-up-tray class="h-4 w-4" /> Unggah
                            </a>
                        </li>
                    @empty
                        <li class="kp-a-empty">
                            <span class="kp-a-empty__icon"><x-heroicon-o-check class="h-5 w-5" /></span>
                            <b>Semua dokumen selesai</b>
                            <span>Tidak ada dokumen yang perlu diunggah.</span>
                        </li>
                    @endforelse
                </ul>

                {{-- Archive --}}
                <ul class="kp-a-list" x-show="tab === 'archive'" x-cloak role="tabpanel">
                    @forelse ($uploaded as $doc)
                        @php
                            [$tone, $label] = match ($doc['status']) {
                                'valid' => [Tone::BLUE, 'Valid'],
                                'pending_review' => [Tone::OUTLINE, 'Ditinjau'],
                                'rejected', 'expired' => [Tone::RED, 'Ditandai'],
                                default => [Tone::NEUTRAL, 'Ditandai'],
                            };
                        @endphp
                        <li class="kp-a-row">
                            <span class="kp-a-icon kp-a-icon--blue"><x-heroicon-o-document-check class="h-[18px] w-[18px]" /></span>
                            <div class="kp-a-row__text">
                                <p class="kp-a-row__title"><span title="{{ $doc['name'] }}">{{ $doc['name'] }}</span></p>
                                <p class="kp-a-row__meta">
                                    <x-kis.badge size="sm" :tone="$tone" dot>{{ $label }}</x-kis.badge>
                                    @if ($doc['uploaded_at'])
                                        <span class="kp-a-num">{{ $date($doc['uploaded_at']) }}</span>
                                    @endif
                                </p>
                            </div>
                            @if ($doc['file_path'] && $doc['uploaded_document'])
                                <button type="button" wire:click="downloadDocument({{ $doc['uploaded_document']->id }})"
                                        class="kis-btn kis-btn--outline kis-btn--sm" title="Unduh Berkas">
                                    <x-heroicon-m-arrow-down-tray class="h-4 w-4 kp-a-muted" /> Unduh
                                </button>
                            @endif
                        </li>
                    @empty
                        <li class="kp-a-empty">
                            <span class="kp-a-empty__icon"><x-heroicon-o-archive-box class="h-5 w-5" /></span>
                            <b>Arsip kosong</b>
                            <span>Belum ada dokumen yang diunggah.</span>
                        </li>
                    @endforelse
                </ul>

                <p class="kp-a-card__foot">
                    <x-heroicon-o-information-circle class="h-4 w-4 flex-none" />
                    Dokumen yang sudah diunggah pindah ke Arsip dan diperiksa tim Kisantra.
                </p>
            </section>

            {{-- Activity --}}
            <section class="kp-a-card kp-ov-feed">
                <header class="kp-a-card__head kp-a-card__head--line">
                    <h2 class="kp-a-card__title">Aktivitas</h2>
                </header>
                @if ($recentActivities->isEmpty())
                    <div class="kp-a-empty kp-ov-feed__empty">
                        <span class="kp-a-empty__icon"><x-heroicon-o-clock class="h-5 w-5" /></span>
                        <span>Belum ada aktivitas.</span>
                    </div>
                @else
                    <ol class="kp-ov-feed__list">
                        @foreach ($recentActivities as $activity)
                            @php
                                [$label, $icon] = match (true) {
                                    str_contains($activity->action, 'upload') => ['Unggah', 'heroicon-m-arrow-up-tray'],
                                    str_contains($activity->action, 'created') => ['Dibuat', 'heroicon-m-plus'],
                                    str_contains($activity->action, 'approved') => ['Disetujui', 'heroicon-m-check'],
                                    str_contains($activity->action, 'rejected') => ['Ditolak', 'heroicon-m-x-mark'],
                                    str_contains($activity->action, 'updated') => ['Diperbarui', 'heroicon-m-pencil'],
                                    str_contains($activity->action, 'deleted') => ['Dihapus', 'heroicon-m-trash'],
                                    str_contains($activity->action, 'report') => ['Laporan', 'heroicon-m-document-chart-bar'],
                                    default => ['Aktivitas', 'heroicon-m-bolt'],
                                };
                                $at = $activity->created_at;
                                $when = $at->isToday() ? $at->format('H.i') : ($at->isYesterday() ? 'Kemarin' : $date($at, 'd M'));
                            @endphp
                            <li class="kp-ov-feed__item">
                                <span @class(['kp-ov-feed__dot', 'is-red' => $label === 'Ditolak'])><x-filament::icon :icon="$icon" class="h-3.5 w-3.5" /></span>
                                <div class="min-w-0">
                                    <p class="kp-ov-feed__meta">
                                        <x-kis.badge size="sm" :tone="$label === 'Ditolak' ? Tone::RED : Tone::NEUTRAL">{{ $label }}</x-kis.badge>
                                        <span class="kp-a-num">{{ $when }}</span>
                                    </p>
                                    <p class="kp-ov-feed__text">{{ $activity->description }}</p>
                                    @if ($activity->user)
                                        <p class="kp-ov-feed__who">{{ $activity->user->name }}</p>
                                    @endif
                                </div>
                            </li>
                        @endforeach
                    </ol>
                @endif
            </section>
        </div>
    @endif
</div>
