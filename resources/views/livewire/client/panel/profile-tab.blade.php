{{--
    Profil (client portal), docs/mockups/client-portal-redesign.html: company
    facts, people in charge, contacts, contracted services and the account
    credentials (passwords hidden until asked). The credential editor is a
    slide-over. Styles: client-portal/profile.css (+ shared kp-a-* in overview.css).
--}}
@php
    use App\Support\ClientPortal\ClientContext;
    use App\Support\Ui\Tone;
    use Illuminate\Support\Facades\Storage;
@endphp

<div class="kp-a kp-pf">
    @if ($clients->isEmpty() || ! $selectedClient)
        <div class="kp-a-card kp-a-empty kp-a-empty--page">
            <span class="kp-a-empty__icon"><x-heroicon-o-building-office-2 class="h-5 w-5" /></span>
            <b>Akun Belum Terhubung</b>
            <span>Akun Anda belum terhubung dengan data klien. Hubungi tim Kisantra.</span>
        </div>
    @else
        @php
            $client = $selectedClient;
            $active = collect($contractStatus)->where('active', true)->count();
            $cred = $client->clientCredential;
            $credRows = $cred ? [
                ['service' => 'Core Tax', 'fields' => [
                    ['key' => 'core-user', 'label' => 'User ID', 'value' => $cred->core_tax_user_id, 'secret' => false],
                    ['key' => 'core-pass', 'label' => 'Password', 'value' => $cred->core_tax_password, 'secret' => true],
                ]],
                ['service' => 'DJP Online', 'fields' => [
                    ['key' => 'djp-user', 'label' => 'Username', 'value' => $cred->djp_account, 'secret' => false],
                    ['key' => 'djp-pass', 'label' => 'Password', 'value' => $cred->djp_password, 'secret' => true],
                ]],
                ['service' => 'Email Terdaftar', 'fields' => [
                    ['key' => 'mail-user', 'label' => 'Email', 'value' => $cred->email, 'secret' => false],
                    ['key' => 'mail-pass', 'label' => 'Password', 'value' => $cred->email_password, 'secret' => true],
                ]],
            ] : [];
            $info = [
                ['NPWP', $client->NPWP, true],
                ['EFIN', $client->EFIN, true],
                ['Alamat', $client->adress, false],
                ['Email', $client->email, false],
            ];
            $ar = $client->accountRepresentative;
        @endphp

        {{-- Header --}}
        <header class="kp-a-head">
            <span class="kp-a-head__tile">
                @if ($client->logo)
                    <img src="{{ Storage::url($client->logo) }}" alt="" onerror="this.remove()">
                @endif
                {{ ClientContext::initials($client->name) }}
            </span>
            <div class="kp-a-head__text">
                <h1>
                    <span>{{ $client->name }}</span>
                    <x-kis.badge :tone="Tone::forClientStatus($client->status)" dot>{{ $client->status === 'Active' ? 'Aktif' : 'Tidak aktif' }}</x-kis.badge>
                </h1>
                <p>
                    @if ($client->client_type)<span>{{ $client->formatted_client_type }}</span>@endif
                    @if ($client->pkp_status)<span>{{ $client->pkp_status }}</span>@endif
                    @if ($client->email)<span>{{ $client->email }}</span>@endif
                </p>
            </div>
            <div class="kp-a-head__acts">
                <button type="button" wire:click="refresh" wire:loading.attr="disabled" class="kis-btn kis-btn--outline">
                    <x-heroicon-m-arrow-path class="h-4 w-4 kp-a-muted" wire:loading.class="animate-spin" wire:target="refresh" />
                    Segarkan
                </button>
            </div>
        </header>

        <div class="kp-pf-grid">
            {{-- Company --}}
            <section class="kp-a-card">
                <header class="kp-a-card__head kp-a-card__head--line"><h2 class="kp-a-card__title">Informasi Perusahaan</h2></header>
                <dl class="kp-pf-dl">
                    @foreach ($info as [$label, $value, $tabular])
                        <dt>{{ $label }}</dt>
                        <dd @class(['kp-a-num' => $tabular, 'kp-a-muted' => blank($value)])>{{ filled($value) ? $value : 'Belum diisi' }}</dd>
                    @endforeach
                </dl>
            </section>

            <div class="kp-pf-side">
                {{-- People in charge --}}
                <section class="kp-a-card">
                    <header class="kp-a-card__head kp-a-card__head--line"><h2 class="kp-a-card__title">Penanggung Jawab</h2></header>
                    <div class="kp-pf-person">
                        <span class="kp-a-label">PIC</span>
                        @if ($client->pic)
                            <span class="kp-pf-avatar">{{ ClientContext::initials($client->pic->name) }}</span>
                            <div class="min-w-0">
                                <b>{{ $client->pic->name }}</b>
                                @if ($client->pic->nik)<small class="kp-a-num">NIK {{ $client->pic->nik }}</small>@endif
                            </div>
                        @else
                            <span class="kp-pf-avatar is-empty"><x-heroicon-o-user class="h-4 w-4" /></span>
                            <b class="kp-a-muted">Belum ditentukan</b>
                        @endif
                    </div>
                    <div class="kp-pf-person">
                        <span class="kp-a-label">AR</span>
                        @if ($ar)
                            <span class="kp-pf-avatar">{{ ClientContext::initials($ar->name) }}</span>
                            <div class="min-w-0">
                                <b>{{ $ar->name }}</b>
                                @if ($ar->kpp || $ar->phone_number)
                                    <small>
                                        @if ($ar->kpp)<span>{{ $ar->kpp }}</span>@endif
                                        @if ($ar->phone_number)<span class="kp-a-num">{{ $ar->phone_number }}</span>@endif
                                    </small>
                                @endif
                            </div>
                        @else
                            <span class="kp-pf-avatar is-empty"><x-heroicon-o-user class="h-4 w-4" /></span>
                            <b class="kp-a-muted">Belum ditentukan</b>
                        @endif
                    </div>
                </section>

                {{-- Contacts --}}
                @if ($client->contacts && $client->contacts->isNotEmpty())
                    <section class="kp-a-card kp-pf-contacts">
                        <header class="kp-a-card__head kp-a-card__head--line">
                            <h2 class="kp-a-card__title">Kontak Tambahan</h2>
                            <span class="kp-a-card__aside">{{ $client->contacts->count() }} kontak</span>
                        </header>
                        @foreach ($client->contacts as $contact)
                            <div class="kp-pf-person">
                                <span class="kp-pf-avatar">{{ mb_strtoupper(mb_substr($contact->name, 0, 1)) }}</span>
                                <div class="min-w-0">
                                    <b>{{ $contact->name }}</b>
                                    @if ($contact->position)<small>{{ $contact->position }}</small>@endif
                                    @if ($contact->phone || $contact->email)
                                        <small>
                                            @if ($contact->phone)<span class="kp-a-num">{{ $contact->phone }}</span>@endif
                                            @if ($contact->email)<span class="truncate">{{ $contact->email }}</span>@endif
                                        </small>
                                    @endif
                                </div>
                            </div>
                        @endforeach
                    </section>
                @endif
            </div>
        </div>

        {{-- Contracted services --}}
        <section class="kp-a-card">
            <header class="kp-a-card__head kp-a-card__head--line">
                <h2 class="kp-a-card__title">Status Kontrak Layanan</h2>
                <span class="kp-a-card__aside">{{ $active }} dari {{ count($contractStatus) }} aktif</span>
            </header>
            <div class="kp-pf-svc">
                @foreach ($contractStatus as $contract)
                    <div @class(['kp-pf-svc__cell', 'is-off' => ! $contract['active']])>
                        <b>{{ $contract['name'] }}</b>
                        <small>{{ $contract['description'] }}</small>
                        <x-kis.badge :tone="$contract['active'] ? Tone::BLUE : Tone::NEUTRAL" :dot="$contract['active']">{{ $contract['active'] ? 'Aktif' : 'Tidak Aktif' }}</x-kis.badge>
                    </div>
                @endforeach
            </div>
        </section>

        {{-- Credentials --}}
        <section class="kp-a-card kp-pf-cred" x-data="{ shown: {}, copied: null, copy(key, text) { window.kisCopy(text).then(() => { this.copied = key; setTimeout(() => this.copied = null, 1500) }) } }">
            <header class="kp-a-card__head kp-a-card__head--line">
                <div class="min-w-0">
                    <h2 class="kp-a-card__title">Kredensial Akun</h2>
                    <p class="kp-a-card__sub kp-pf-cred__note">
                        <x-heroicon-o-lock-closed class="h-3.5 w-3.5 flex-none" />
                        Informasi sensitif. Jangan bagikan kepada pihak yang tidak berwenang.
                    </p>
                </div>
                <div class="ml-auto flex-none">
                    @if ($cred)
                        <button type="button" wire:click="openCredentialModal" class="kis-btn kis-btn--outline kis-btn--sm">
                            <x-heroicon-m-pencil-square class="h-4 w-4 kp-a-muted" /> Perbarui
                        </button>
                    @else
                        <button type="button" wire:click="openCredentialModal" class="kis-btn kis-btn--primary kis-btn--sm">
                            <x-heroicon-m-plus class="h-4 w-4" /> Tambah Kredensial
                        </button>
                    @endif
                </div>
            </header>

            @if ($cred)
                <div class="kp-pf-cred__table" role="table" aria-label="Kredensial akun">
                    <div class="kp-pf-cred__row kp-pf-cred__row--head" role="row">
                        <span role="columnheader">Layanan</span>
                        <span role="columnheader">Akun / ID</span>
                        <span role="columnheader">Kata sandi</span>
                    </div>
                    @foreach ($credRows as $row)
                        <div class="kp-pf-cred__row" role="row">
                            <b role="rowheader">{{ $row['service'] }}</b>
                            @foreach ($row['fields'] as $f)
                                <div class="kp-pf-cv" role="cell">
                                    <span class="kp-pf-cv__label">{{ $f['label'] }}</span>
                                    @if (filled($f['value']))
                                        @if ($f['secret'])
                                            <span class="kp-pf-cv__value kp-a-num" x-text="shown[@js($f['key'])] ? @js($f['value']) : '••••••••••'">••••••••••</span>
                                            <button type="button" class="kp-pf-cv__btn" x-on:click="shown[@js($f['key'])] = ! shown[@js($f['key'])]"
                                                    x-bind:title="shown[@js($f['key'])] ? 'Sembunyikan' : 'Tampilkan'"
                                                    x-bind:aria-label="(shown[@js($f['key'])] ? 'Sembunyikan ' : 'Tampilkan ') + @js($f['label'] . ' ' . $row['service'])">
                                                <span x-show="! shown[@js($f['key'])]"><x-heroicon-o-eye class="h-4 w-4" /></span>
                                                <span x-show="shown[@js($f['key'])]" x-cloak><x-heroicon-o-eye-slash class="h-4 w-4" /></span>
                                            </button>
                                        @else
                                            <span class="kp-pf-cv__value kp-a-num">{{ $f['value'] }}</span>
                                        @endif
                                        <button type="button" class="kp-pf-cv__btn" x-on:click="copy(@js($f['key']), @js($f['value']))"
                                                title="Salin" aria-label="Salin {{ $f['label'] }} {{ $row['service'] }}">
                                            <span x-show="copied !== @js($f['key'])"><x-heroicon-o-clipboard-document class="h-4 w-4" /></span>
                                            <span x-show="copied === @js($f['key'])" x-cloak><x-heroicon-m-check class="h-4 w-4 kp-pf-cv__ok" /></span>
                                        </button>
                                    @else
                                        <span class="kp-pf-cv__value kp-a-muted">Belum diisi</span>
                                    @endif
                                </div>
                            @endforeach
                        </div>
                    @endforeach
                </div>
            @else
                <div class="kp-a-empty">
                    <span class="kp-a-empty__icon"><x-heroicon-o-key class="h-5 w-5" /></span>
                    <b>Belum Ada Kredensial</b>
                    <span>Tambahkan kredensial akun untuk Core Tax, DJP Online, dan Email terdaftar.</span>
                </div>
            @endif
        </section>
    @endif

    {{-- Credential editor (slide-over) --}}
    <div class="kp-pf-sheet" x-data="{ open: $wire.entangle('showCredentialModal') }" x-show="open" x-cloak
         x-on:keydown.escape.window="open && $wire.closeCredentialModal()">
        <div class="kp-pf-sheet__scrim" x-show="open" x-transition.opacity.duration.200ms x-on:click="$wire.closeCredentialModal()"></div>

        <div class="kp-pf-sheet__panel" role="dialog" aria-modal="true" aria-labelledby="kp-pf-sheet-title"
             x-show="open" x-trap.noscroll="open"
             x-transition:enter="transition ease-out duration-250" x-transition:enter-start="translate-x-full" x-transition:enter-end="translate-x-0"
             x-transition:leave="transition ease-in duration-200" x-transition:leave-start="translate-x-0" x-transition:leave-end="translate-x-full">
            <header class="kp-pf-sheet__head">
                <div class="min-w-0">
                    <h2 id="kp-pf-sheet-title">{{ $selectedClient?->clientCredential ? 'Perbarui Kredensial' : 'Tambah Kredensial' }}</h2>
                    @if ($selectedClient?->name)<p>{{ $selectedClient->name }}</p>@endif
                </div>
                <button type="button" wire:click="closeCredentialModal" class="kp-pf-cv__btn" aria-label="Tutup">
                    <x-heroicon-o-x-mark class="h-5 w-5" />
                </button>
            </header>

            <p class="kp-pf-sheet__note">
                <x-heroicon-o-lock-closed class="h-4 w-4 flex-none" />
                Data tersimpan aman. Jangan bagikan kepada pihak yang tidak berwenang.
            </p>

            <div class="kp-pf-sheet__body">
                @foreach ([
                    ['Core Tax', [['credCoreTaxUserId', 'User ID', 'text', 'Masukkan User ID'], ['credCoreTaxPassword', 'Password', 'password', 'Masukkan Password']]],
                    ['DJP Online', [['credDjpAccount', 'Username', 'text', 'Masukkan Username DJP'], ['credDjpPassword', 'Password', 'password', 'Masukkan Password DJP']]],
                    ['Email Terdaftar', [['credEmail', 'Email', 'email', 'nama@domain.com'], ['credEmailPassword', 'Password', 'password', 'Masukkan Password Email']]],
                ] as [$group, $fields])
                    <fieldset class="kp-pf-group">
                        <legend class="kp-a-label">{{ $group }}</legend>
                        @foreach ($fields as [$model, $label, $type, $placeholder])
                            <div class="kp-pf-field" @if ($type === 'password') x-data="{ show: false }" @endif>
                                <label for="kp-pf-{{ $model }}">{{ $label }}</label>
                                <div class="kp-pf-field__control">
                                    <input id="kp-pf-{{ $model }}" wire:model="{{ $model }}" placeholder="{{ $placeholder }}"
                                           @if ($type === 'password') x-bind:type="show ? 'text' : 'password'" type="password" autocomplete="new-password"
                                           @else type="{{ $type }}" autocomplete="off" @endif
                                           @error($model) aria-invalid="true" @enderror>
                                    @if ($type === 'password')
                                        <button type="button" class="kp-pf-cv__btn" x-on:click="show = ! show" x-bind:aria-label="show ? 'Sembunyikan password' : 'Tampilkan password'">
                                            <x-heroicon-o-eye class="h-4 w-4" x-show="! show" />
                                            <x-heroicon-o-eye-slash class="h-4 w-4" x-show="show" x-cloak />
                                        </button>
                                    @endif
                                </div>
                                @error($model)<p class="kp-pf-field__error">{{ $message }}</p>@enderror
                            </div>
                        @endforeach
                    </fieldset>
                @endforeach
            </div>

            <footer class="kp-pf-sheet__foot">
                <button type="button" wire:click="closeCredentialModal" class="kis-btn kis-btn--outline">Batal</button>
                <button type="button" wire:click="saveCredentials" wire:loading.attr="disabled" wire:target="saveCredentials" class="kis-btn kis-btn--primary">
                    <x-filament::loading-indicator class="h-4 w-4" wire:loading wire:target="saveCredentials" />
                    <span wire:loading.remove wire:target="saveCredentials">Simpan Kredensial</span>
                    <span wire:loading wire:target="saveCredentials">Menyimpan...</span>
                </button>
            </footer>
        </div>
    </div>
</div>
