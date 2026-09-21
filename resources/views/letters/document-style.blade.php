{{-- Shared paper styles for preview and PDF, mirroring the office Word template:
     Arial 11pt, letterhead image behind the page, content inset to clear the header and footer art. --}}
@php
    $letterhead = config('letter.letterhead_image');
    $letterheadUrl = $letterhead ? '/' . ltrim($letterhead, '/') : null; // host-relative so it works whatever host the panel is opened on
@endphp
<style>
    .ltr-doc { font-family: Arial, 'Liberation Sans', Helvetica, sans-serif; font-size: 11pt; line-height: 1.4; color: #111111; }

    /* One A4 page. On screen the letterhead is a background; in the PDF it is a fixed image per page. */
    .ltr-page {
        position: relative;
        width: 210mm; min-height: 297mm;
        box-sizing: border-box;
        padding: 44mm 16mm 40mm 18mm; /* clears the header art (~38mm) and the footer strip (~35mm) of the letterhead */
        background-color: #fff;
        {!! $letterheadUrl ? "background-image: url('{$letterheadUrl}'); background-repeat: no-repeat; background-position: center top; background-size: 210mm 297mm;" : '' !!}
    }
    .ltr-doc--pdf .ltr-page { width: auto; min-height: 0; padding: 0; background: none; }
    .ltr-page + .ltr-page { margin-top: 16px; }
    .ltr-doc--pdf .ltr-page + .ltr-page { margin-top: 0; page-break-before: always; }

    .ltr-dateline { margin: 0 0 10px; }
    .ltr-meta { border-collapse: collapse; margin: 0 0 12px; }
    .ltr-meta td { padding: 0 0 2px; vertical-align: top; }
    .ltr-meta-k { width: 22mm; }
    .ltr-meta-c { width: 5mm; }

    .ltr-addr { margin: 0 0 12px; }
    .ltr-addr-name { font-weight: 700; }


    .ltr-body p { margin: 0 0 9px; text-align: justify; }
    .ltr-body ol { list-style: decimal; margin: 0 0 9px 22px; padding: 0; }
    .ltr-body ul { list-style: disc; margin: 0 0 9px 22px; padding: 0; }
    .ltr-body li { margin: 0 0 3px; text-align: justify; }
    .ltr-blank { color: #9ca3af; font-style: italic; }
    .ltr-missing { color: #b91c1c; background: #fee2e2; padding: 0 3px; border-radius: 2px; font-size: 9pt; }

    .ltr-sign { border-collapse: collapse; width: 100%; margin-top: 14px; page-break-inside: avoid; }
    .ltr-sign-cell { vertical-align: top; padding: 0 12mm 0 0; }
    .ltr-sign-salute { margin-bottom: 0; }
    .ltr-sign-for { font-weight: 700; margin-bottom: 4px; }
    .ltr-sign-space { height: 28mm; position: relative; }
    .ltr-sign-img { height: 26mm; width: auto; max-width: 60mm; display: block; }
    .ltr-esign { font-size: 8pt; color: #047857; line-height: 1.35; padding-top: 10mm; }
    .ltr-sign-name { font-weight: 700; }
    .ltr-sign-role { font-size: 10.5pt; }

    .ltr-attach-head { margin-bottom: 12px; }
    .ltr-attach-title { font-weight: 700; text-decoration: underline; letter-spacing: .03em; margin-bottom: 8px; }
    .ltr-attach-table { width: 100%; border-collapse: collapse; font-size: 10pt; line-height: 1.4; }
    .ltr-attach-table th, .ltr-attach-table td { border: 1px solid #333; padding: 6px 8px; vertical-align: top; text-align: left; }
    .ltr-attach-table th { font-weight: 700; text-align: center; background: #f2f2f2; }
    .ltr-attach-table td { text-align: justify; }
    .ltr-col-no { width: 10mm; text-align: center !important; white-space: nowrap; }
    .ltr-col-doc { width: 50mm; text-align: left !important; }
    .ltr-attach-table tr { page-break-inside: avoid; }
</style>
