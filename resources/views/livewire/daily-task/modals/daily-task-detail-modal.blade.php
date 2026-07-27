{{-- Daily Task Detail Modal — Tailwind Edition --}}
<div>
    <style>
        /* ── Keyframes only (cannot be expressed in Tailwind) ── */
        @import url('https://fonts.googleapis.com/css2?family=Syne:wght@600;700;800&family=DM+Sans:ital,opsz,wght@0,9..40,300;0,9..40,400;0,9..40,500;0,9..40,600;1,9..40,300&display=swap');

        @keyframes dt-slide-in {
            from { opacity: 0; transform: translateX(14px); }
            to   { opacity: 1; transform: translateX(0); }
        }
        @keyframes dt-fade-up {
            from { opacity: 0; transform: translateY(7px); }
            to   { opacity: 1; transform: translateY(0); }
        }
        @keyframes dt-bar-fill {
            from { width: 0%; }
            to   { width: var(--pct, 0%); }
        }
        @keyframes dt-ping-soft {
            0%, 100% { transform: scale(1);   opacity: .9; }
            50%       { transform: scale(1.3); opacity: .3; }
        }
        .dt-slide-in  { animation: dt-slide-in  .36s cubic-bezier(.22,1,.36,1) both; }
        .dt-fade-up   { animation: dt-fade-up   .30s cubic-bezier(.22,1,.36,1) both; }
        .dt-ping-soft { animation: dt-ping-soft  2s  ease-in-out infinite; }
        .dt-bar-fill  {
            animation: dt-bar-fill .6s cubic-bezier(.22,1,.36,1) both;
            animation-delay: .08s;
            width: var(--pct, 0%);
        }

        /* ── Syne display font for title ── */
        .dt-title {
            font-family: 'Syne', system-ui, sans-serif;
            font-weight: 700;
            letter-spacing: -0.02em;
        }

        /* ── Staggered fade-up delays for list items ── */
        .dt-stagger > *:nth-child(1)  { animation-delay: 0ms;   }
        .dt-stagger > *:nth-child(2)  { animation-delay: 40ms;  }
        .dt-stagger > *:nth-child(3)  { animation-delay: 80ms;  }
        .dt-stagger > *:nth-child(4)  { animation-delay: 120ms; }
        .dt-stagger > *:nth-child(5)  { animation-delay: 160ms; }
        .dt-stagger > *:nth-child(6)  { animation-delay: 200ms; }
        .dt-stagger > *:nth-child(n+7){ animation-delay: 220ms; }

        /* ── Scrollbar ── */
        .dt-scroll::-webkit-scrollbar       { width: 4px; }
        .dt-scroll::-webkit-scrollbar-track { background: transparent; }
        .dt-scroll::-webkit-scrollbar-thumb { background: rgba(0,0,0,.12); border-radius: 99px; }
        .dark .dt-scroll::-webkit-scrollbar-thumb { background: rgba(255,255,255,.10); }

        /* ── Dropdown ── */
        .dt-dropdown {
            position: absolute;
            right: 0; top: calc(100% + 6px);
            min-width: 160px;
            z-index: 100;
        }
        /* ── Subtask hover reveal ── */
        .dt-sub-row:hover .dt-sub-actions { opacity: 1; }
        .dt-sub-actions { opacity: 0; transition: opacity .15s; }

        /* ── Description card hover reveal ── */
        .dt-desc-card:hover .dt-desc-edit { opacity: 1; }
        .dt-desc-edit { opacity: 0; transition: opacity .15s; }

        /* ── Title edit icon reveal ── */
        .dt-title-group:hover .dt-title-edit { opacity: 1; }
        .dt-title-edit { opacity: 0; transition: opacity .15s; }

        /* ── Comment form avatar ── */
        .dt-comment-sender-ava {
            background: linear-gradient(135deg, #0e7490 0%, #0891b2 100%);
        }
        .dark .dt-comment-sender-ava {
            background: linear-gradient(135deg, #22d3ee 0%, #0891b2 100%);
        }

        /* ── Avatar stack ── */
        .dt-ava-stack > * + * { margin-left: -6px; }

        /* ── Activity timeline line ── */
        .dt-timeline::before {
            content: '';
            position: absolute;
            left: 15px; top: 20px; bottom: 0;
            width: 1px;
            background: linear-gradient(to bottom, rgba(14,116,144,.35), transparent);
        }
        .dark .dt-timeline::before {
            background: linear-gradient(to bottom, rgba(34,211,238,.3), transparent);
        }

        /* ── Progress bar gradient ── */
        .dt-progress-fill {
            background: linear-gradient(90deg, #0e7490 0%, #67e8f9 100%);
        }
        .dark .dt-progress-fill {
            background: linear-gradient(90deg, #22d3ee 0%, #67e8f9 100%);
        }

        /* ── Avatar gradient (assignees) ── */
        .dt-member-ava {
            background: linear-gradient(135deg, #0e7490 0%, #0891b2 100%);
        }
        .dark .dt-member-ava {
            background: linear-gradient(135deg, #22d3ee 0%, #0891b2 100%);
        }

        /*
         * Panel modal Filament default memakai gray-900 (#18181b, dingin),
         * sedangkan konten .dt-slide-in memakai #111110 (hangat). Karena
         * .fi-modal-content punya padding 24px, warna dingin itu bocor sebagai
         * bingkai di sekeliling konten -> terasa "tidak sinkron" di dark mode.
         * Samakan panel + hilangkan bingkai. Di-scope via :has(.dt-slide-in)
         * agar tidak mengganggu modal Filament lain.
         */
        .dark .fi-modal-window:has(.dt-slide-in) {
            background-color: #111110;
        }
        .fi-modal-window:has(.dt-slide-in) .fi-modal-content {
            padding: 0;
        }
    </style>

    <x-filament::modal id="task-detail-modal" width="2xl" slide-over>
        @if($task)
                {{-- Shell: flat white / flat dark, no gradient --}}
                <div class="dt-slide-in flex flex-col min-h-full bg-white dark:bg-[#111110] text-gray-900 dark:text-[#f5f4f0]" style="font-family:'DM Sans',system-ui,sans-serif">

                    {{-- ─── Header ─── --}}
                    <div class="sticky top-0 z-20 flex items-center justify-between px-6 py-4 bg-white dark:bg-[#111110] border-b border-black/[.08] dark:border-white/[.07]">
                        <div class="flex items-center gap-3">
                            <button wire:click="closeModal"
                                class="flex items-center justify-center w-8 h-8 rounded-lg border border-black/[.09] dark:border-white/[.08] text-gray-400 dark:text-gray-500 hover:bg-gray-100 dark:hover:bg-white/[.06] hover:text-gray-700 dark:hover:text-white transition-all duration-150"
                                title="Tutup">
                                <x-heroicon-o-x-mark class="w-4 h-4" />
                            </button>
                            <span class="text-[10px] font-semibold tracking-[.12em] uppercase text-cyan-600 dark:text-cyan-400">
                                Tugas Harian
                            </span>
                        </div>
                        <div class="flex items-center gap-2">
                            {{ $this->deleteAction }}
                        </div>
                    </div>

                    {{-- ─── Scrollable body ─── --}}
                    <div class="dt-scroll flex-1 overflow-y-auto px-6 py-7 flex flex-col gap-7">

                        {{-- ─── Title ─── --}}
                        <div class="dt-fade-up flex items-start gap-3" style="animation-delay:.04s">

                            {{-- Completion toggle --}}
                            <button wire:click="toggleTaskCompletion"
                                class="flex-shrink-0 mt-1 w-[22px] h-[22px] rounded-full flex items-center justify-center transition-all duration-200"
                                title="Toggle selesai">
                                @if($task->status === 'completed')
                                    <x-heroicon-s-check-circle class="dt-ping-soft w-[22px] h-[22px] text-emerald-500 dark:text-emerald-400" />
                                @else
                                    <div class="w-[22px] h-[22px] rounded-full border-2 border-black/20 dark:border-white/20 flex items-center justify-center hover:border-cyan-500 dark:hover:border-cyan-400 hover:bg-cyan-50 dark:hover:bg-cyan-900/20 transition-all duration-200">
                                        <x-heroicon-o-check class="w-3 h-3 text-gray-300 dark:text-gray-600" />
                                    </div>
                                @endif
                            </button>

                            {{-- Title --}}
                            <div class="flex-1 min-w-0">
                                @if($editingTitle)
                                    <div class="flex flex-col gap-3">
                                        <div>{{ $this->taskEditForm }}</div>
                                        <div class="flex items-center gap-2">
                                            <button wire:click="saveTitle" class="flex items-center justify-center w-7 h-7 rounded-md text-emerald-600 dark:text-emerald-400 hover:bg-emerald-50 dark:hover:bg-emerald-900/20 transition-all">
                                                <x-heroicon-o-check class="w-4 h-4" />
                                            </button>
                                            <button wire:click="cancelEditTitle" class="flex items-center justify-center w-7 h-7 rounded-md text-red-500 hover:bg-red-50 dark:hover:bg-red-900/20 transition-all">
                                                <x-heroicon-o-x-mark class="w-4 h-4" />
                                            </button>
                                        </div>
                                    </div>
                                @else
                                    <div class="dt-title-group flex items-start gap-2">
                                        <h1 class="dt-title text-[1.5rem] leading-snug text-gray-900 dark:text-[#f5f4f0] {{ $task->status === 'completed' ? 'line-through text-gray-400 dark:text-gray-600' : '' }}">
                                            {{ $task->title }}
                                        </h1>
                                        <button wire:click="startEditTitle"
                                            class="dt-title-edit mt-1 flex items-center justify-center w-6 h-6 rounded-md text-gray-400 hover:text-cyan-600 dark:hover:text-cyan-400 hover:bg-gray-100 dark:hover:bg-white/[.06] transition-all"
                                            title="Edit judul">
                                            <x-heroicon-o-pencil class="w-3.5 h-3.5" />
                                        </button>
                                    </div>
                                @endif
                            </div>
                        </div>

                        {{-- ─── Meta grid ─── --}}
                        <div class="dt-fade-up rounded-xl border border-black/[.08] dark:border-white/[.07] overflow-hidden" style="animation-delay:.08s">

                            {{-- Created --}}
                            <div class="flex items-center justify-between px-4 py-3 hover:bg-gray-50 dark:hover:bg-white/[.03] border-b border-black/[.06] dark:border-white/[.05] transition-colors">
                                <span class="flex items-center gap-2 text-[.75rem] text-gray-400 dark:text-[#6b6760] font-medium tracking-wide whitespace-nowrap">
                                    <x-heroicon-o-clock class="w-3.5 h-3.5" /> Dibuat
                                </span>
                                <span class="text-[.82rem] text-gray-700 dark:text-[#f5f4f0]">{{ $task->created_at->format('d M Y, g:i A') }}</span>
                            </div>

                            {{-- Creator --}}
                            @if($task->creator)
                                <div class="flex items-center justify-between px-4 py-3 hover:bg-gray-50 dark:hover:bg-white/[.03] border-b border-black/[.06] dark:border-white/[.05] transition-colors">
                                    <span class="flex items-center gap-2 text-[.75rem] text-gray-400 dark:text-[#6b6760] font-medium tracking-wide whitespace-nowrap">
                                        <x-heroicon-o-user class="w-3.5 h-3.5" /> Dibuat oleh
                                    </span>
                                    <div class="flex items-center gap-2">
                                        <div class="dt-member-ava flex items-center justify-center w-5 h-5 rounded-full text-[.62rem] font-bold text-white">
                                            {{ strtoupper(substr($task->creator->name, 0, 1)) }}
                                        </div>
                                        <span class="text-[.82rem] text-gray-700 dark:text-[#f5f4f0]">{{ $task->creator->name }}</span>
                                    </div>
                                </div>
                            @endif

                            {{-- Status --}}
                            <div class="flex items-center justify-between px-4 py-3 hover:bg-gray-50 dark:hover:bg-white/[.03] border-b border-black/[.06] dark:border-white/[.05] transition-colors">
                                <span class="flex items-center gap-2 text-[.75rem] text-gray-400 dark:text-[#6b6760] font-medium tracking-wide whitespace-nowrap">
                                    <x-heroicon-o-signal class="w-3.5 h-3.5" /> Status
                                </span>
                                <div class="relative" x-data="{ open: false }">
                                    <button @click="open = !open" class="flex items-center gap-1.5 px-2.5 py-1 rounded-full text-[.74rem] font-medium border transition-all duration-150
                                        {{ match ($task->status) {
                'completed' => 'bg-emerald-50 dark:bg-emerald-900/20 text-emerald-700 dark:text-emerald-400 border-emerald-200 dark:border-emerald-800',
                'in_progress' => 'bg-yellow-50 dark:bg-yellow-900/20 text-yellow-700 dark:text-yellow-400 border-yellow-200 dark:border-yellow-800',
                'cancelled' => 'bg-red-50 dark:bg-red-900/20 text-red-700 dark:text-red-400 border-red-200 dark:border-red-800',
                default => 'bg-gray-100 dark:bg-white/[.06] text-gray-600 dark:text-gray-400 border-black/[.08] dark:border-white/[.08]',
            } }}">
                                        <span class="w-1.5 h-1.5 rounded-full {{ match ($task->status) {
                'completed' => 'bg-emerald-500',
                'in_progress' => 'bg-yellow-500',
                'cancelled' => 'bg-red-500',
                default => 'bg-gray-400',
            } }}"></span>
                                        {{ $this->getStatusOptions()[$task->status] ?? $task->status }}
                                        <x-heroicon-o-chevron-down class="w-3 h-3 opacity-50" />
                                    </button>
                                    <div x-show="open" @click.away="open = false" x-cloak
                                        class="dt-dropdown bg-white dark:bg-[#1a1a18] border border-black/[.10] dark:border-white/[.10] rounded-lg shadow-xl overflow-hidden">
                                        @foreach($this->getStatusOptions() as $sv => $sl)
                                                                    <button wire:click="updateStatus('{{ $sv }}')" @click="open = false"
                                                                        class="w-full text-left flex items-center gap-2 px-3.5 py-2.5 text-[.81rem] text-gray-600 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-white/[.05] hover:text-gray-900 dark:hover:text-white transition-colors">
                                                                        <span class="w-2 h-2 rounded-full {{ match ($sv) {
                                                'completed' => 'bg-emerald-500',
                                                'in_progress' => 'bg-yellow-500',
                                                'cancelled' => 'bg-red-500',
                                                default => 'bg-gray-400',
                                            } }}"></span>
                                                                        {{ $sl }}
                                                                    </button>
                                        @endforeach
                                    </div>
                                </div>
                            </div>

                            {{-- Priority --}}
                            <div class="flex items-center justify-between px-4 py-3 hover:bg-gray-50 dark:hover:bg-white/[.03] border-b border-black/[.06] dark:border-white/[.05] transition-colors">
                                <span class="flex items-center gap-2 text-[.75rem] text-gray-400 dark:text-[#6b6760] font-medium tracking-wide whitespace-nowrap">
                                    <x-heroicon-o-bolt class="w-3.5 h-3.5" /> Prioritas
                                </span>
                                <div class="relative" x-data="{ open: false }">
                                    <button @click="open = !open" class="flex items-center gap-1.5 px-2.5 py-1 rounded-full text-[.74rem] font-medium border transition-all duration-150
                                        {{ match ($task->priority) {
                                            'urgent' => 'bg-red-50 dark:bg-red-900/20 text-red-700 dark:text-red-400 border-red-200 dark:border-red-800',
                                            'high' => 'bg-cyan-50 dark:bg-cyan-900/20 text-cyan-700 dark:text-cyan-400 border-cyan-200 dark:border-cyan-800',
                                            'low' => 'bg-gray-100 dark:bg-white/[.06] text-gray-500 dark:text-gray-500 border-black/[.08] dark:border-white/[.07]',
                                            default => 'bg-blue-50 dark:bg-blue-900/20 text-blue-700 dark:text-blue-400 border-blue-200 dark:border-blue-800',
                                        } }}">
                                        {{ $this->getPriorityOptions()[$task->priority] ?? $task->priority }}
                                        <x-heroicon-o-chevron-down class="w-3 h-3 opacity-50" />
                                    </button>
                                    <div x-show="open" @click.away="open = false" x-cloak
                                        class="dt-dropdown bg-white dark:bg-[#1a1a18] border border-black/[.10] dark:border-white/[.10] rounded-lg shadow-xl overflow-hidden">
                                        @foreach($this->getPriorityOptions() as $pv => $pl)
                                            <button wire:click="updatePriority('{{ $pv }}')" @click="open = false"
                                                class="w-full text-left flex items-center gap-2 px-3.5 py-2.5 text-[.81rem] text-gray-600 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-white/[.05] hover:text-gray-900 dark:hover:text-white transition-colors">
                                                {{ $pl }}
                                            </button>
                                        @endforeach
                                    </div>
                                </div>
                            </div>

                            {{-- Due date --}}
                            <div class="flex items-center justify-between px-4 py-3 hover:bg-gray-50 dark:hover:bg-white/[.03] border-b border-black/[.06] dark:border-white/[.05] transition-colors">
                                <span class="flex items-center gap-2 text-[.75rem] text-gray-400 dark:text-[#6b6760] font-medium tracking-wide whitespace-nowrap">
                                    <x-heroicon-o-calendar-days class="w-3.5 h-3.5" /> Due Date
                                </span>
                                <div class="text-right">
                                    <span class="text-[.82rem] text-gray-700 dark:text-[#f5f4f0]">{{ $task->task_date->format('d M Y') }}</span>
                                    @if($task->start_task_date && $task->start_task_date != $task->task_date)
                                        <p class="text-[.72rem] text-gray-400 dark:text-[#6b6760] mt-0.5">Mulai: {{ $task->start_task_date->format('d M Y') }}</p>
                                    @endif
                                </div>
                            </div>

                            {{-- Assignees --}}
                            <div class="flex items-center justify-between px-4 py-3 hover:bg-gray-50 dark:hover:bg-white/[.03] border-b border-black/[.06] dark:border-white/[.05] transition-colors">
                                <span class="flex items-center gap-2 text-[.75rem] text-gray-400 dark:text-[#6b6760] font-medium tracking-wide whitespace-nowrap">
                                    <x-heroicon-o-users class="w-3.5 h-3.5" /> Assignees
                                </span>
                                <div class="relative" x-data="{ assigneeOpen: false, buttonRect: {} }">
                                    <button @click="assigneeOpen = !assigneeOpen; buttonRect = $el.getBoundingClientRect()"
                                            class="flex items-center gap-2 px-2.5 py-1 rounded-full text-[.74rem] font-medium border transition-all duration-150
                                                   bg-gray-100 dark:bg-white/[.06] text-gray-600 dark:text-gray-400 border-black/[.08] dark:border-white/[.08]
                                                   hover:border-cyan-400 dark:hover:border-cyan-600 hover:bg-cyan-50/50 dark:hover:bg-cyan-900/10">
                                        @if($task->assignedUsers && $task->assignedUsers->count() > 0)
                                            <div class="dt-ava-stack flex items-center">
                                                @foreach($task->assignedUsers->take(3) as $u)
                                                    <div class="dt-member-ava flex items-center justify-center w-5 h-5 rounded-full text-[.58rem] font-bold text-white border border-white dark:border-[#111110]" title="{{ $u->name }}">
                                                        {{ strtoupper(substr($u->name, 0, 1)) }}
                                                    </div>
                                                @endforeach
                                                @if($task->assignedUsers->count() > 3)
                                                    <span class="text-[.68rem] text-gray-400 ml-0.5">+{{ $task->assignedUsers->count() - 3 }}</span>
                                                @endif
                                            </div>
                                            <span class="text-[.74rem]">{{ $task->assignedUsers->count() }} orang</span>
                                        @else
                                            <span>+ Tambah</span>
                                        @endif
                                        <x-heroicon-o-chevron-down class="w-3 h-3 opacity-50" />
                                    </button>

                                    <template x-teleport="body">
                                        <div x-show="assigneeOpen" x-cloak @click.away="assigneeOpen = false"
                                             x-transition:enter="transition ease-out duration-150"
                                             x-transition:enter-start="opacity-0 scale-95"
                                             x-transition:enter-end="opacity-100 scale-100"
                                             class="fixed w-64 bg-white dark:bg-[#1a1a18] rounded-xl shadow-xl border border-black/[.09] dark:border-white/[.10] z-[9999] max-h-72 overflow-hidden flex flex-col"
                                             x-bind:style="{
                                                 top: Math.min(buttonRect.bottom + window.scrollY + 6, window.innerHeight + window.scrollY - 300) + 'px',
                                                 left: Math.max(8, Math.min(buttonRect.left + window.scrollX, window.innerWidth - 264 - 8)) + 'px'
                                             }">

                                            {{-- Header --}}
                                            <div class="px-3.5 py-2.5 text-[.7rem] font-semibold uppercase tracking-wider text-gray-400 dark:text-gray-500 border-b border-black/[.07] dark:border-white/[.07] flex-shrink-0 flex items-center gap-2 sticky top-0 bg-white dark:bg-[#1a1a18]">
                                                <x-heroicon-o-user-plus class="w-3.5 h-3.5" />
                                                Assign Users
                                            </div>

                                            {{-- User List --}}
                                            <div class="overflow-y-auto flex-1">
                                                @foreach($this->getUserOptions() as $userId => $userName)
                                                    @php $isAssigned = $task->assignedUsers->contains($userId); @endphp
                                                    <button wire:click="{{ $isAssigned ? 'unassignUser' : 'assignUser' }}({{ $userId }})"
                                                            class="w-full text-left flex items-center gap-3 px-3.5 py-2.5 text-[.82rem]
                                                                   text-gray-600 dark:text-gray-300
                                                                   hover:bg-gray-50 dark:hover:bg-white/[.04] transition-colors
                                                                   {{ $isAssigned ? 'bg-cyan-50/50 dark:bg-cyan-900/10' : '' }}">
                                                        <div class="dt-member-ava flex items-center justify-center w-7 h-7 rounded-full text-[.7rem] font-bold text-white flex-shrink-0">
                                                            {{ strtoupper(substr($userName, 0, 1)) }}
                                                        </div>
                                                        <span class="font-medium flex-1 truncate">{{ $userName }}</span>
                                                        @if($isAssigned)
                                                            <x-heroicon-s-check class="w-4 h-4 text-emerald-500 flex-shrink-0" />
                                                        @endif
                                                    </button>
                                                @endforeach
                                            </div>
                                        </div>
                                    </template>
                                </div>
                            </div>

                            {{-- Project --}}
                            @if($task->project)
                                <div class="flex items-center justify-between px-4 py-3 hover:bg-gray-50 dark:hover:bg-white/[.03] border-b border-black/[.06] dark:border-white/[.05] transition-colors">
                                    <span class="flex items-center gap-2 text-[.75rem] text-gray-400 dark:text-[#6b6760] font-medium tracking-wide whitespace-nowrap">
                                        <x-heroicon-o-folder-open class="w-3.5 h-3.5" /> Project
                                    </span>
                                    <span class="text-[.82rem] text-gray-700 dark:text-[#f5f4f0]">{{ $task->project->name }}</span>
                                </div>
                            @endif

                            {{-- Client --}}
                            @if($task->project && $task->project->client)
                                <div class="flex items-center justify-between px-4 py-3 hover:bg-gray-50 dark:hover:bg-white/[.03] transition-colors">
                                    <span class="flex items-center gap-2 text-[.75rem] text-gray-400 dark:text-[#6b6760] font-medium tracking-wide whitespace-nowrap">
                                        <x-heroicon-o-building-office class="w-3.5 h-3.5" /> Client
                                    </span>
                                    <span class="text-[.82rem] text-gray-700 dark:text-[#f5f4f0]">{{ $task->project->client->name }}</span>
                                </div>
                            @endif

                        </div>{{-- end meta grid --}}

                        {{-- ─── Description ─── --}}
                        <div class="dt-fade-up" style="animation-delay:.12s">
                            <p class="text-[.7rem] font-semibold tracking-[.1em] uppercase text-gray-400 dark:text-[#6b6760] mb-2.5">Deskripsi</p>

                            @if($editingDescription)
                                <div class="flex flex-col gap-3">
                                    {{ $this->descriptionForm }}
                                    <div class="flex gap-2">
                                        <button wire:click="saveDescription"
                                            class="px-4 py-2 rounded-lg text-[.8rem] font-medium bg-cyan-600 dark:bg-cyan-500 text-white hover:opacity-90 transition-opacity">
                                            Simpan
                                        </button>
                                        <button wire:click="cancelEditDescription"
                                            class="px-4 py-2 rounded-lg text-[.8rem] font-medium border border-black/[.09] dark:border-white/[.10] text-gray-600 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-white/[.05] transition-colors">
                                            Batal
                                        </button>
                                    </div>
                                </div>
                            @else
                                <div class="dt-desc-card relative rounded-xl border border-black/[.08] dark:border-white/[.07] bg-gray-50 dark:bg-[#1a1a18] p-4">
                                    @if($task->description)
                                        <div class="text-[.875rem] leading-[1.75] text-gray-600 dark:text-[#a8a49f]">{!! $task->description !!}</div>
                                    @else
                                        <span class="text-[.855rem] italic text-gray-400 dark:text-[#6b6760]">Belum ada deskripsi&hellip;</span>
                                    @endif
                                    <div class="dt-desc-edit absolute top-2.5 right-2.5">
                                        <button wire:click="startEditDescription"
                                            class="flex items-center justify-center w-7 h-7 rounded-md text-gray-400 hover:text-cyan-600 dark:hover:text-cyan-400 hover:bg-gray-100 dark:hover:bg-white/[.07] transition-all"
                                            title="Edit deskripsi">
                                            <x-heroicon-o-pencil class="w-3.5 h-3.5" />
                                        </button>
                                    </div>
                                </div>
                            @endif
                        </div>

                        {{-- ─── Subtasks ─── --}}
                        <div class="dt-fade-up" style="animation-delay:.16s">
                            @php
                                $completed = $task->subtasks ? $task->subtasks->where('status', 'completed')->count() : 0;
                                $total = $task->subtasks ? $task->subtasks->count() : 0;
                                $progress = $total > 0 ? round(($completed / $total) * 100) : 0;
                            @endphp

                            {{-- Heading + progress --}}
                            <div class="flex items-center justify-between mb-3">
                                <p class="text-[.7rem] font-semibold tracking-[.1em] uppercase text-gray-400 dark:text-[#6b6760]">Subtasks</p>
                                @if($total > 0)
                                    <div class="flex items-center gap-2.5">
                                        <span class="text-[.73rem] text-gray-400 dark:text-[#6b6760]">{{ $completed }}/{{ $total }}</span>
                                        <div class="w-20 h-1 rounded-full bg-gray-200 dark:bg-white/[.08] overflow-hidden">
                                            <div class="dt-bar-fill dt-progress-fill h-full rounded-full" style="--pct:{{ $progress }}%"></div>
                                        </div>
                                        <span class="text-[.73rem] font-semibold text-cyan-600 dark:text-cyan-400">{{ $progress }}%</span>
                                    </div>
                                @endif
                            </div>

                            {{-- List --}}
                            @if($task->subtasks && $task->subtasks->count() > 0)
                                {{-- Sortable drag-and-drop: onEnd mengumpulkan urutan id dari DOM
                                     lalu memanggil reorderSubtasks di server. Sortable.js sudah
                                     dimuat oleh komponen list di halaman ini. --}}
                                <div class="flex flex-col gap-0.5 dt-stagger"
                                     x-data="{
                                         sortable: null,
                                         initSort() {
                                             if (!window.Sortable || !this.$el) return;
                                             if (this.sortable) { this.sortable.destroy(); this.sortable = null; }
                                             this.sortable = window.Sortable.create(this.$el, {
                                                 handle: '.subtask-handle',
                                                 animation: 150,
                                                 ghostClass: 'opacity-40',
                                                 onEnd: () => {
                                                     const ids = Array.from(this.$el.querySelectorAll('[data-subtask-id]'))
                                                         .map(el => el.getAttribute('data-subtask-id'));
                                                     $wire.call('reorderSubtasks', ids);
                                                 }
                                             });
                                         }
                                     }"
                                     x-init="$nextTick(() => initSort())">
                                    @foreach($task->subtasks->sortBy('order') as $subtask)
                                                <div wire:key="subtask-{{ $subtask->id }}" data-subtask-id="{{ $subtask->id }}"
                                                    class="dt-sub-row dt-fade-up flex items-start gap-2 px-2 py-2.5 rounded-lg hover:bg-gray-50 dark:hover:bg-white/[.03] transition-colors">

                                                    {{-- Drag handle --}}
                                                    <button type="button" title="Seret untuk mengurutkan"
                                                        class="subtask-handle flex-shrink-0 mt-0.5 flex items-center justify-center w-5 h-5 rounded text-gray-300 dark:text-gray-600 hover:text-gray-500 dark:hover:text-gray-300 cursor-grab active:cursor-grabbing transition-colors">
                                                        <x-heroicon-o-bars-3 class="w-4 h-4" />
                                                    </button>

                                                    {{-- Toggle --}}
                                                    <button wire:click="toggleSubtask({{ $subtask->id }})"
                                                        class="flex-shrink-0 mt-0.5 flex items-center justify-center w-5 h-5 rounded-full border-2 transition-all duration-200
                                                            {{ $subtask->status === 'completed'
                                        ? 'bg-emerald-500 dark:bg-emerald-500 border-emerald-500 dark:border-emerald-500'
                                        : 'border-black/20 dark:border-white/20 hover:border-cyan-500 dark:hover:border-cyan-400' }}"
                                                        title="Toggle">
                                                        @if($subtask->status === 'completed')
                                                            <x-heroicon-s-check class="w-3 h-3 text-white" />
                                                        @endif
                                                    </button>

                                                    {{-- Content --}}
                                                    <div class="flex-1 min-w-0">
                                                        @if($editingSubtaskId === $subtask->id)
                                                            <div class="flex items-center gap-2">
                                                                <div class="flex-1">{{ $this->editSubtaskForm }}</div>
                                                                <button wire:click="saveSubtaskEdit"
                                                                    class="flex items-center justify-center w-7 h-7 rounded-md text-emerald-600 dark:text-emerald-400 hover:bg-emerald-50 dark:hover:bg-emerald-900/20 transition-all">
                                                                    <x-heroicon-o-check class="w-4 h-4" />
                                                                </button>
                                                                <button wire:click="cancelEditSubtask"
                                                                    class="flex items-center justify-center w-7 h-7 rounded-md text-red-500 hover:bg-red-50 dark:hover:bg-red-900/20 transition-all">
                                                                    <x-heroicon-o-x-mark class="w-4 h-4" />
                                                                </button>
                                                            </div>
                                                        @else
                                                            <div class="flex items-start justify-between gap-2">
                                                                <div>
                                                                    <p class="text-[.855rem] text-gray-800 dark:text-[#f5f4f0] leading-snug
                                                                        {{ $subtask->status === 'completed' ? 'line-through text-gray-400 dark:text-[#6b6760]' : '' }}">
                                                                        {{ $subtask->title }}
                                                                    </p>
                                                                    @if($subtask->status === 'completed')
                                                                        <p class="text-[.72rem] text-emerald-600 dark:text-emerald-400 mt-0.5">
                                                                            Selesai {{ $subtask->updated_at->diffForHumans() }}
                                                                        </p>
                                                                    @endif
                                                                </div>
                                                                <div class="dt-sub-actions flex items-center gap-0.5 flex-shrink-0">
                                                                    <button wire:click="startEditSubtask({{ $subtask->id }})"
                                                                        class="flex items-center justify-center w-7 h-7 rounded-md text-gray-400 hover:text-gray-700 dark:hover:text-gray-200 hover:bg-gray-100 dark:hover:bg-white/[.07] transition-all"
                                                                        title="Edit">
                                                                        <x-heroicon-o-pencil class="w-3.5 h-3.5" />
                                                                    </button>
                                                                    <button wire:click="deleteSubtask({{ $subtask->id }})" wire:confirm="Hapus subtask ini?"
                                                                        class="flex items-center justify-center w-7 h-7 rounded-md text-gray-400 hover:text-red-600 dark:hover:text-red-400 hover:bg-red-50 dark:hover:bg-red-900/20 transition-all"
                                                                        title="Hapus">
                                                                        <x-heroicon-o-trash class="w-3.5 h-3.5" />
                                                                    </button>
                                                                </div>
                                                            </div>
                                                        @endif
                                                    </div>
                                                </div>
                                    @endforeach
                                </div>
                            @else
                                <div class="text-center py-6">
                                    <div class="flex items-center justify-center w-10 h-10 rounded-full bg-gray-100 dark:bg-white/[.05] mx-auto mb-3">
                                        <x-heroicon-o-list-bullet class="w-5 h-5 text-gray-400 dark:text-[#6b6760]" />
                                    </div>
                                    <p class="text-[.88rem] font-medium text-gray-500 dark:text-[#a8a49f]">Belum ada subtask</p>
                                    <p class="text-[.8rem] text-gray-400 dark:text-[#6b6760]">Tambahkan subtask di bawah ini</p>
                                </div>
                            @endif

                            {{-- Add subtask form --}}
                            <form wire:submit="addSubtask"
                                class="flex items-center gap-2.5 mt-3 px-3.5 py-2.5 rounded-lg border border-dashed border-black/[.12] dark:border-white/[.10] focus-within:border-cyan-500 dark:focus-within:border-cyan-500 transition-colors">
                                <x-heroicon-o-plus class="w-4 h-4 text-gray-400 dark:text-[#6b6760] flex-shrink-0" />
                                <div class="flex-1">{{ $this->newSubtaskForm }}</div>
                                <button type="submit"
                                    class="flex-shrink-0 px-3.5 py-1.5 rounded-lg text-[.79rem] font-medium bg-cyan-600 dark:bg-cyan-500 text-white hover:opacity-90 active:scale-95 transition-all whitespace-nowrap">
                                    Tambah
                                </button>
                            </form>
                        </div>

                    </div>{{-- end scrollable body --}}

                    {{-- ─── Tabs ─── --}}
                    <div class="border-t border-black/[.08] dark:border-white/[.07]"
                         x-data="{ activeTab: @entangle('activeTab') }">

                        {{-- Tab bar --}}
                        <div class="flex border-b border-black/[.07] dark:border-white/[.06]">
                            <button @click="activeTab = 'comments'"
                                class="relative flex-1 flex items-center justify-center gap-2 px-4 py-3 text-[.79rem] font-medium transition-colors"
                                :class="activeTab === 'comments'
                                    ? 'text-cyan-600 dark:text-cyan-400'
                                    : 'text-gray-400 dark:text-[#6b6760] hover:text-gray-700 dark:hover:text-gray-300'">
                                <x-heroicon-o-chat-bubble-left-right class="w-4 h-4" />
                                Komentar
                                @if($task->comments && $task->comments->count() > 0)
                                    <span class="px-1.5 py-0.5 rounded-full text-[.7rem]"
                                        :class="activeTab === 'comments'
                                            ? 'bg-cyan-100 dark:bg-cyan-900/30 text-cyan-700 dark:text-cyan-400'
                                            : 'bg-gray-100 dark:bg-white/[.06] text-gray-500 dark:text-gray-400'">
                                        {{ $task->comments->count() }}
                                    </span>
                                @endif
                                <span x-show="activeTab === 'comments'"
                                    class="absolute bottom-[-1px] left-4 right-4 h-0.5 rounded-t-full bg-cyan-600 dark:bg-cyan-400"></span>
                            </button>
                            <button @click="activeTab = 'activity'"
                                class="relative flex-1 flex items-center justify-center gap-2 px-4 py-3 text-[.79rem] font-medium transition-colors"
                                :class="activeTab === 'activity'
                                    ? 'text-cyan-600 dark:text-cyan-400'
                                    : 'text-gray-400 dark:text-[#6b6760] hover:text-gray-700 dark:hover:text-gray-300'">
                                <x-heroicon-o-clock class="w-4 h-4" />
                                Aktivitas
                                @if($activityLogs && $activityLogs->count() > 0)
                                    <span class="px-1.5 py-0.5 rounded-full text-[.7rem]"
                                        :class="activeTab === 'activity'
                                            ? 'bg-cyan-100 dark:bg-cyan-900/30 text-cyan-700 dark:text-cyan-400'
                                            : 'bg-gray-100 dark:bg-white/[.06] text-gray-500 dark:text-gray-400'">
                                        {{ $activityLogs->count() }}
                                    </span>
                                @endif
                                <span x-show="activeTab === 'activity'"
                                    class="absolute bottom-[-1px] left-4 right-4 h-0.5 rounded-t-full bg-cyan-600 dark:bg-cyan-400"></span>
                            </button>
                        </div>

                        {{-- Comments panel --}}
                        <div x-show="activeTab === 'comments'"
                             x-transition:enter="transition ease-out duration-200"
                             x-transition:enter-start="opacity-0 translate-x-1"
                             x-transition:enter-end="opacity-100 translate-x-0"
                             x-cloak
                             class="px-6 py-5 flex flex-col gap-4">

                            @if($task->comments && $task->comments->count() > 0)
                                <div class="flex flex-col gap-4 dt-stagger">
                                @foreach($task->comments->sortByDesc('created_at') as $comment)
                                    <div class="dt-fade-up flex gap-3">
                                        <div class="flex-shrink-0 flex items-center justify-center w-8 h-8 rounded-full bg-blue-500 dark:bg-blue-600 text-white text-[.71rem] font-bold">
                                            {{ strtoupper(substr($comment->user->name, 0, 1)) }}
                                        </div>
                                        <div class="flex-1 bg-gray-50 dark:bg-[#1a1a18] border border-black/[.07] dark:border-white/[.07] rounded-tr-xl rounded-br-xl rounded-bl-xl px-3.5 py-2.5 text-[.83rem] text-gray-600 dark:text-[#a8a49f] leading-relaxed">
                                            <div class="flex items-center gap-2 mb-1.5">
                                                <span class="font-semibold text-[.82rem] text-gray-800 dark:text-[#f5f4f0]">{{ $comment->user->name }}</span>
                                                <span class="text-[.73rem] text-gray-400 dark:text-[#6b6760]">{{ $comment->created_at->diffForHumans() }}</span>
                                            </div>
                                            {!! nl2br(e($comment->content)) !!}
                                        </div>
                                    </div>
                                @endforeach
                                </div>
                            @else
                                <div class="text-center py-6">
                                    <div class="flex items-center justify-center w-10 h-10 rounded-full bg-gray-100 dark:bg-white/[.05] mx-auto mb-3">
                                        <x-heroicon-o-chat-bubble-left-right class="w-5 h-5 text-gray-400 dark:text-[#6b6760]" />
                                    </div>
                                    <p class="text-[.88rem] font-medium text-gray-500 dark:text-[#a8a49f]">Belum ada komentar</p>
                                    <p class="text-[.8rem] text-gray-400 dark:text-[#6b6760]">Jadilah yang pertama memberikan komentar</p>
                                </div>
                            @endif

                            {{-- Comment form --}}
                            <form wire:submit="addComment" class="flex gap-3 pt-4 border-t border-black/[.07] dark:border-white/[.06]">
                                <div class="dt-comment-sender-ava flex-shrink-0 flex items-center justify-center w-8 h-8 rounded-full text-[.72rem] font-bold text-white">
                                    {{ strtoupper(substr(auth()->user()->name, 0, 1)) }}
                                </div>
                                <div class="flex-1 flex flex-col gap-2.5">
                                    {{ $this->commentForm }}
                                    <div>
                                        <button type="submit"
                                            class="flex items-center gap-1.5 px-4 py-2 rounded-lg text-[.8rem] font-medium bg-cyan-600 dark:bg-cyan-500 text-white hover:opacity-90 active:scale-95 transition-all">
                                            <x-heroicon-o-paper-airplane class="w-3.5 h-3.5" />
                                            Kirim
                                        </button>
                                    </div>
                                </div>
                            </form>
                        </div>

                        {{-- Activity panel --}}
                        <div x-show="activeTab === 'activity'"
                             x-transition:enter="transition ease-out duration-200"
                             x-transition:enter-start="opacity-0 translate-x-1"
                             x-transition:enter-end="opacity-100 translate-x-0"
                             x-cloak
                             class="px-6 py-5">

                            @if($activityLogs && $activityLogs->count() > 0)
                                <div class="dt-timeline relative flex flex-col dt-stagger">
                                @foreach($activityLogs as $activity)
                                            <div class="dt-fade-up flex gap-3.5 pb-5">
                                                {{-- Dot --}}
                                                <div class="flex-shrink-0 flex items-center justify-center w-[30px] h-[30px] rounded-full z-[1] text-[.69rem] font-bold text-white"
                                                    style="background: {{ 
                                                        str_contains(strtolower($activity->description), 'membuat') ? 'linear-gradient(135deg,#2e7d55,#3a8a5f)' :
                                    (str_contains(strtolower($activity->description), 'status') ? 'linear-gradient(135deg,#2e6494,#3d6f8f)' :
                                        (str_contains(strtolower($activity->description), 'menghapus') ? 'linear-gradient(135deg,#a33232,#a04040)' :
                                            (str_contains(strtolower($activity->description), 'selesai') ? 'linear-gradient(135deg,#0e7490,#0891b2)' :
                                                'linear-gradient(135deg,#555,#333)')))
                                                    }}">
                                                    {{ $activity->causer ? strtoupper(substr($activity->causer->name, 0, 1)) : 'S' }}
                                                </div>
                                                {{-- Body --}}
                                                <div class="flex-1 bg-gray-50 dark:bg-[#1a1a18] border border-black/[.07] dark:border-white/[.07] rounded-lg px-3.5 py-2.5 text-[.82rem]">
                                                    <span class="font-semibold text-gray-800 dark:text-[#f5f4f0]">{{ $activity->causer->name ?? 'System' }}</span>
                                                    <span class="text-gray-500 dark:text-[#a8a49f] ml-1">{{ $activity->description }}</span>
                                                    <div class="flex items-center gap-1.5 mt-1.5 text-[.73rem] text-gray-400 dark:text-[#6b6760]">
                                                        <x-heroicon-o-clock class="w-3 h-3" />
                                                        {{ $activity->created_at->diffForHumans() }} &nbsp;·&nbsp; {{ $activity->created_at->format('d M Y, H:i') }}
                                                    </div>
                                                    @if($activity->properties && ($activity->properties->get('old') || $activity->properties->get('attributes')))
                                                        <div class="mt-2 flex flex-col gap-1">
                                                            @if($activity->properties->get('old'))
                                                                <div class="text-[.73rem] flex flex-wrap gap-1 items-center">
                                                                    @foreach($activity->properties->get('old') as $k => $v)
                                                                        <span class="bg-red-50 dark:bg-red-900/20 text-red-600 dark:text-red-400 px-1.5 py-0.5 rounded">{{ ucfirst($k) }}: {{ is_array($v) ? json_encode($v) : $v }}</span>
                                                                    @endforeach
                                                                    <span class="text-gray-400">→</span>
                                                                </div>
                                                            @endif
                                                            @if($activity->properties->get('attributes'))
                                                                <div class="text-[.73rem] flex flex-wrap gap-1">
                                                                    @foreach($activity->properties->get('attributes') as $k => $v)
                                                                        <span class="bg-emerald-50 dark:bg-emerald-900/20 text-emerald-600 dark:text-emerald-400 px-1.5 py-0.5 rounded">{{ ucfirst($k) }}: {{ is_array($v) ? json_encode($v) : $v }}</span>
                                                                    @endforeach
                                                                </div>
                                                            @endif
                                                        </div>
                                                    @endif
                                                </div>
                                            </div>
                                @endforeach
                                </div>
                            @else
                                <div class="text-center py-6">
                                    <div class="flex items-center justify-center w-10 h-10 rounded-full bg-gray-100 dark:bg-white/[.05] mx-auto mb-3">
                                        <x-heroicon-o-clock class="w-5 h-5 text-gray-400 dark:text-[#6b6760]" />
                                    </div>
                                    <p class="text-[.88rem] font-medium text-gray-500 dark:text-[#a8a49f]">Belum ada aktivitas</p>
                                    <p class="text-[.8rem] text-gray-400 dark:text-[#6b6760]">Riwayat perubahan akan muncul di sini</p>
                                </div>
                            @endif
                        </div>

                    </div>{{-- end tabs --}}

                </div>{{-- end shell --}}
        @endif
        <x-filament-actions::modals />
    </x-filament::modal>
</div>