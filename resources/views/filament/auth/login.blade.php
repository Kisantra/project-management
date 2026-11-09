{{--
    Halaman login (Opsi E: foto penuh, kartu solid).

    Foto: Monumen Nasional, Jakarta, dari Unsplash (photo-1662808782878-941ea16adbdc,
    lisensi Unsplash). Disimpan di public/images/login supaya halaman login tidak
    bergantung pada server luar: 2400px untuk desktop, 1200px untuk tablet/ponsel.

    Form dan tombol tetap komponen Filament (validasi, tampilkan kata sandi, pesan
    error); CSS di bawah hanya menyesuaikan ukuran dan warnanya.
--}}
<div class="kl-login">
    <div class="kl-photo" aria-hidden="true"></div>
    <div class="kl-scrim" aria-hidden="true"></div>

    <header class="kl-top">
        <div class="kl-brand">
            <img src="{{ asset('images/Logo/OnlyLogo.png') }}" alt="" width="26" height="30">
            <span>Kisantra</span>
        </div>
        <span class="kl-top-note">Konsultan Pajak &amp; Manajemen Proyek</span>
    </header>

    <div class="kl-copy">
        <p class="kl-tagline">Clear. Trusted.<br>Reliable.</p>
        <p class="kl-sub">Ruang kerja tim konsultan dan klien Kisantra.</p>
        <p class="kl-cap">Monumen Nasional &middot; Jakarta</p>
    </div>

    <div class="kl-stage">
        <main class="kl-card">
            <h1 class="kl-title">Masuk</h1>
            <p class="kl-lead">Gunakan email kantor, atau email yang terdaftar sebagai klien Kisantra.</p>

            {{ \Filament\Support\Facades\FilamentView::renderHook(\Filament\View\PanelsRenderHook::AUTH_LOGIN_FORM_BEFORE, scopes: $this->getRenderHookScopes()) }}

            <x-filament-panels::form id="form" wire:submit="authenticate">
                {{ $this->form }}

                <x-filament-panels::form.actions
                    :actions="$this->getCachedFormActions()"
                    :full-width="$this->hasFullWidthFormActions()"
                />
            </x-filament-panels::form>

            {{ \Filament\Support\Facades\FilamentView::renderHook(\Filament\View\PanelsRenderHook::AUTH_LOGIN_FORM_AFTER, scopes: $this->getRenderHookScopes()) }}

            <p class="kl-help">Lupa kata sandi atau belum punya akses? <strong>Hubungi admin Kisantra.</strong></p>

            <footer class="kl-foot">
                <span>Khusus tim dan klien Kisantra</span>
                <span>&copy; {{ now()->year }} Kisantra</span>
            </footer>
        </main>
    </div>

    <style>
        .kl-login {
            --kl-surface: #ffffff;
            --kl-ink: #141416;
            --kl-muted: #5b5b63;
            --kl-subtle: #6e6e77;
            --kl-line: #e4e4e7;
            --kl-btn: #0f6f86;
            --kl-btn-hover: #0b5b6e;
            --kl-on-btn: #ffffff;
            position: relative;
            min-height: 100vh;
            min-height: 100dvh;
            overflow: hidden;
            color: var(--kl-ink);
        }
        .dark .kl-login {
            --kl-surface: #141417;
            --kl-ink: #f2f2f4;
            --kl-muted: #a6a6ae;
            --kl-subtle: #8a8a93;
            --kl-line: #26262b;
            --kl-btn: #43b3cc;
            --kl-btn-hover: #62c5db;
            --kl-on-btn: #04232b;
        }

        /* Photo + scrim */
        /* Fixed on desktop: on a short laptop screen the card scrolls while
           the photo keeps covering the whole viewport. */
        .kl-photo {
            position: fixed; inset: 0;
            background: #1b2a2e url("{{ asset('images/login/monas-2400.webp') }}") center 45% / cover no-repeat;
            filter: saturate(.85);
        }
        .dark .kl-photo { filter: saturate(.7) brightness(.62); }
        .kl-scrim {
            position: fixed; inset: 0;
            background:
                linear-gradient(180deg, rgba(8, 18, 22, .35) 0%, rgba(8, 18, 22, 0) 18%),
                linear-gradient(90deg, rgba(8, 18, 22, .55) 0%, rgba(8, 18, 22, .15) 42%, rgba(8, 18, 22, .05) 60%, rgba(8, 18, 22, .35) 100%);
        }

        /* Top bar */
        .kl-top {
            position: absolute; z-index: 2; top: 36px; left: 44px; right: 44px;
            display: flex; align-items: center; justify-content: space-between; gap: 16px;
            color: #fff;
        }
        .kl-brand { display: flex; align-items: center; gap: 10px; font-size: 15px; font-weight: 700; letter-spacing: .14em; text-transform: uppercase; }
        .kl-brand img { width: auto; height: 30px; filter: drop-shadow(0 1px 2px rgba(0, 0, 0, .3)); }
        .kl-top-note { font-size: 13px; color: rgba(255, 255, 255, .88); }

        /* Bottom-left copy */
        .kl-copy { position: absolute; z-index: 2; left: 44px; bottom: 44px; max-width: 430px; color: #fff; }
        .kl-tagline { margin: 0; font-size: 40px; line-height: 1.1; font-weight: 800; letter-spacing: -.03em; text-shadow: 0 2px 18px rgba(0, 0, 0, .25); }
        .kl-sub { margin: 12px 0 0; font-size: 14.5px; line-height: 1.6; color: rgba(255, 255, 255, .9); }
        .kl-cap { margin: 18px 0 0; font-size: 11.5px; letter-spacing: .06em; text-transform: uppercase; color: rgba(255, 255, 255, .72); }

        /* Card */
        .kl-stage {
            position: relative; z-index: 3;
            min-height: 100vh; min-height: 100dvh;
            display: flex; align-items: center; justify-content: flex-end;
            padding: 104px 64px 104px;
        }
        .kl-card {
            width: 440px; max-width: 100%;
            padding: 40px 40px 30px;
            border-radius: 20px;
            background: var(--kl-surface);
            box-shadow: 0 40px 80px -30px rgba(0, 0, 0, .55), 0 0 0 1px rgba(0, 0, 0, .04);
        }
        .dark .kl-card { box-shadow: 0 40px 80px -30px rgba(0, 0, 0, .8), 0 0 0 1px var(--kl-line); }
        .kl-title { margin: 0; font-size: 26px; line-height: 1.2; font-weight: 750; letter-spacing: -.02em; color: var(--kl-ink); }
        .kl-lead { margin: 8px 0 26px; font-size: 14px; line-height: 1.55; color: var(--kl-muted); }
        .kl-help { margin: 18px 0 0; font-size: 12.5px; line-height: 1.55; color: var(--kl-subtle); }
        .kl-help strong { font-weight: 600; color: var(--kl-muted); }
        .kl-foot {
            display: flex; justify-content: space-between; gap: 12px;
            margin-top: 22px; padding-top: 16px; border-top: 1px solid var(--kl-line);
            font-size: 12px; color: var(--kl-subtle);
        }

        /* Filament form, tuned to the card */
        .kl-card .fi-fo-field-wrp-label span { font-size: 13px; font-weight: 600; }
        .kl-card .fi-input-wrp { border-radius: 10px; }
        .kl-card .fi-input { padding-top: .7rem; padding-bottom: .7rem; font-size: 14px; }
        .kl-card .fi-fo-checkbox-list-option-label, .kl-card .fi-fo-field-wrp label span { font-size: 13px; }
        .kl-card .fi-form-actions { margin-top: 4px; }
        .kl-card .fi-btn.fi-btn-color-primary {
            height: 46px; border-radius: 10px;
            font-size: 14.5px; font-weight: 700;
            background-color: var(--kl-btn) !important;
            color: var(--kl-on-btn) !important;
        }
        .kl-card .fi-btn.fi-btn-color-primary:hover { background-color: var(--kl-btn-hover) !important; }
        .kl-card .fi-btn.fi-btn-color-primary .fi-btn-icon { color: var(--kl-on-btn) !important; }

        /* Tablet: keep the full-bleed photo, centre the card, tagline under the logo. */
        @media (max-width: 1023px) {
            .kl-top { top: 24px; left: 28px; right: 28px; }
            .kl-copy { top: 92px; bottom: auto; left: 28px; right: 28px; }
            .kl-tagline { font-size: 30px; }
            .kl-sub, .kl-cap { display: none; }
            .kl-stage { align-items: flex-start; justify-content: center; padding: 220px 24px 56px; }
            .kl-card { width: 520px; }
        }

        /* Phone: the photo becomes a header band and the card a sheet over it. */
        @media (max-width: 639px) {
            .kl-photo, .kl-scrim { position: absolute; bottom: auto; height: 300px; }
            .kl-photo { background-image: url("{{ asset('images/login/monas-1200.webp') }}"); background-position: center 40%; }
            .kl-scrim { background: linear-gradient(180deg, rgba(8, 18, 22, .45) 0%, rgba(8, 18, 22, .05) 45%, rgba(8, 18, 22, .55) 100%); }
            .kl-top { top: 22px; left: 22px; right: 22px; }
            .kl-top-note { display: none; }
            .kl-brand { font-size: 13px; }
            .kl-brand img { height: 24px; }
            .kl-copy { top: 150px; left: 22px; right: 22px; }
            .kl-tagline { font-size: 26px; }
            .kl-stage { padding: 256px 0 0; }
            .kl-card { width: 100%; min-height: calc(100dvh - 256px); padding: 28px 22px; border-radius: 20px 20px 0 0; box-shadow: none; }
            .kl-title { font-size: 22px; }
        }
    </style>
</div>
