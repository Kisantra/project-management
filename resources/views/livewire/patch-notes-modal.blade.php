@php
    $typeLabels = \App\Models\PatchNote::CHANGE_TYPES;
@endphp

<div>
@if ($open && count($patches))
    <div
        class="pnm"
        wire:key="patch-notes-modal"
        x-data="{
            shown: false,
            p: 0,
            s: 0,
            view: 'after',
            steps: @js(array_map(fn ($patch) => count($patch['slides']) + 1, $patches)),
            get total() { return this.steps[this.p] },
            get last() { return this.s === this.total - 1 },
            go(i) {
                if (i < 0 || i >= this.total) return
                this.s = i
                this.view = 'after'
                this.$refs['body' + this.p]?.scrollTo({ top: 0 })
            },
            release(i) { this.p = i; this.s = 0; this.view = 'after' },
            close() {
                if (! this.shown) return
                this.shown = false
                setTimeout(() => this.$wire.dismiss(), 180)
            },
        }"
        x-init="$nextTick(() => { shown = true; setTimeout(() => $refs.dialog.focus(), 60) })"
        x-on:keydown.escape.window="close()"
        x-on:keydown.arrow-right.window="if (! last) go(s + 1)"
        x-on:keydown.arrow-left.window="go(s - 1)"
    >
        <div class="pnm-backdrop" x-show="shown" x-transition.opacity.duration.180ms x-on:click="close()" aria-hidden="true"></div>

        <div
            class="pnm-dialog"
            role="dialog"
            aria-modal="true"
            aria-labelledby="pnm-title"
            tabindex="-1"
            x-ref="dialog"
            x-show="shown"
            x-trap.noscroll.noautofocus="shown"
            x-transition:enter="pnm-t-enter"
            x-transition:enter-start="pnm-t-from"
            x-transition:leave="pnm-t-leave"
            x-transition:leave-end="pnm-t-from"
        >
            <span class="pnm-accentline" aria-hidden="true"></span>

            @foreach ($patches as $pi => $patch)
                @php
                    $slideCount = count($patch['slides']);
                    $changeCount = collect($patch['groups'])->flatten(1)->count();
                @endphp

                <div class="pnm-release" x-show="p === {{ $pi }}" @if ($pi > 0) x-cloak @endif>
                    {{-- Header --}}
                    <header class="pnm-head">
                        <div class="pnm-head-top">
                            <span class="pnm-kicker"><span class="pnm-kicker-dot"></span>Pembaruan Sistem</span>
                            <div class="pnm-head-tools">
                                @if (count($patches) > 1)
                                    <nav class="pnm-releases" aria-label="Pilih rilis">
                                        @foreach ($patches as $ri => $r)
                                            <button type="button" class="pnm-release-tab" x-on:click="release({{ $ri }})"
                                                :class="p === {{ $ri }} && 'is-active'" :aria-current="p === {{ $ri }} ? 'true' : null">
                                                {{ $r['version'] }}
                                            </button>
                                        @endforeach
                                    </nav>
                                @endif
                                <button type="button" class="pnm-iconbtn" x-on:click="close()" aria-label="Tutup">
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M18 6 6 18M6 6l12 12"/></svg>
                                </button>
                            </div>
                        </div>
                        <div class="pnm-titlerow">
                            <h2 class="pnm-title" @if ($pi === 0) id="pnm-title" @endif>{{ $patch['title'] }}</h2>
                            <span class="pnm-version">{{ $patch['version'] }}</span>
                            @if ($patch['released_at'])<span class="pnm-date">{{ $patch['released_at'] }}</span>@endif
                        </div>
                    </header>

                    {{-- Body --}}
                    <div class="pnm-body" x-ref="body{{ $pi }}">
                        @foreach ($patch['slides'] as $si => $slide)
                            @php $hasBoth = $slide['before'] && $slide['after']; @endphp
                            <section class="pnm-slide" x-show="p === {{ $pi }} && s === {{ $si }}" x-transition:enter="pnm-fade" x-transition:enter-start="pnm-fade-from" @if ($si > 0) x-cloak @endif>
                                <figure class="pnm-media">
                                    @if ($hasBoth)
                                        <div class="pnm-seg" role="group" aria-label="Bandingkan tampilan">
                                            <button type="button" class="pnm-seg-btn" x-on:click="view = 'before'" :class="view === 'before' && 'is-on'" :aria-pressed="view === 'before'">Sebelum</button>
                                            <button type="button" class="pnm-seg-btn" x-on:click="view = 'after'" :class="view === 'after' && 'is-on'" :aria-pressed="view === 'after'">Sesudah</button>
                                        </div>
                                    @endif

                                    <div class="pnm-stack">
                                        @if ($slide['before'])
                                            <img src="{{ $slide['before'] }}" alt="Tampilan sebelum pembaruan: {{ $slide['title'] ?? $slide['area'] }}"
                                                class="pnm-shot {{ $hasBoth ? '' : 'is-on' }}" decoding="async" @if ($si > 0) loading="lazy" @endif
                                                @if ($hasBoth) :class="view === 'before' && 'is-on'" @endif>
                                        @endif
                                        @if ($slide['after'])
                                            <img src="{{ $slide['after'] }}" alt="Tampilan sesudah pembaruan: {{ $slide['title'] ?? $slide['area'] }}"
                                                class="pnm-shot {{ $hasBoth ? '' : 'is-on' }}" decoding="async" @if ($si > 0) loading="lazy" @endif
                                                @if ($hasBoth) :class="view === 'after' && 'is-on'" @endif>
                                        @endif
                                    </div>

                                    @if (! $hasBoth)
                                        <figcaption class="pnm-onlytag">{{ $slide['after'] ? 'Tampilan baru' : 'Tampilan lama' }}</figcaption>
                                    @endif

                                    <a class="pnm-zoom" target="_blank" rel="noopener" aria-label="Buka gambar ukuran penuh"
                                        @if ($hasBoth) :href="view === 'before' ? @js($slide['before']) : @js($slide['after'])" @endif
                                        href="{{ $slide['after'] ?? $slide['before'] }}">
                                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M15 3h6v6M9 21H3v-6M21 3l-7 7M3 21l7-7"/></svg>
                                    </a>
                                </figure>

                                <div class="pnm-copy">
                                    <div class="pnm-meta">
                                        <span class="pnm-type pnm-type-{{ $slide['type'] }}">{{ $typeLabels[$slide['type']] ?? $slide['type'] }}</span>
                                        @if ($slide['area'])<span class="pnm-area">{{ $slide['area'] }}</span>@endif
                                        <span class="pnm-count">Sorotan {{ $si + 1 }} dari {{ $slideCount }}</span>
                                    </div>
                                    @if ($slide['title'])<h3 class="pnm-slide-title">{{ $slide['title'] }}</h3>@endif
                                    <p class="pnm-text">{{ $slide['text'] }}</p>

                                    @if ($slide['location'] || $slide['link'])
                                        <div class="pnm-loc">
                                            <svg class="pnm-loc-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 21s-7-6.2-7-11.5A7 7 0 0 1 19 9.5C19 14.8 12 21 12 21Z"/><circle cx="12" cy="9.5" r="2.5"/></svg>
                                            <span class="pnm-loc-label">Letak</span>
                                            @if ($slide['location'])
                                                <span class="pnm-crumbs">
                                                    @foreach (preg_split('/\s*›\s*/u', $slide['location']) as $ci => $crumb)
                                                        @if ($ci > 0)<span class="pnm-crumb-sep" aria-hidden="true">›</span>@endif
                                                        <span class="pnm-crumb">{{ $crumb }}</span>
                                                    @endforeach
                                                </span>
                                            @endif
                                            @if ($slide['link'])
                                                <a href="{{ $slide['link'] }}" class="pnm-loc-link" x-on:click="close()">
                                                    Buka halaman
                                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14M13 6l6 6-6 6"/></svg>
                                                </a>
                                            @endif
                                        </div>
                                    @endif
                                </div>
                            </section>
                        @endforeach

                        {{-- Ringkasan: seluruh perubahan pada rilis ini --}}
                        <section class="pnm-summary" x-show="p === {{ $pi }} && s === {{ $slideCount }}" x-transition:enter="pnm-fade" x-transition:enter-start="pnm-fade-from" @if ($slideCount > 0) x-cloak @endif>
                            @if ($patch['description'])
                                <p class="pnm-desc">{{ $patch['description'] }}</p>
                            @endif

                            <div class="pnm-groups">
                                @foreach ($patch['groups'] as $type => $items)
                                    <div class="pnm-group pnm-group-{{ $type }}">
                                        <div class="pnm-ghead">
                                            <span class="pnm-gdot" aria-hidden="true"></span>
                                            <span class="pnm-glabel">{{ $typeLabels[$type] ?? $type }}</span>
                                            <span class="pnm-gcount">{{ count($items) }}</span>
                                        </div>
                                        <ul class="pnm-items">
                                            @foreach ($items as $c)
                                                <li class="pnm-item">
                                                    @if ($c['area'])<span class="pnm-item-area">{{ $c['area'] }}</span>@endif
                                                    <div class="pnm-item-body">
                                                        <p class="pnm-item-text">
                                                            @if ($c['title'])<strong>{{ $c['title'] }}.</strong> @endif{{ $c['text'] }}
                                                        </p>
                                                        @if ($c['slide'] !== null || $c['link'])
                                                            <div class="pnm-item-actions">
                                                                @if ($c['slide'] !== null)
                                                                    <button type="button" class="pnm-item-action is-view" x-on:click="go({{ $c['slide'] }})">
                                                                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="16" rx="2"/><circle cx="9" cy="10" r="2"/><path d="m21 17-5-5L5 20"/></svg>
                                                                        Lihat tampilan
                                                                    </button>
                                                                @endif
                                                                @if ($c['link'])
                                                                    <a href="{{ $c['link'] }}" class="pnm-item-action" x-on:click="close()">
                                                                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 21s-7-6.2-7-11.5A7 7 0 0 1 19 9.5C19 14.8 12 21 12 21Z"/><circle cx="12" cy="9.5" r="2.5"/></svg>
                                                                        {{ $c['location'] ?? 'Buka halaman' }}
                                                                    </a>
                                                                @elseif ($c['location'])
                                                                    <span class="pnm-item-action is-static">{{ $c['location'] }}</span>
                                                                @endif
                                                            </div>
                                                        @endif
                                                    </div>
                                                </li>
                                            @endforeach
                                        </ul>
                                    </div>
                                @endforeach
                            </div>
                        </section>
                    </div>

                    {{-- Footer --}}
                    <footer class="pnm-foot">
                        <div class="pnm-steps" role="tablist" aria-label="Langkah">
                            @foreach ($patch['slides'] as $si => $slide)
                                <button type="button" class="pnm-dot" x-on:click="go({{ $si }})"
                                    :class="s === {{ $si }} && 'is-on'" :aria-current="s === {{ $si }} ? 'step' : null"
                                    aria-label="Sorotan {{ $si + 1 }}"></button>
                            @endforeach
                            <button type="button" class="pnm-all" x-on:click="go({{ $slideCount }})"
                                :class="s === {{ $slideCount }} && 'is-on'" :aria-current="s === {{ $slideCount }} ? 'step' : null">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M8 6h13M8 12h13M8 18h13M3 6h.01M3 12h.01M3 18h.01"/></svg>
                                <span class="pnm-all-label">Semua perubahan</span> <span class="pnm-all-count">{{ $changeCount }}</span>
                            </button>
                        </div>

                        <div class="pnm-actions">
                            <button type="button" class="pnm-btn pnm-btn-ghost" x-on:click="go(s - 1)" x-show="s > 0" x-cloak>Kembali</button>
                            <button type="button" class="pnm-btn pnm-btn-primary" x-on:click="last ? close() : go(s + 1)">
                                <span x-text="last ? 'Selesai' : 'Lanjut'">{{ $slideCount ? 'Lanjut' : 'Selesai' }}</span>
                                <svg x-show="! last" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14M13 6l6 6-6 6"/></svg>
                            </button>
                        </div>
                    </footer>
                </div>
            @endforeach
        </div>
    </div>

    <style>
        .pnm {
            --pnm-bg: #ffffff;
            --pnm-raised: #f7f8fa;
            --pnm-border: #e6e8ed;
            --pnm-ink: #0f172a;
            --pnm-muted: #556070;
            --pnm-subtle: #8b95a5;
            --pnm-line: #eef0f4;
            --pnm-dot: rgba(15,23,42,.05);
            --pnm-accent: #0891b2;
            --pnm-accent-ink: #0e7490;
            --pnm-accent-bg: #ecfeff;
            --pnm-accent-border: rgba(8,145,178,.24);
            --pnm-shadow: 0 24px 64px -24px rgba(15,23,42,.35), 0 2px 6px rgba(15,23,42,.06);
            --pnm-scrim: rgba(15,23,42,.42);
            --pnm-thumb: rgba(15,23,42,.18);

            position: fixed; inset: 0; z-index: 60;
            display: flex; align-items: center; justify-content: center;
            padding: 24px;
            font-family: 'Plus Jakarta Sans', system-ui, sans-serif;
            color: var(--pnm-ink);
        }
        .dark .pnm {
            --pnm-bg: #161616;
            --pnm-raised: #1d1d1f;
            --pnm-border: #2c2c2e;
            --pnm-ink: #f3f4f6;
            --pnm-muted: #a1a1aa;
            --pnm-subtle: #71717a;
            --pnm-line: #242426;
            --pnm-dot: rgba(255,255,255,.05);
            --pnm-accent: #22d3ee;
            --pnm-accent-ink: #67e8f9;
            --pnm-accent-bg: rgba(8,51,68,.5);
            --pnm-accent-border: rgba(34,211,238,.3);
            --pnm-shadow: 0 24px 64px -20px rgba(0,0,0,.8);
            --pnm-scrim: rgba(0,0,0,.6);
            --pnm-thumb: rgba(255,255,255,.18);
        }
        .pnm [x-cloak] { display: none !important; }

        .pnm-backdrop { position: absolute; inset: 0; background: var(--pnm-scrim); backdrop-filter: blur(3px); }

        .pnm-dialog {
            position: relative;
            display: flex; flex-direction: column;
            width: min(1000px, 100%);
            /* Tinggi tetap: tombol Lanjut tidak bergeser di bawah kursor saat
               berpindah slide. Area gambar mengisi sisa ruang. */
            height: min(880px, calc(100dvh - 48px));
            background: var(--pnm-bg);
            border: 1px solid var(--pnm-border);
            border-radius: 18px;
            box-shadow: var(--pnm-shadow);
            overflow: hidden;
        }
        .pnm-t-enter { transition: opacity .2s ease-out, transform .2s cubic-bezier(.2,.8,.3,1); }
        .pnm-t-leave { transition: opacity .16s ease-in, transform .16s ease-in; }
        .pnm-t-from  { opacity: 0; transform: translateY(10px) scale(.985); }
        .pnm-fade { transition: opacity .18s ease-out; }
        .pnm-fade-from { opacity: 0; }

        .pnm-accentline {
            position: absolute; top: 0; left: 0; right: 0; height: 3px; z-index: 1;
            background: linear-gradient(90deg, #34d399 0 33.3%, #60a5fa 33.3% 66.6%, #fbbf24 66.6% 100%);
        }

        .pnm-release { display: flex; flex-direction: column; min-height: 0; flex: 1; }

        /* Header */
        .pnm-head { flex-shrink: 0; padding: 16px 20px 0 24px; }
        .pnm-head-top { display: flex; align-items: center; justify-content: space-between; gap: 12px; min-height: 34px; }
        .pnm-head-tools { display: flex; align-items: center; gap: 10px; }
        .pnm-kicker { display: inline-flex; align-items: center; gap: 7px; white-space: nowrap; font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: .12em; color: var(--pnm-subtle); }
        .pnm-kicker-dot { width: 6px; height: 6px; border-radius: 50%; background: var(--pnm-accent); box-shadow: 0 0 0 3px var(--pnm-accent-bg); }
        .pnm-titlerow { display: flex; flex-wrap: wrap; align-items: center; gap: 8px 10px; margin-top: 2px; padding-right: 4px; }
        .pnm-title { margin: 0; font-size: 20px; line-height: 1.3; font-weight: 750; letter-spacing: -.015em; }
        .pnm-version {
            display: inline-flex; align-items: center; padding: 2px 8px; border-radius: 6px;
            font-size: 12px; font-weight: 700; font-variant-numeric: tabular-nums;
            color: var(--pnm-accent-ink); background: var(--pnm-accent-bg); box-shadow: inset 0 0 0 1px var(--pnm-accent-border);
        }
        .pnm-date { font-size: 12.5px; font-weight: 500; color: var(--pnm-subtle); }
        .pnm-iconbtn {
            flex-shrink: 0; display: inline-flex; align-items: center; justify-content: center;
            width: 34px; height: 34px; border: 0; border-radius: 9px;
            background: transparent; color: var(--pnm-subtle); cursor: pointer; transition: background .12s, color .12s;
        }
        .pnm-iconbtn svg { width: 18px; height: 18px; }
        .pnm-iconbtn:hover { background: var(--pnm-line); color: var(--pnm-ink); }

        .pnm-releases { display: flex; gap: 4px; }
        .pnm-release-tab {
            padding: 4px 10px; border: 0; border-radius: 99px; cursor: pointer;
            font: inherit; font-size: 12px; font-weight: 650; font-variant-numeric: tabular-nums;
            color: var(--pnm-muted); background: transparent; box-shadow: inset 0 0 0 1px var(--pnm-border);
            transition: background .12s, color .12s;
        }
        .pnm-release-tab:hover { color: var(--pnm-ink); }
        .pnm-release-tab.is-active { color: var(--pnm-accent-ink); background: var(--pnm-accent-bg); box-shadow: inset 0 0 0 1px var(--pnm-accent-border); }

        /* Body */
        .pnm-body { flex: 1; min-height: 0; overflow-y: auto; padding: 16px 24px 4px; scrollbar-width: thin; scrollbar-color: var(--pnm-thumb) transparent; }

        .pnm-slide { display: flex; flex-direction: column; min-height: 100%; }

        /* Media */
        .pnm-media {
            position: relative; margin: 0; flex: 1;
            display: flex; justify-content: center; align-items: center;
            padding: 50px 24px 24px;
            border-radius: 14px;
            background-color: var(--pnm-raised);
            background-image: radial-gradient(var(--pnm-dot) 1px, transparent 1px);
            background-size: 16px 16px;
            box-shadow: inset 0 0 0 1px var(--pnm-border);
        }
        .pnm-stack { display: grid; max-width: 100%; }
        .pnm-shot {
            grid-area: 1 / 1; justify-self: center; align-self: center;
            display: block; max-width: 100%; width: auto; height: auto;
            /* Sisakan ruang untuk header, teks, baris Letak, dan footer supaya
               semuanya terlihat tanpa scroll di layar laptop. */
            max-height: clamp(180px, calc(100dvh - 500px), 440px);
            border-radius: 10px;
            box-shadow: 0 0 0 1px var(--pnm-border), 0 10px 30px -14px rgba(15,23,42,.35);
            opacity: 0; transition: opacity .25s ease;
        }
        .pnm-shot.is-on { opacity: 1; }

        .pnm-seg {
            position: absolute; top: 12px; left: 50%; transform: translateX(-50%); z-index: 1;
            display: inline-flex; padding: 3px; gap: 2px; border-radius: 99px;
            background: var(--pnm-bg); box-shadow: 0 0 0 1px var(--pnm-border), 0 4px 12px -6px rgba(15,23,42,.25);
        }
        .pnm-seg-btn {
            min-width: 84px; padding: 5px 14px; border: 0; border-radius: 99px; cursor: pointer;
            font: inherit; font-size: 12.5px; font-weight: 650; color: var(--pnm-muted); background: transparent;
            transition: background .15s, color .15s;
        }
        .pnm-seg-btn:hover { color: var(--pnm-ink); }
        .pnm-seg-btn.is-on { color: #fff; background: var(--pnm-ink); }
        .dark .pnm-seg-btn.is-on { color: #0f172a; }

        .pnm-onlytag {
            position: absolute; top: 14px; left: 16px;
            font-size: 10.5px; font-weight: 700; text-transform: uppercase; letter-spacing: .08em; color: var(--pnm-subtle);
        }
        .pnm-zoom {
            position: absolute; top: 10px; right: 10px;
            display: inline-flex; align-items: center; justify-content: center; width: 30px; height: 30px; border-radius: 8px;
            color: var(--pnm-subtle); background: var(--pnm-bg); box-shadow: 0 0 0 1px var(--pnm-border);
            transition: color .12s;
        }
        .pnm-zoom svg { width: 15px; height: 15px; }
        .pnm-zoom:hover { color: var(--pnm-ink); }

        /* Copy */
        .pnm-copy { padding: 16px 2px 8px; }
        .pnm-slide-title, .pnm-text { max-width: 760px; }
        .pnm-meta { display: flex; flex-wrap: wrap; align-items: center; gap: 8px; }
        .pnm-type { font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: .07em; color: var(--acc-ink); }
        .pnm-type::before { content: ''; display: inline-block; width: 7px; height: 7px; margin-right: 6px; border-radius: 50%; background: var(--acc); vertical-align: 1px; }
        .pnm-area {
            font-size: 10.5px; font-weight: 700; text-transform: uppercase; letter-spacing: .04em; color: var(--pnm-muted);
            padding: 2px 7px; border-radius: 5px; background: var(--pnm-line);
        }
        .pnm-count { margin-left: auto; font-size: 12px; font-weight: 500; color: var(--pnm-subtle); font-variant-numeric: tabular-nums; }
        .pnm-slide-title { margin: 10px 0 0; font-size: 17px; line-height: 1.35; font-weight: 700; letter-spacing: -.01em; }
        .pnm-text { margin: 6px 0 0; font-size: 14px; line-height: 1.6; color: var(--pnm-muted); }

        .pnm-loc {
            display: flex; flex-wrap: wrap; align-items: center; gap: 6px 8px;
            margin-top: 14px; padding: 9px 10px 9px 12px; border-radius: 10px;
            background: var(--pnm-raised); box-shadow: inset 0 0 0 1px var(--pnm-border);
            font-size: 13px;
        }
        .pnm-loc-icon { width: 15px; height: 15px; color: var(--pnm-accent); flex-shrink: 0; }
        .pnm-loc-label { font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: .07em; color: var(--pnm-subtle); }
        .pnm-crumbs { display: inline-flex; flex-wrap: wrap; align-items: center; gap: 4px 6px; font-weight: 600; color: var(--pnm-ink); }
        .pnm-crumb-sep { color: var(--pnm-subtle); font-weight: 400; }
        .pnm-loc-link {
            margin-left: auto; display: inline-flex; align-items: center; gap: 6px;
            padding: 5px 10px; border-radius: 8px;
            font-size: 12.5px; font-weight: 650; color: var(--pnm-accent-ink); text-decoration: none;
            background: var(--pnm-accent-bg); box-shadow: inset 0 0 0 1px var(--pnm-accent-border);
            transition: filter .12s;
        }
        .pnm-loc-link svg { width: 14px; height: 14px; transition: transform .15s; }
        .pnm-loc-link:hover svg { transform: translateX(2px); }

        /* Summary */
        .pnm-desc { margin: 0 0 18px; font-size: 14px; line-height: 1.6; color: var(--pnm-muted); max-width: 700px; }
        .pnm-groups { display: flex; flex-direction: column; gap: 20px; padding-bottom: 8px; }
        .pnm-ghead { display: flex; align-items: center; gap: 8px; margin-bottom: 6px; }
        .pnm-gdot { width: 8px; height: 8px; border-radius: 50%; background: var(--acc); box-shadow: 0 0 0 3px color-mix(in srgb, var(--acc) 22%, transparent); }
        .pnm-glabel { font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: .07em; color: var(--acc-ink); }
        .pnm-gcount {
            display: inline-flex; align-items: center; justify-content: center; min-width: 18px; height: 17px; padding: 0 6px;
            font-size: 10.5px; font-weight: 700; color: var(--pnm-muted); background: var(--pnm-line); border-radius: 99px;
        }
        .pnm-items { list-style: none; margin: 0; padding: 0; }
        .pnm-item { display: flex; align-items: flex-start; gap: 12px; padding: 10px 0; border-top: 1px solid var(--pnm-line); }
        .pnm-item:first-child { border-top: 0; }
        .pnm-item-area {
            flex-shrink: 0; width: 76px; margin-top: 2px; text-align: center;
            font-size: 10px; font-weight: 700; text-transform: uppercase; letter-spacing: .04em; color: var(--pnm-muted);
            padding: 3px 6px; border-radius: 5px; background: var(--pnm-line);
        }
        .pnm-item-body { flex: 1; min-width: 0; }
        .pnm-item-text { margin: 0; font-size: 13.5px; line-height: 1.6; color: var(--pnm-ink); }
        .pnm-item-text strong { font-weight: 700; }
        .pnm-item-actions { display: flex; flex-wrap: wrap; gap: 4px 14px; margin-top: 5px; }
        .pnm-item-action {
            display: inline-flex; align-items: center; gap: 5px; padding: 2px 0; border: 0; cursor: pointer;
            font: inherit; font-size: 12px; font-weight: 550; color: var(--pnm-subtle); text-decoration: none;
            background: none; transition: color .12s;
        }
        .pnm-item-action svg { width: 13px; height: 13px; flex-shrink: 0; }
        .pnm-item-action:hover { color: var(--pnm-accent-ink); }
        .pnm-item-action.is-view { color: var(--pnm-accent-ink); font-weight: 650; }
        .pnm-item-action.is-static { cursor: default; }
        .pnm-item-action.is-static:hover { color: var(--pnm-subtle); }

        .pnm-type-feature,     .pnm-group-feature     { --acc: #34d399; --acc-ink: #059669; }
        .pnm-type-improvement, .pnm-group-improvement { --acc: #60a5fa; --acc-ink: #2563eb; }
        .pnm-type-fix,         .pnm-group-fix         { --acc: #fbbf24; --acc-ink: #b45309; }
        .dark .pnm-type-feature,     .dark .pnm-group-feature     { --acc-ink: #6ee7b7; }
        .dark .pnm-type-improvement, .dark .pnm-group-improvement { --acc-ink: #93c5fd; }
        .dark .pnm-type-fix,         .dark .pnm-group-fix         { --acc-ink: #fcd34d; }

        /* Footer */
        .pnm-foot {
            flex-shrink: 0; display: flex; align-items: center; justify-content: space-between; gap: 12px;
            padding: 14px 24px; border-top: 1px solid var(--pnm-line);
        }
        .pnm-steps { display: flex; align-items: center; gap: 6px; min-width: 0; }
        .pnm-dot {
            width: 8px; height: 8px; padding: 0; border: 0; border-radius: 99px; cursor: pointer;
            background: var(--pnm-border); transition: width .2s ease, background .2s;
        }
        .pnm-dot:hover { background: var(--pnm-subtle); }
        .pnm-dot.is-on { width: 22px; background: var(--pnm-accent); }
        .pnm-all {
            display: inline-flex; align-items: center; gap: 6px; margin-left: 6px; padding: 5px 10px 5px 8px;
            border: 0; border-radius: 99px; cursor: pointer;
            font: inherit; font-size: 12.5px; font-weight: 600; color: var(--pnm-muted); background: transparent;
            box-shadow: inset 0 0 0 1px var(--pnm-border); transition: color .12s, background .12s;
        }
        .pnm-all svg { width: 14px; height: 14px; }
        .pnm-all:hover { color: var(--pnm-ink); }
        .pnm-all.is-on { color: var(--pnm-accent-ink); background: var(--pnm-accent-bg); box-shadow: inset 0 0 0 1px var(--pnm-accent-border); }
        .pnm-all-count { font-size: 11px; font-weight: 700; font-variant-numeric: tabular-nums; opacity: .75; }

        .pnm-actions { display: flex; align-items: center; gap: 8px; flex-shrink: 0; }
        .pnm-btn {
            display: inline-flex; align-items: center; gap: 6px; padding: 8px 16px; border: 0; border-radius: 10px; cursor: pointer;
            font: inherit; font-size: 13.5px; font-weight: 650; transition: background .12s, filter .12s;
        }
        .pnm-btn svg { width: 15px; height: 15px; }
        .pnm-btn-ghost { color: var(--pnm-muted); background: transparent; }
        .pnm-btn-ghost:hover { color: var(--pnm-ink); background: var(--pnm-line); }
        .pnm-btn-primary { color: #fff; background: #0e7490; }
        .pnm-btn-primary:hover { background: #155e75; }
        .dark .pnm-btn-primary { color: #083344; background: #22d3ee; }
        .dark .pnm-btn-primary:hover { background: #67e8f9; }

        .pnm button:focus-visible, .pnm a:focus-visible { outline: 2px solid var(--pnm-accent); outline-offset: 2px; }
        .pnm-dialog:focus { outline: none; }

        @media (max-width: 640px) {
            .pnm { padding: 0; align-items: flex-end; }
            .pnm-dialog { width: 100%; height: 92dvh; border-radius: 18px 18px 0 0; border-bottom: 0; }
            .pnm-head { padding: 12px 12px 0 16px; }
            .pnm-title { font-size: 17px; }
            .pnm-body { padding: 14px 16px 4px; }
            .pnm-kicker { font-size: 10px; letter-spacing: .1em; }
            .pnm-head-tools { gap: 4px; }
            .pnm-release-tab { padding: 3px 8px; font-size: 11px; }
            .pnm-media { flex: none; padding: 48px 12px 14px; }
            .pnm-shot { max-height: 34vh; }
            .pnm-count { margin-left: 0; width: 100%; }
            .pnm-loc-link { margin-left: 0; }
            .pnm-item { flex-direction: column; gap: 6px; }
            .pnm-item-area { width: auto; }
            .pnm-foot { padding: 12px 16px; }
            .pnm-all { margin-left: 2px; }
            .pnm-all-label { display: none; }
        }
        @media (prefers-reduced-motion: reduce) {
            .pnm *, .pnm *::before { transition: none !important; }
        }
    </style>
@endif
</div>
