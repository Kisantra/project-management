{{--
    Credential viewer/editor. One table of logins (service, account, secret),
    grouped like the Clients list: client logins, applications, PIC.
    CSS: .kis-cred / .kis-cf in resources/css/filament/admin/theme.css.
--}}
@php
    use App\Livewire\Client\Management\CredentialManager;
    use Illuminate\Support\Carbon;

    $isEdit = $mode === 'edit';
    $today = now()->startOfDay();
@endphp

<div @class(['kis-cred', 'is-edit' => $isEdit, 'is-framed' => $closable])
     x-data="{
         dirty: false,
         dismiss() {
             if (this.$wire.mode === 'edit' && this.dirty && ! confirm('Perubahan belum disimpan. Tutup tanpa menyimpan?')) return
             this.$dispatch('kis-cred-close')
         },
     }"
     x-on:input="dirty = true"
     x-on:change="dirty = true"
     @if ($closable)
         x-on:keydown.escape.window="dismiss()"
         x-on:kis-cred-dismiss.window="dismiss()"
     @endif
>
    {{-- Header: who, and the mode --}}
    <header class="kis-cred__head">
        <span class="kis-cred__tile" aria-hidden="true">
            <span>{{ CredentialManager::initials($clientName) }}</span>
            @if ($logoUrl)
                <img src="{{ $logoUrl }}" alt="" onerror="this.remove()">
            @endif
        </span>
        <div class="kis-cred__id">
            <h2 class="kis-cred__name" id="kis-cred-title-{{ $clientId }}" title="{{ $clientName }}">{{ $clientName }}</h2>
            <p class="kis-cred__meta">
                <span>Kredensial login</span>
                @if ($clientNpwp)
                    <span aria-hidden="true">·</span>
                    <span>NPWP {{ $clientNpwp }}</span>
                @endif
            </p>
        </div>
        <div class="kis-cred__head-actions">
            @if ($isEdit)
                <span class="kis-badge kis-badge--outline kis-badge--dot">Mode ubah</span>
            @else
                <button type="button" wire:click="enableEdit" x-on:click="dirty = false" class="kis-btn kis-btn--sm kis-btn--outline">
                    <x-heroicon-m-pencil-square class="h-4 w-4 text-gray-500 dark:text-gray-400" />
                    Ubah
                </button>
            @endif
            @if ($closable)
                <button type="button" class="kis-cred__close" x-on:click="dismiss()" aria-label="Tutup" autofocus>
                    <x-heroicon-m-x-mark class="h-5 w-5" />
                </button>
            @endif
        </div>
    </header>

    <div class="kis-cred__body">
        <div class="kis-cred__table" role="table" aria-label="Kredensial {{ $clientName }}">
            <div class="kis-cred__thead" role="row">
                <span role="columnheader">Layanan</span>
                <span role="columnheader">Akun / ID</span>
                <span role="columnheader">Kata sandi</span>
                @if ($isEdit)
                    <span role="columnheader"><span class="sr-only">Aksi</span></span>
                @endif
            </div>

            {{-- Client logins --}}
            <div class="kis-cred__group" role="row"><span role="cell">Akun pajak &amp; email</span></div>
            @foreach (CredentialManager::CLIENT_LOGINS as $key => [$name, $icon, $accountField, $secretField, $accountLabel])
                <div class="kis-cred__row" role="row" wire:key="login-{{ $key }}">
                    <div class="kis-cred__svc" role="cell">
                        <span class="kis-cred__svc-tile"><x-filament::icon :icon="$icon" class="h-4 w-4" /></span>
                        <span class="kis-cred__svc-text">
                            <span class="kis-cred__svc-name">{{ $name }}</span>
                        </span>
                    </div>
                    <div role="cell">
                        <x-cred-field :label="$accountLabel" :context="$name" :mode="$mode"
                                      :type="$accountField === 'email' ? 'email' : 'text'"
                                      wire="clientCred.{{ $accountField }}" :value="$clientCred[$accountField]"
                                      :placeholder="$accountLabel" :error="$errors->first('clientCred.' . $accountField)" />
                    </div>
                    <div role="cell">
                        <x-cred-field label="Kata sandi" :context="$name" type="password" :mode="$mode"
                                      wire="clientCred.{{ $secretField }}" :value="$clientCred[$secretField]" />
                    </div>
                    @if ($isEdit)
                        <div class="kis-cred__row-act" role="cell"></div>
                    @endif
                </div>
            @endforeach

            {{-- Applications --}}
            <div class="kis-cred__group" role="row">
                <span role="cell">Aplikasi <span class="kis-cred__count">{{ count($appCreds) }}</span></span>
            </div>
            @forelse ($appCreds as $i => $cred)
                @php
                    $appName = $applicationOptions[$cred['application_id']] ?? null;
                    $until = filled($cred['account_period']) ? Carbon::parse($cred['account_period']) : null;
                    $expired = $until && $until->lt($today);
                @endphp
                <div class="kis-cred__row" role="row" wire:key="app-{{ $cred['id'] ?? 'new-' . $i }}">
                    @if ($isEdit)
                        <div class="kis-cred__svc is-form" role="cell">
                            <label class="sr-only" for="cred-app-{{ $i }}">Aplikasi</label>
                            <select id="cred-app-{{ $i }}" wire:model="appCreds.{{ $i }}.application_id"
                                    @class(['kis-cf__input', 'is-invalid' => $errors->has("appCreds.{$i}.application_id")])
                                    @error("appCreds.{$i}.application_id") aria-invalid="true" aria-describedby="cred-app-{{ $i }}-err" @enderror>
                                <option value="">Pilih aplikasi…</option>
                                @foreach ($applicationOptions as $id => $option)
                                    <option value="{{ $id }}">{{ $option }}</option>
                                @endforeach
                            </select>
                            @error("appCreds.{$i}.application_id")
                                <p class="kis-cf__error" id="cred-app-{{ $i }}-err">{{ $message }}</p>
                            @enderror
                            <label class="kis-cred__check">
                                <input type="checkbox" wire:model="appCreds.{{ $i }}.is_active"
                                       class="h-4 w-4 rounded border-gray-300 text-primary-600 focus:ring-primary-500 dark:border-white/20 dark:bg-white/5 dark:checked:bg-primary-500">
                                Aktif
                            </label>
                        </div>
                        <div class="kis-cred__stack" role="cell">
                            <x-cred-field label="Username" :context="$appName" mode="edit" placeholder="Username"
                                          wire="appCreds.{{ $i }}.username" :value="$cred['username']"
                                          :error="$errors->first('appCreds.' . $i . '.username')" />
                            <x-cred-field label="Kode aktivasi" :context="$appName" mode="edit" placeholder="Opsional" show-label
                                          wire="appCreds.{{ $i }}.activation_code" :value="$cred['activation_code']" />
                        </div>
                        <div class="kis-cred__stack" role="cell">
                            <x-cred-field label="Kata sandi" :context="$appName" type="password" mode="edit"
                                          wire="appCreds.{{ $i }}.password" :value="$cred['password']"
                                          :error="$errors->first('appCreds.' . $i . '.password')" />
                            <div class="kis-cf has-label">
                                <label class="kis-cf__label" for="cred-until-{{ $i }}">Berlaku hingga</label>
                                <input type="date" id="cred-until-{{ $i }}" wire:model="appCreds.{{ $i }}.account_period" class="kis-cf__input">
                            </div>
                        </div>
                        <div class="kis-cred__row-act" role="cell">
                            <button type="button" wire:click="removeAppCred({{ $i }})" x-on:click="dirty = true"
                                    class="kis-cred__icon-btn is-danger" title="Hapus" aria-label="Hapus {{ $appName ?? 'aplikasi ini' }}">
                                <x-heroicon-m-trash class="h-4 w-4" />
                            </button>
                        </div>
                    @else
                        <div class="kis-cred__svc" role="cell">
                            <span class="kis-cred__svc-tile">{{ CredentialManager::initials($appName ?? '?') }}</span>
                            <span class="kis-cred__svc-text">
                                <span class="kis-cred__svc-name" title="{{ $appName }}">{{ $appName ?? 'Aplikasi tanpa nama' }}</span>
                                <span class="kis-cred__svc-meta">
                                    @if ($cred['is_active'])
                                        <span class="kis-badge kis-badge--sm kis-badge--blue kis-badge--dot">Aktif</span>
                                    @else
                                        <span class="kis-badge kis-badge--sm kis-badge--neutral kis-badge--dot">Nonaktif</span>
                                    @endif
                                    @if ($until)
                                        <span @class(['is-bad' => $expired])>
                                            {{ $expired ? 'Kedaluwarsa' : 's/d' }} {{ $until->locale('id')->translatedFormat('j M Y') }}
                                        </span>
                                    @endif
                                </span>
                            </span>
                        </div>
                        <div class="kis-cred__stack" role="cell">
                            <x-cred-field label="Username" :context="$appName" wire="appCreds.{{ $i }}.username" :value="$cred['username']" />
                            @if (filled($cred['activation_code']))
                                <x-cred-field label="Kode aktivasi" :context="$appName" show-label
                                              wire="appCreds.{{ $i }}.activation_code" :value="$cred['activation_code']" />
                            @endif
                        </div>
                        <div role="cell">
                            <x-cred-field label="Kata sandi" :context="$appName" type="password"
                                          wire="appCreds.{{ $i }}.password" :value="$cred['password']" />
                        </div>
                    @endif
                </div>
            @empty
                <div class="kis-cred__row is-wide" role="row">
                    <p class="kis-cred__empty" role="cell">
                        <x-heroicon-o-key class="h-5 w-5 shrink-0" />
                        <span><b>Belum ada kredensial aplikasi.</b> {{ $isEdit ? 'Tambahkan dengan tombol di bawah.' : 'Klik Ubah untuk menambahkan.' }}</span>
                    </p>
                </div>
            @endforelse
            @if ($isEdit)
                <div class="kis-cred__row is-wide" role="row">
                    <div role="cell">
                        <button type="button" wire:click="addAppCred" x-on:click="dirty = true" class="kis-cred__add">
                            <x-heroicon-m-plus class="h-4 w-4" />
                            Tambah aplikasi
                        </button>
                    </div>
                </div>
            @endif

            {{-- PIC: one shared record across all of the PIC's clients --}}
            @if ($hasPic)
                <div class="kis-cred__group" role="row"><span role="cell">PIC</span></div>
                <div class="kis-cred__row" role="row" wire:key="pic">
                    <div class="kis-cred__svc" role="cell">
                        <span class="kis-cred__svc-tile is-round">{{ CredentialManager::initials($pic['name'] ?: 'PIC') }}</span>
                        <span class="kis-cred__svc-text">
                            <span class="kis-cred__svc-name" title="{{ $pic['name'] }}">{{ $pic['name'] ?: 'PIC' }}</span>
                            <span class="kis-cred__svc-meta">Login NIK, dipakai bersama</span>
                        </span>
                    </div>
                    <div role="cell">
                        <x-cred-field label="NIK" context="PIC" :mode="$mode" placeholder="NIK 16 digit"
                                      wire="pic.nik" :value="$pic['nik']" :error="$errors->first('pic.nik')" />
                    </div>
                    <div role="cell">
                        <x-cred-field label="Kata sandi" context="PIC" type="password" :mode="$mode"
                                      wire="pic.password" :value="$pic['password']" />
                    </div>
                    @if ($isEdit)
                        <div class="kis-cred__row-act" role="cell"></div>
                        <p class="kis-cred__note" role="cell">
                            <x-heroicon-m-information-circle class="h-4 w-4 shrink-0" />
                            <span>Data PIC dipakai oleh semua klien yang ditanganinya. Mengubah NIK atau kata sandi di sini berlaku untuk semua klien tersebut.</span>
                        </p>
                    @endif
                </div>
            @endif
        </div>
    </div>

    {{-- Edit actions --}}
    @if ($isEdit)
        <footer class="kis-cred__foot">
            @if ($errors->any())
                <p class="kis-cred__foot-note is-bad" role="alert">
                    <x-heroicon-m-exclamation-circle class="h-4 w-4 shrink-0" />
                    {{ $errors->count() }} kolom perlu diperbaiki.
                </p>
            @else
                <p class="kis-cred__foot-note">Username dan kata sandi aplikasi wajib diisi.</p>
            @endif
            <div class="kis-cred__foot-actions">
                <button type="button" wire:click="cancelEdit" x-on:click="dirty = false" class="kis-btn kis-btn--outline">Batal</button>
                <button type="button" wire:click="save" wire:loading.attr="disabled" wire:target="save" class="kis-btn kis-btn--primary disabled:cursor-wait disabled:opacity-70">
                    <x-filament::loading-indicator wire:loading wire:target="save" class="h-4 w-4" />
                    <x-heroicon-m-check wire:loading.remove wire:target="save" class="h-4 w-4" />
                    Simpan
                </button>
            </div>
        </footer>
    @endif
</div>
