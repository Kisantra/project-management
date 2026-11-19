{{-- Chat with the Kisantra team: a round button bottom right, the compact ChatPanel in a floating card. --}}
@auth
<div class="kp-chat print:hidden" x-data="{ open: false }" x-on:keydown.escape.window="open = false">
    <div class="kp-chat__pop" x-show="open" x-cloak
         x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0 translate-y-2"
         x-transition:enter-end="opacity-100 translate-y-0" x-transition:leave="transition ease-in duration-150"
         x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0"
         role="dialog" aria-label="Chat dengan tim Kisantra">
        @livewire('client.panel.chat-panel', ['compact' => true])
    </div>

    <button type="button" class="kp-chat__fab" x-on:click="open = ! open" x-bind:aria-expanded="open"
            x-bind:aria-label="open ? 'Tutup chat' : 'Chat dengan tim Kisantra'" title="Chat dengan tim Kisantra">
        <x-heroicon-o-chat-bubble-left-right x-show="! open" class="h-6 w-6" />
        <x-heroicon-o-x-mark x-show="open" x-cloak class="h-6 w-6" />
    </button>
</div>
@endauth
