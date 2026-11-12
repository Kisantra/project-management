{{--
    Tax report page body (docs/mockups/tax-report-detail-redesign.html). The
    header comes from ViewTaxReport::getHeader(). Below it: period navigation,
    one strip of tax types that doubles as the tab list, then the workspace of
    the selected type. The open type is ?jenis= (kisUrlTab in kis-ui.js).
--}}
@php
    use App\Services\TaxReport\ReportOverview;

    $record = $this->record;
    $cards = $overview->cards();
@endphp

<x-filament-panels::page>
    <div class="kis-tr"
         x-data="kisUrlTab({ param: 'jenis', value: @js($this->initialType), fallback: 'ppn', allowed: @js($overview->openTabs()), resets: ['tab'] })">

        {{-- Period: year + twelve months of this client --}}
        <div class="kis-trp">
            <nav class="kis-rail__wrap kis-trp__years" aria-label="Tahun">
                @foreach ($overview->years() as $year)
                    @if ($year['current'])
                        <span class="kis-rail__chip is-on" aria-current="true">{{ $year['year'] }} <em>{{ $year['count'] }}</em></span>
                    @else
                        <a href="{{ $year['url'] }}" class="kis-rail__chip" x-on:click="kisFollow($event, $el)">{{ $year['year'] }} <em>{{ $year['count'] }}</em></a>
                    @endif
                @endforeach
            </nav>

            <nav class="kis-trp__months" aria-label="Bulan {{ $overview->year() }}">
                @foreach ($overview->months() as $month)
                    @php
                        $mark = match ($month['state']) {
                            'done' => '✓ Lapor',
                            'pending' => 'Belum',
                            'nocontract' => 'Ada data',
                            default => '–',
                        };
                    @endphp
                    @if ($month['url'] && ! $month['current'])
                        <a href="{{ $month['url'] }}" x-on:click="kisFollow($event, $el)"
                           class="kis-trp__m is-{{ $month['state'] }}" data-kis-tip='@json($month['tip'])'
                           aria-label="{{ $month['name'] }}: {{ $mark }}">
                            @if ($month['activity'])<i class="kis-trp__act" aria-hidden="true"></i>@endif
                            <span>{{ $month['short'] }}</span>
                            <small>{{ $mark }}</small>
                        </a>
                    @else
                        <span @class(['kis-trp__m', 'is-' . $month['state'], 'is-current' => $month['current']])
                              @if ($month['tip']) data-kis-tip='@json($month['tip'])' @endif
                              @if ($month['current']) aria-current="page" @endif>
                            @if ($month['activity'])<i class="kis-trp__act" aria-hidden="true"></i>@endif
                            <span>{{ $month['short'] }}</span>
                            <small>{{ $mark }}</small>
                        </span>
                    @endif
                @endforeach
            </nav>

            <div class="kis-trp__legend" aria-hidden="true">
                <span class="is-done">✓ Semua jenis lapor</span>
                <span class="is-pending">Ada yang belum</span>
                <span class="is-act">Ada transaksi</span>
            </div>
        </div>

        {{-- Tax types: one strip, each cell opens its workspace --}}
        <div class="kis-trs" role="tablist" aria-label="Jenis pajak">
            @foreach ($cards as $card)
                @php
                    $inner = view('filament.pages.tax-report.partials.type-cell', ['card' => $card])->render();
                @endphp
                @if ($card['locked'])
                    <div class="kis-trs__cell is-locked" title="Kontrak {{ $card['label'] }} belum aktif untuk klien ini">{!! $inner !!}</div>
                @else
                    <button type="button" role="tab" id="kis-tab-{{ $card['key'] }}" aria-controls="kis-ws-{{ $card['key'] }}"
                            class="kis-trs__cell" x-bind:class="tab === @js($card['key']) && 'is-on'"
                            x-bind:aria-selected="tab === @js($card['key'])" x-on:click="show(@js($card['key']))">{!! $inner !!}</button>
                @endif
            @endforeach
        </div>

        {{-- Workspaces --}}
        <section id="kis-ws-ppn" role="tabpanel" aria-labelledby="kis-tab-ppn" class="kis-trw"
                 x-show="tab === 'ppn'" @style(['display: none' => $this->initialType !== 'ppn'])>
            @livewire('tax-report.ppn.tax-report-invoices', ['taxReportId' => $record->id], key('ppn-' . $record->id))
        </section>

        <section id="kis-ws-pph" role="tabpanel" aria-labelledby="kis-tab-pph" class="kis-trw"
                 x-show="tab === 'pph'" @style(['display: none' => $this->initialType !== 'pph'])>
            @livewire('tax-report.pph.tax-report-pph', ['taxReportId' => $record->id], key('pph-' . $record->id))
        </section>

        @unless ($cards['bupot']['locked'])
            @php $unifikasiSpt = (bool) $overview->summary('bupot')?->bukti_lapor; @endphp
            <section id="kis-ws-unifikasi" role="tabpanel" aria-labelledby="kis-tab-unifikasi" class="kis-trw"
                     x-show="tab === 'unifikasi'" @style(['display: none' => $this->initialType !== 'unifikasi'])>
                <div class="kis-ws" x-data="kisUrlTab({ param: 'tab', value: @js($this->initialUnifikasiTab), fallback: 'bupot', allowed: @js(\App\Filament\Resources\TaxReportResource\Pages\ViewTaxReport::UNIFIKASI_TABS) })">
                    <div class="kis-subtabs">
                        <nav class="kis-subtabs__nav" role="tablist" aria-label="Bagian PPh Unifikasi">
                            <button type="button" role="tab" class="kis-subtab" x-bind:class="tab === 'bupot' && 'is-on'" x-bind:aria-selected="tab === 'bupot'" x-on:click="show('bupot')">
                                Bukti potong <i>{{ $cards['bupot']['value'] }}</i>
                            </button>
                            <button type="button" role="tab" class="kis-subtab" x-bind:class="tab === 'spt' && 'is-on'" x-bind:aria-selected="tab === 'spt'" x-on:click="show('spt')">
                                SPT
                                @if ($unifikasiSpt)
                                    <em class="is-done">Terunggah</em>
                                @elseif ($cards['bupot']['status'] === ReportOverview::NO_ACTIVITY)
                                    <i>Nihil</i>
                                @else
                                    <em>Belum</em>
                                @endif
                            </button>
                        </nav>
                        <span class="kis-subtabs__ctx">PPh Unifikasi · {{ $overview->periodLabel() }}</span>
                    </div>

                    {{-- Only due when there was withholding; without an answer the period keeps counting as outstanding. --}}
                    @livewire('tax-report.activity-confirmation', ['taxReportId' => $record->id, 'type' => 'bupot'], key('act-bupot-' . $record->id))

                    <div x-show="tab === 'bupot'" @style(['display: none' => $this->initialUnifikasiTab !== 'bupot'])>
                        {{-- The same table as on the edit page (all actions, filters and the bukti setor upload). --}}
                        @livewire(\App\Filament\Resources\TaxReportResource\RelationManagers\BupotsRelationManager::class, [
                            'ownerRecord' => $record,
                            'pageClass' => \App\Filament\Resources\TaxReportResource\Pages\ViewTaxReport::class,
                        ], key('bupots-' . $record->id))
                    </div>
                    <div x-show="tab === 'spt'" @style(['display: none' => $this->initialUnifikasiTab !== 'spt'])>
                        @livewire('tax-report.spt-upload', ['taxReportId' => $record->id, 'type' => 'bupot'], key('spt-bupot-' . $record->id))
                    </div>
                </div>
            </section>
        @endunless

        @unless ($cards['pph_badan']['locked'])
            <section id="kis-ws-badan" role="tabpanel" aria-labelledby="kis-tab-badan" class="kis-trw"
                     x-show="tab === 'badan'" @style(['display: none' => $this->initialType !== 'badan'])>
                {{-- PPh Badan is reported only when there is activity, like PPh Unifikasi. --}}
                <div class="kis-ws">
                    <div class="kis-subtabs">
                        <span class="kis-subtab is-on" aria-current="true">SPT</span>
                        <span class="kis-subtabs__ctx">PPh Badan · {{ $overview->periodLabel() }}</span>
                    </div>
                    @livewire('tax-report.activity-confirmation', ['taxReportId' => $record->id, 'type' => 'pph_badan'], key('act-badan-' . $record->id))
                    @livewire('tax-report.spt-upload', ['taxReportId' => $record->id, 'type' => 'pph_badan'], key('spt-badan-' . $record->id))
                </div>
            </section>
        @endunless
    </div>
</x-filament-panels::page>
