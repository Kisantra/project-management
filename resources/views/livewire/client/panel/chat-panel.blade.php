<div
    x-data="{
        view: @js($compact ? ($activeThread ? 'messages' : 'threads') : 'split'),
        activeThreadId: @js($activeThread?->id),
        subscribedThreadId: null,
        showNewForm: @js($showNewThreadForm),
        init() {
            if (this.activeThreadId) {
                this.subscribe(this.activeThreadId);
            }

            Livewire.on('chat-thread-selected', (event) => {
                const threadId = event.threadId ?? event[0]?.threadId ?? null;
                if (!threadId) return;
                this.subscribe(threadId);
                this.view = @js($compact) ? 'messages' : 'split';
                this.scrollToBottom();
            });

            Livewire.on('chat-message-posted', () => this.scrollToBottom());
            this.$nextTick(() => this.scrollToBottom());
        },
        subscribe(threadId) {
            if (!window.Echo || !threadId || this.subscribedThreadId === threadId) return;
            if (this.subscribedThreadId) {
                window.Echo.leave('chat.thread.' + this.subscribedThreadId);
            }
            this.activeThreadId = threadId;
            this.subscribedThreadId = threadId;
            window.Echo.private('chat.thread.' + threadId)
                .listen('.message.sent', (payload) => {
                    $wire.handleBroadcastedMessage(payload?.message?.id ?? null);
                    this.scrollToBottom();
                });
        },
        scrollToBottom() {
            this.$nextTick(() => {
                const list = this.$refs.messageList;
                if (list) list.scrollTop = list.scrollHeight;
            });
        },
        goBack() {
            this.view = 'threads';
        },
        sendOnEnter(e) {
            if (e.key === 'Enter' && !e.shiftKey) {
                e.preventDefault();
                $wire.sendMessage();
            }
        }
    }"
    @class(['flex h-full flex-col overflow-hidden', 'kp-cp' => $compact, 'bg-white dark:bg-gray-950' => ! $compact])
>
    @if (!$hasClients)
        <div class="flex flex-1 flex-col items-center justify-center gap-4 px-6 py-12 text-center">
            <div class="flex h-14 w-14 items-center justify-center rounded-2xl bg-gray-100 dark:bg-gray-800">
                <x-heroicon-o-chat-bubble-left-right class="h-7 w-7 text-gray-400" />
            </div>
            <div>
                <p class="font-semibold text-gray-700 dark:text-gray-300">Belum ada klien</p>
                <p class="mt-1 text-sm text-gray-400 dark:text-gray-500">Klien akan muncul di sini setelah ditambahkan.</p>
            </div>
        </div>
    @else

        {{-- Compact mode: the floating chat of the client portal (chat.css). --}}
        @if ($compact)
            @php
                $typeLabels = ['client_support' => 'Layanan klien', 'project' => 'Proyek', 'tax_report' => 'Laporan pajak'];
                $initial = fn (?string $name) => mb_strtoupper(mb_substr(trim((string) $name) ?: '?', 0, 1));
            @endphp

            {{-- Threads --}}
            <div x-show="view === 'threads'" class="kp-cp__pane">
                <div class="kp-cp__head">
                    <div class="kp-cp__title">
                        <b>{{ $showAllClients ? 'Semua Percakapan' : ($selectedClient?->name ?? 'Chat') }}</b>
                        <small>{{ $threads->count() }} percakapan aktif</small>
                    </div>
                    <button type="button" wire:click="toggleNewThreadForm" class="kp-cp__icon" title="Percakapan baru" aria-label="Percakapan baru">
                        <x-heroicon-m-plus class="h-4 w-4" />
                    </button>
                </div>

                @if ($showNewThreadForm)
                    <form wire:submit.prevent="createThread" class="kp-cp__new">
                        <p class="kp-cp__label">Percakapan baru</p>
                        @if ($showAllClients)
                            <select wire:model.defer="newThreadClientId" class="kp-cp__field">
                                @foreach ($clients as $client)
                                    <option value="{{ $client->id }}">{{ $client->name }}</option>
                                @endforeach
                            </select>
                        @endif
                        <div class="kp-cp__row">
                            <input type="text" wire:model.defer="newThreadTitle" placeholder="Judul percakapan (opsional)" class="kp-cp__field" />
                            <button type="submit" class="kp-cp__send" title="Buat percakapan" aria-label="Buat percakapan">
                                <x-heroicon-m-check class="h-4 w-4" />
                            </button>
                        </div>
                        @error('newThreadClientId') <p class="kp-cp__error">{{ $message }}</p> @enderror
                        @error('newThreadTitle') <p class="kp-cp__error">{{ $message }}</p> @enderror
                    </form>
                @endif

                <div class="kp-cp__list">
                    @forelse ($threads as $thread)
                        @php
                            $latestMsg = $thread->latestMessage;
                            $isActive = $activeThread?->id === $thread->id;
                        @endphp
                        <button type="button" wire:click="selectThread({{ $thread->id }})" @class(['kp-cp__thread', 'is-on' => $isActive])>
                            <span class="kp-cp__avatar">{{ $initial($thread->client?->name) }}</span>
                            <span class="kp-cp__tbody">
                                <span class="kp-cp__tline">
                                    <b>{{ $thread->title ?: 'Percakapan' }}</b>
                                    <time>{{ ($latestMsg?->created_at ?? $thread->created_at)?->format('H.i') }}</time>
                                </span>
                                @if ($showAllClients && $thread->client)
                                    <span class="kp-cp__client">{{ $thread->client->name }}</span>
                                @endif
                                <span class="kp-cp__last">{{ $latestMsg?->body ?: 'Belum ada pesan.' }}</span>
                            </span>
                        </button>
                    @empty
                        <div class="kp-cp__empty">
                            <x-heroicon-o-chat-bubble-left-right class="h-6 w-6" />
                            <b>Belum ada percakapan</b>
                            <span>Mulai percakapan baru di atas.</span>
                        </div>
                    @endforelse
                </div>
            </div>

            {{-- Messages --}}
            <div x-show="view === 'messages'" class="kp-cp__pane" style="display: none;">
                <div class="kp-cp__head">
                    <button type="button" x-on:click="goBack()" class="kp-cp__icon" title="Kembali ke daftar percakapan" aria-label="Kembali">
                        <x-heroicon-m-arrow-left class="h-4 w-4" />
                    </button>
                    @if ($activeThread)
                        <div class="kp-cp__title">
                            <b>{{ $activeThread->title ?: 'Percakapan' }}</b>
                            <small>
                                @if ($showAllClients && $activeThread->client){{ $activeThread->client->name }} · @endif{{ $activeThread->participants->count() }} peserta
                            </small>
                        </div>
                        <x-kis.badge tone="neutral" size="sm">{{ $typeLabels[$activeThread->type] ?? str_replace('_', ' ', $activeThread->type) }}</x-kis.badge>
                    @else
                        <div class="kp-cp__title"><small>Pilih percakapan</small></div>
                    @endif
                </div>

                <div x-ref="messageList" class="kp-cp__msgs">
                    @forelse ($messages as $message)
                        @php $isOwn = $message->user_id === auth()->id(); @endphp
                        <div @class(['kp-cp__msg', 'is-own' => $isOwn])>
                            <p class="kp-cp__meta">
                                {{ $isOwn ? 'Anda' : ($message->user?->name ?? 'Pengguna') }} · {{ $message->created_at?->locale('id')->translatedFormat('j M, H.i') }}
                            </p>
                            <div class="kp-cp__bubble">{{ $message->body }}</div>
                        </div>
                    @empty
                        <div class="kp-cp__empty">
                            <x-heroicon-o-chat-bubble-oval-left class="h-6 w-6" />
                            <span>Belum ada pesan. Mulai percakapan!</span>
                        </div>
                    @endforelse
                </div>

                <form wire:submit.prevent="sendMessage" class="kp-cp__compose">
                    <div class="kp-cp__input">
                        <textarea wire:model.defer="messageBody" rows="1" x-on:keydown="sendOnEnter($event)"
                                  placeholder="Tulis pesan…" aria-label="Pesan" aria-describedby="kp-cp-hint"></textarea>
                        <button type="submit" class="kp-cp__send" wire:loading.attr="disabled" wire:target="sendMessage" title="Kirim pesan" aria-label="Kirim pesan">
                            <x-heroicon-m-paper-airplane class="h-4 w-4" wire:loading.remove wire:target="sendMessage" />
                            <x-filament::loading-indicator class="h-4 w-4" wire:loading wire:target="sendMessage" />
                        </button>
                    </div>
                    @error('messageBody') <p class="kp-cp__error">{{ $message }}</p> @enderror
                    <p id="kp-cp-hint" class="kp-cp__hint">Enter untuk kirim, Shift+Enter untuk baris baru</p>
                </form>
            </div>

        {{-- ═══════════════════════════════════════════════
             FULL / SPLIT MODE  (two-column layout)
        ════════════════════════════════════════════════ --}}
        @else
            <div class="grid h-full overflow-hidden lg:grid-cols-[320px_minmax(0,1fr)]">

                {{-- ── Left sidebar: thread list ────────────── --}}
                <aside class="flex flex-col border-b border-gray-200 bg-gray-50/60 dark:border-gray-800 dark:bg-gray-900/60 lg:border-b-0 lg:border-r">

                    {{-- Sidebar header --}}
                    <div class="flex shrink-0 items-center justify-between gap-3 border-b border-gray-200 px-4 py-3.5 dark:border-gray-800">
                        <div class="min-w-0">
                            <p class="truncate text-sm font-semibold text-gray-900 dark:text-white">
                                {{ $showAllClients ? 'Semua Percakapan' : ($selectedClient?->name ?? 'Chat') }}
                            </p>
                            <p class="text-[11px] text-gray-400">{{ $threads->count() }} percakapan</p>
                        </div>
                        <button
                            type="button"
                            wire:click="toggleNewThreadForm"
                            title="Percakapan baru"
                            class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg border border-gray-200 bg-white text-gray-500 shadow-sm transition hover:border-primary-300 hover:text-primary-600 dark:border-gray-700 dark:bg-gray-900 dark:hover:border-primary-600 dark:hover:text-primary-400"
                        >
                            <x-heroicon-o-plus class="h-4 w-4" />
                        </button>
                    </div>

                    {{-- New thread form --}}
                    @if ($showNewThreadForm)
                        <form
                            wire:submit.prevent="createThread"
                            class="shrink-0 border-b border-gray-200 bg-white px-4 py-3 dark:border-gray-800 dark:bg-gray-950"
                        >
                            <p class="mb-2 text-[11px] font-semibold uppercase tracking-wide text-gray-400">Percakapan Baru</p>
                            <div class="space-y-2">
                                @if ($showAllClients)
                                    <select
                                        wire:model.defer="newThreadClientId"
                                        class="w-full rounded-lg border-gray-200 text-sm shadow-sm focus:border-primary-500 focus:ring-primary-500 dark:border-gray-700 dark:bg-gray-900 dark:text-white"
                                    >
                                        @foreach ($clients as $client)
                                            <option value="{{ $client->id }}">{{ $client->name }}</option>
                                        @endforeach
                                    </select>
                                @endif

                                <div class="flex gap-2">
                                    <input
                                        type="text"
                                        wire:model.defer="newThreadTitle"
                                        placeholder="Judul percakapan (opsional)"
                                        class="min-w-0 flex-1 rounded-lg border-gray-200 text-sm shadow-sm placeholder:text-gray-400 focus:border-primary-500 focus:ring-primary-500 dark:border-gray-700 dark:bg-gray-900 dark:text-white"
                                    />
                                    <button
                                        type="submit"
                                        class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-primary-600 text-white transition hover:bg-primary-700"
                                    >
                                        <x-heroicon-o-check class="h-4 w-4" />
                                    </button>
                                </div>
                            </div>
                            @error('newThreadClientId')
                                <p class="mt-1 text-xs text-red-500">{{ $message }}</p>
                            @enderror
                            @error('newThreadTitle')
                                <p class="mt-1 text-xs text-red-500">{{ $message }}</p>
                            @enderror
                        </form>
                    @endif

                    {{-- Thread list --}}
                    <div class="flex-1 overflow-y-auto">
                        @forelse ($threads as $thread)
                            @php
                                $latestMsg = $thread->latestMessage;
                                $isActive  = $activeThread?->id === $thread->id;
                                $clientInitial = strtoupper(substr($thread->client?->name ?? '?', 0, 1));
                            @endphp

                            <button
                                type="button"
                                wire:click="selectThread({{ $thread->id }})"
                                class="group flex w-full items-start gap-3 border-b border-gray-100 px-4 py-3 text-left transition-colors dark:border-gray-800/80
                                    {{ $isActive
                                        ? 'bg-white dark:bg-gray-950'
                                        : 'hover:bg-white/80 dark:hover:bg-gray-950/60' }}"
                            >
                                <div class="mt-0.5 flex h-9 w-9 shrink-0 items-center justify-center rounded-xl text-[11px] font-bold
                                    {{ $isActive
                                        ? 'bg-gradient-to-br from-primary-500 to-primary-700 text-white'
                                        : 'bg-gray-200 text-gray-600 dark:bg-gray-700 dark:text-gray-300' }}">
                                    {{ $clientInitial }}
                                </div>

                                <div class="min-w-0 flex-1">
                                    <div class="flex items-baseline justify-between gap-2">
                                        <p class="truncate text-sm font-semibold
                                            {{ $isActive ? 'text-primary-700 dark:text-primary-300' : 'text-gray-900 dark:text-white' }}">
                                            {{ $thread->title ?: 'Percakapan' }}
                                        </p>
                                        <span class="shrink-0 text-[11px] text-gray-400">
                                            {{ ($latestMsg?->created_at ?? $thread->created_at)?->format('H:i') }}
                                        </span>
                                    </div>

                                    @if ($showAllClients && $thread->client)
                                        <p class="mt-0.5 truncate text-[11px] font-medium text-primary-600 dark:text-primary-400">
                                            {{ $thread->client->name }}
                                        </p>
                                    @endif

                                    <p class="mt-0.5 line-clamp-1 text-xs text-gray-400 dark:text-gray-500">
                                        {{ $latestMsg?->body ?: 'Belum ada pesan.' }}
                                    </p>
                                </div>
                            </button>
                        @empty
                            <div class="flex flex-col items-center justify-center gap-3 py-16 text-center">
                                <div class="flex h-12 w-12 items-center justify-center rounded-2xl bg-gray-100 dark:bg-gray-800">
                                    <x-heroicon-o-chat-bubble-oval-left class="h-6 w-6 text-gray-300 dark:text-gray-600" />
                                </div>
                                <div>
                                    <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Belum ada percakapan</p>
                                    <p class="mt-0.5 text-xs text-gray-400 dark:text-gray-500">Buat percakapan baru di atas.</p>
                                </div>
                            </div>
                        @endforelse
                    </div>
                </aside>

                {{-- ── Right: messages ──────────────────────── --}}
                <section class="flex flex-col overflow-hidden bg-white dark:bg-gray-950">
                    @if ($activeThread)

                        {{-- Message area header --}}
                        <header class="flex shrink-0 items-center justify-between gap-3 border-b border-gray-200 px-5 py-4 dark:border-gray-800">
                            <div class="flex items-center gap-3 min-w-0">
                                <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-gradient-to-br from-primary-500 to-primary-700 text-[11px] font-bold text-white">
                                    {{ strtoupper(substr($activeThread->client?->name ?? '?', 0, 1)) }}
                                </div>
                                <div class="min-w-0">
                                    <h2 class="truncate text-sm font-semibold text-gray-900 dark:text-white">{{ $activeThread->title }}</h2>
                                    <p class="text-[11px] text-gray-400">
                                        @if ($showAllClients && $activeThread->client)
                                            {{ $activeThread->client->name }} ·
                                        @endif
                                        {{ $activeThread->participants->count() }} peserta
                                    </p>
                                </div>
                            </div>

                            <span class="shrink-0 rounded-full bg-primary-50 px-2.5 py-1 text-[11px] font-semibold uppercase tracking-wide text-primary-700 dark:bg-primary-500/10 dark:text-primary-300">
                                {{ str_replace('_', ' ', $activeThread->type) }}
                            </span>
                        </header>

                        {{-- Messages --}}
                        <div
                            x-ref="messageList"
                            class="flex-1 space-y-3 overflow-y-auto bg-gray-50/70 px-5 py-5 dark:bg-gray-900/30"
                            style="scrollbar-width: thin; scrollbar-color: #d1d5db transparent;"
                        >
                            @forelse ($messages as $message)
                                @php $isOwn = $message->user_id === auth()->id(); @endphp

                                <div class="flex {{ $isOwn ? 'justify-end' : 'justify-start' }} items-end gap-2">
                                    @unless ($isOwn)
                                        <div class="flex h-7 w-7 shrink-0 items-center justify-center rounded-full bg-gradient-to-br from-gray-300 to-gray-400 text-[10px] font-bold text-white dark:from-gray-600 dark:to-gray-700">
                                            {{ strtoupper(substr($message->user?->name ?? '?', 0, 1)) }}
                                        </div>
                                    @endunless

                                    <div class="{{ $isOwn ? 'items-end' : 'items-start' }} flex max-w-[72%] flex-col">
                                        @unless ($isOwn)
                                            <p class="mb-1 ml-1 text-[11px] font-medium text-gray-400">
                                                {{ $message->user?->name ?? 'Pengguna' }}
                                            </p>
                                        @endunless

                                        <div class="rounded-2xl px-4 py-2.5 text-sm leading-relaxed shadow-sm
                                            {{ $isOwn
                                                ? 'rounded-br-sm bg-gradient-to-br from-primary-500 to-primary-700 text-white'
                                                : 'rounded-bl-sm border border-gray-200 bg-white text-gray-800 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-100' }}">
                                            <p class="whitespace-pre-wrap break-words">{{ $message->body }}</p>
                                        </div>

                                        <p class="mt-1 px-1 text-[10px] text-gray-400 {{ $isOwn ? 'text-right' : 'text-left' }}">
                                            {{ $message->created_at?->format('d M, H:i') }}
                                        </p>
                                    </div>
                                </div>
                            @empty
                                <div class="flex h-full flex-col items-center justify-center gap-4 py-16 text-center">
                                    <div class="flex h-14 w-14 items-center justify-center rounded-2xl bg-gray-100 dark:bg-gray-800">
                                        <x-heroicon-o-chat-bubble-oval-left class="h-7 w-7 text-gray-300 dark:text-gray-600" />
                                    </div>
                                    <div>
                                        <p class="font-medium text-gray-500 dark:text-gray-400">Belum ada pesan</p>
                                        <p class="mt-1 text-sm text-gray-400 dark:text-gray-500">Mulai percakapan dengan mengirim pesan.</p>
                                    </div>
                                </div>
                            @endforelse
                        </div>

                        {{-- Input --}}
                        <form
                            wire:submit.prevent="sendMessage"
                            class="shrink-0 border-t border-gray-200 bg-white px-4 py-3.5 dark:border-gray-800 dark:bg-gray-950"
                        >
                            <div class="flex items-end gap-3 rounded-xl border border-gray-200 bg-gray-50 px-4 py-2.5 transition-colors focus-within:border-primary-400 focus-within:bg-white dark:border-gray-700 dark:bg-gray-900 dark:focus-within:border-primary-600">
                                <textarea
                                    wire:model.defer="messageBody"
                                    rows="1"
                                    placeholder="Tulis pesan… (Enter kirim, Shift+Enter baris baru)"
                                    @keydown="sendOnEnter($event)"
                                    class="max-h-32 min-h-[1.75rem] flex-1 resize-none border-0 bg-transparent p-0 text-sm text-gray-800 placeholder:text-gray-400 focus:ring-0 dark:text-gray-100 dark:placeholder:text-gray-500"
                                    style="scrollbar-width: thin;"
                                ></textarea>

                                <button
                                    type="submit"
                                    wire:loading.attr="disabled"
                                    wire:target="sendMessage"
                                    title="Kirim pesan"
                                    class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-primary-600 text-white shadow-sm transition hover:bg-primary-700 disabled:cursor-wait disabled:opacity-60"
                                >
                                    <span wire:loading.remove wire:target="sendMessage">
                                        <x-heroicon-o-paper-airplane class="h-4 w-4" />
                                    </span>
                                    <span wire:loading wire:target="sendMessage">
                                        <svg class="h-4 w-4 animate-spin" fill="none" viewBox="0 0 24 24">
                                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/>
                                        </svg>
                                    </span>
                                </button>
                            </div>

                            @error('messageBody')
                                <p class="mt-1.5 text-xs text-red-500">{{ $message }}</p>
                            @enderror
                        </form>

                    @else
                        <div class="flex flex-1 flex-col items-center justify-center gap-4 px-8 text-center">
                            <div class="flex h-16 w-16 items-center justify-center rounded-2xl bg-primary-50 dark:bg-primary-500/10">
                                <x-heroicon-o-chat-bubble-left-right class="h-8 w-8 text-primary-500" />
                            </div>
                            <div>
                                <h2 class="text-base font-semibold text-gray-900 dark:text-white">Pilih percakapan</h2>
                                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                                    {{ $showAllClients ? 'Pilih atau buat percakapan untuk mulai.' : 'Pilih thread di sebelah kiri atau buat yang baru.' }}
                                </p>
                            </div>
                            <button
                                type="button"
                                wire:click="toggleNewThreadForm"
                                class="inline-flex items-center gap-2 rounded-lg bg-primary-600 px-4 py-2 text-sm font-medium text-white shadow-sm transition hover:bg-primary-700"
                            >
                                <x-heroicon-o-plus class="h-4 w-4" />
                                Percakapan baru
                            </button>
                        </div>
                    @endif
                </section>
            </div>
        @endif

    @endif
</div>
