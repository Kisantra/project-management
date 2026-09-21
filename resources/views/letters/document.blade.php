{{-- The letter itself, laid out like the office's Word template: full-page letterhead
     image, Arial 11pt, date/Nomor/Perihal block, addressee, justified body, "Hormat kami"
     closing with handwritten signature, and an optional LAMPIRAN table page.
     Shared by the on-screen preview and the PDF, so it uses plain CSS classes only. --}}
@php
    $service = app(\App\Services\LetterService::class);
    $template = $letter->template;
    $isBeritaAcara = $template->category === 'berita_acara';
    $bodyHasAddressee = \Illuminate\Support\Str::startsWith(ltrim($template->body), 'Kepada Yth.');
    $dateLine = ($company['city'] ? $company['city'] . ', ' : '') . $service->formatDate($letter->letter_date);
    $attachmentKeys = $template->checklist_as_attachment ? $template->checklistKeys() : [];
    $attachments = collect($attachmentKeys)
        ->map(fn ($key) => ['field' => $template->fieldDefinitions()[$key] ?? ['label' => $key], 'items' => $letter->checklist($key)])
        ->filter(fn ($a) => $a['items']->isNotEmpty())
        ->values();
    $legalName = $company['legal_name'] ?: $company['name'];
    // Signature image shows as soon as a person is assigned to the slot (requested behaviour),
    // not only after they click "Tandatangani".
    $signatureSrc = function ($slot) use ($forPdf, $service) {
        $src = $service->signatureSources($slot->user);

        return $src ? ($forPdf ? $src['path'] : $src['url']) : null;
    };
@endphp
<div class="ltr-doc {{ $forPdf ? 'ltr-doc--pdf' : '' }}">
    <div class="ltr-page">
        {{-- Same left-aligned head on every document type: date line, then Nomor / Perihal / Lampiran --}}
        <p class="ltr-dateline">{{ $dateLine }}</p>

        <table class="ltr-meta">
            <tr><td class="ltr-meta-k">Nomor</td><td class="ltr-meta-c">:</td><td>{{ $letter->number ?? '…' }}</td></tr>
            <tr><td class="ltr-meta-k">Perihal</td><td class="ltr-meta-c">:</td><td><strong>{{ $letter->subject }}</strong></td></tr>
            @if ($attachments->isNotEmpty())
                <tr><td class="ltr-meta-k">Lampiran</td><td class="ltr-meta-c">:</td><td>Satu set</td></tr>
            @endif
        </table>

        {{-- Berita acara are not addressed to anyone; KPP-bound letters carry their own "Kepada Yth." in the body --}}
        @if (! $isBeritaAcara && ! $bodyHasAddressee)
            <div class="ltr-addr">
                <div>Kepada Yth.,</div>
                <div class="ltr-addr-name">Bapak/Ibu Pimpinan {{ $letter->client?->name }}</div>
                <div>di tempat</div>
            </div>
        @endif

        <div class="ltr-body">{!! $body !!}</div>

        {{-- Closing + signature block --}}
        @if ($slots->isNotEmpty())
            <table class="ltr-sign">
                <tr>
                    @foreach ($slots as $slot)
                        @php $sig = $slot->user ? $signatureSrc($slot) : null; @endphp
                        <td class="ltr-sign-cell" style="width: {{ (int) (100 / max(1, $slots->count() + ($isBeritaAcara && filled($letter->values['pihak_klien'] ?? null) ? 1 : 0))) }}%">
                            @if ($loop->first)
                                <div class="ltr-sign-salute">Hormat kami,</div>
                                <div class="ltr-sign-for">{{ $legalName }}</div>
                            @else
                                <div class="ltr-sign-salute">&nbsp;</div>
                                <div class="ltr-sign-for">&nbsp;</div>
                            @endif
                            <div class="ltr-sign-space">
                                @if ($sig)
                                    <img class="ltr-sign-img" src="{{ $sig }}" alt="Tanda tangan {{ $slot->user?->name }}">
                                @elseif (($slot->status ?? 'pending') === 'signed')
                                    <div class="ltr-esign">Ditandatangani secara elektronik<br>{{ $slot->signed_at?->locale('id')->translatedFormat('d M Y H:i') }} · {{ $slot->verification_code }}</div>
                                @endif
                            </div>
                            <div class="ltr-sign-name">{{ $slot->user?->name ?? '…………………………' }}</div>
                            <div class="ltr-sign-role">{{ $slot->user?->signatureTitle() ?: $slot->label }}</div>
                        </td>
                    @endforeach
                    @if ($isBeritaAcara && filled($letter->values['pihak_klien'] ?? null))
                        <td class="ltr-sign-cell">
                            <div class="ltr-sign-salute">Menyetujui,</div>
                            <div class="ltr-sign-for">{{ $letter->client?->name }}</div>
                            <div class="ltr-sign-space"></div>
                            <div class="ltr-sign-name">{{ $letter->values['pihak_klien'] }}</div>
                            <div class="ltr-sign-role">Wakil klien</div>
                        </td>
                    @endif
                </tr>
            </table>
        @endif
    </div>

    {{-- LAMPIRAN pages --}}
    @foreach ($attachments as $i => $attachment)
        <div class="ltr-page ltr-page--attachment">
            <div class="ltr-attach-head">
                <div class="ltr-attach-title">LAMPIRAN {{ ['I', 'II', 'III', 'IV'][$i] ?? $i + 1 }}</div>
                <table class="ltr-meta">
                    <tr><td class="ltr-meta-k">Nomor</td><td class="ltr-meta-c">:</td><td>{{ $letter->number ?? '…' }}</td></tr>
                    <tr><td class="ltr-meta-k">Tanggal</td><td class="ltr-meta-c">:</td><td>{{ $service->formatDate($letter->letter_date) }}</td></tr>
                    <tr><td class="ltr-meta-k">Lampiran</td><td class="ltr-meta-c">:</td><td>Daftar {{ $attachment['field']['label'] }}</td></tr>
                </table>
            </div>
            <table class="ltr-attach-table">
                <colgroup>
                    <col style="width: 10mm">
                    <col style="width: 50mm">
                    <col>
                </colgroup>
                <thead>
                    <tr>
                        <th class="ltr-col-no" style="width: 10mm">No</th>
                        <th class="ltr-col-doc" style="width: 50mm">Dokumen</th>
                        <th>Rincian yang Diminta</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($attachment['items'] as $n => $doc)
                        <tr>
                            <td class="ltr-col-no">{{ $n + 1 }}</td>
                            <td class="ltr-col-doc">{{ $doc->name }}</td>
                            <td>{{ $doc->note ?: ($doc->type?->description ?: '—') }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endforeach
</div>
