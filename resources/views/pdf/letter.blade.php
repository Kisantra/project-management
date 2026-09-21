<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>{{ $letter->number ?? 'Draft' }} - {{ $letter->subject }}</title>
    @include('letters.document-style')
    <style>
        {{-- Arial from storage/fonts so the PDF matches the office template on any server. --}}
        @font-face { font-family: 'Arial'; font-style: normal; font-weight: 400; src: url('{{ $fonts['regular'] }}') format('truetype'); }
        @font-face { font-family: 'Arial'; font-style: normal; font-weight: 700; src: url('{{ $fonts['bold'] }}') format('truetype'); }
        @font-face { font-family: 'Arial'; font-style: italic; font-weight: 400; src: url('{{ $fonts['italic'] }}') format('truetype'); }
        @font-face { font-family: 'Arial'; font-style: italic; font-weight: 700; src: url('{{ $fonts['bolditalic'] }}') format('truetype'); }

        /* Content is inset by padding on the body; the letterhead is drawn full-bleed on every page. */
        @page { margin: 44mm 16mm 40mm 18mm; }
        body { margin: 0; }
        .ltr-letterhead { position: fixed; top: -44mm; left: -18mm; width: 210mm; height: 297mm; z-index: -1; }
    </style>
</head>
<body>
    @if ($letterhead)
        <img class="ltr-letterhead" src="{{ $letterhead }}" alt="">
    @endif
    {!! $document !!}
</body>
</html>
