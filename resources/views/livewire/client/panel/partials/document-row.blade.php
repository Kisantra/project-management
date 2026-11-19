{{--
    One document row in the client portal's Dokumen list (DocumentTab::legalRow,
    requirementRow, additionalRow). Columns: icon, name + meta, status, actions.
    $row['actions']: [type, ...args] with type preview | download | delete
    (document id) or upload | reupload (kind "sop"/"req", item id).
--}}
@php
    $uploadCall = fn (string $kind, int $id) => $kind === 'sop'
        ? "openUploadModal({$clientId}, {$id}, false)"
        : "openUploadModal({$clientId}, null, false, {$id})";
@endphp

<div @class(['kpd-row', 'kpd-row--child' => $row['child'] ?? false]) wire:key="{{ $row['key'] }}">
    <span @class(['kpd-ft', 'kpd-ft--ok' => $row['state'] === 'ok', 'kpd-ft--missing' => $row['state'] === 'missing'])>
        @if ($row['state'] === 'ok')
            <x-heroicon-o-document-check class="h-4 w-4" />
        @else
            <x-heroicon-o-document class="h-4 w-4" />
        @endif
    </span>

    <div class="kpd-main">
        <p class="kpd-name">
            <span>{{ $row['name'] }}</span>
            @foreach ($row['tags'] as $tag)
                <x-kis.badge :tone="$tag['tone']" size="sm" :dot="$tag['tone'] === 'red'">{{ $tag['text'] }}</x-kis.badge>
            @endforeach
        </p>
        @if (! empty($row['meta']))
            <p class="kpd-meta">
                @foreach ($row['meta'] as $meta)
                    <span @class(['is-red' => $meta['tone'] === 'red', 'is-file' => $meta['file'] ?? false])>{{ $meta['text'] }}</span>
                @endforeach
            </p>
        @endif
    </div>

    <div class="kpd-status">
        <x-kis.badge :tone="$row['status']['tone']" :dot="$row['status']['dot']">{{ $row['status']['label'] }}</x-kis.badge>
    </div>

    <div class="kpd-acts">
        @foreach ($row['actions'] as $action)
            @switch($action[0])
                @case('preview')
                    <button type="button" class="kpd-ib" wire:click="previewDocuments({{ $action[1] }})" title="Lihat" aria-label="Lihat {{ $row['name'] }}">
                        <x-heroicon-o-eye class="h-4 w-4" />
                    </button>
                    @break
                @case('download')
                    <button type="button" class="kpd-ib" wire:click="downloadDocument({{ $action[1] }})" title="Unduh" aria-label="Unduh {{ $row['name'] }}">
                        <x-heroicon-o-arrow-down-tray class="h-4 w-4" />
                    </button>
                    @break
                @case('reupload')
                    <button type="button" class="kpd-ib" wire:click="{{ $uploadCall($action[1], $action[2]) }}" title="Unggah ulang" aria-label="Unggah ulang {{ $row['name'] }}">
                        <x-heroicon-o-arrow-path class="h-4 w-4" />
                    </button>
                    @break
                @case('delete')
                    <button type="button" class="kpd-ib kpd-ib--danger" wire:click="deleteDocumentConfirm({{ $action[1] }})" title="Hapus" aria-label="Hapus {{ $row['name'] }}">
                        <x-heroicon-o-trash class="h-4 w-4" />
                    </button>
                    @break
                @case('upload')
                    <button type="button" class="kpd-up" wire:click="{{ $uploadCall($action[1], $action[2]) }}">
                        <x-heroicon-m-arrow-up-tray class="h-4 w-4" />
                        Unggah
                    </button>
                    @break
            @endswitch
        @endforeach
    </div>
</div>
