{{-- Hierarchical status picker dropdown
     Required vars:
       $project   — current project (id, status)
       $statuses  — full $statuses array keyed by status key
     Required Alpine scope: an ancestor with x-data="{ open: ... }"
--}}
<div x-show="open" @click.outside="open = false" x-cloak class="cu-dropdown-panel cu-status-picker">
    @php
        $sections = [];
        foreach ($statuses as $sKey => $sMeta) {
            $sections[$sMeta['category']][$sKey] = $sMeta;
        }
        $sectionLabels = [
            'not_started' => 'Not Started',
            'active'      => 'Active',
            'done'        => 'Done',
            'closed'      => 'Closed',
        ];
    @endphp
    @foreach ($sectionLabels as $sectionKey => $sectionLabel)
        @if (!empty($sections[$sectionKey]))
            <div class="cu-status-section">
                <div class="cu-status-section-head">{{ $sectionLabel }}</div>
                @foreach ($sections[$sectionKey] as $key => $meta)
                    @php $isCurrent = $project->status === $key; @endphp
                    <button type="button"
                            wire:click="updateStatus({{ $project->id }}, '{{ $key }}')"
                            @click="open = false"
                            class="cu-status-option {{ $isCurrent ? 'is-current' : '' }}">
                        <span class="cu-status-option-icon">
                            @include('livewire.projects.partials.status-shape', ['shape' => $meta['shape'] ?? 'empty', 'color' => $meta['color'], 'tone' => $meta['tone'] ?? null, 'size' => 18])
                        </span>
                        <span class="cu-status-option-label">{{ $meta['label'] }}</span>
                        @if ($isCurrent)
                            <svg class="cu-status-option-check" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>
                        @endif
                    </button>
                @endforeach
            </div>
        @endif
    @endforeach
    @if (auth()->user()->hasRole('super-admin'))
        <div class="cu-status-picker-footer">
            <button type="button"
                    wire:click="openStatusManager"
                    @click="open = false"
                    class="cu-status-picker-manage">
                <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round"><path d="M12 20h9"/><path d="M16.5 3.5a2.121 2.121 0 0 1 3 3L7 19l-4 1 1-4L16.5 3.5z"/></svg>
                Kelola Status
            </button>
        </div>
    @endif
</div>
