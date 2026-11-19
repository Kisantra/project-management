<?php

namespace App\Livewire\Client\Panel;

use App\Models\ChatThread;
use App\Models\Client;
use App\Services\ChatService;
use Filament\Notifications\Notification;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Locked;
use Livewire\Attributes\On;
use Livewire\Component;

class ChatPanel extends Component
{
    public EloquentCollection $clients;

    #[Locked]
    public bool $showAllClients = false;

    public bool $compact = false;

    #[Locked]
    public ?int $selectedClientId = null;

    public ?int $newThreadClientId = null;

    public ?int $activeThreadId = null;

    public string $messageBody = '';

    public string $newThreadTitle = '';

    public bool $showNewThreadForm = false;

    public function mount(bool $showAllClients = false): void
    {
        $this->showAllClients = $showAllClients;
        $this->loadClients();

        if ($this->showAllClients) {
            $this->newThreadClientId = $this->clients->first()?->id;
        } else {
            $sessionClientId = session('client_panel_selected_client_id');
            if ($sessionClientId && $this->clients->contains('id', (int) $sessionClientId)) {
                $this->selectedClientId = (int) $sessionClientId;
            }

            if (!$this->selectedClientId && $this->clients->isNotEmpty()) {
                $this->selectedClientId = $this->clients->first()->id;
            }
        }

        $this->selectFirstThread();
    }

    #[On('client-switched')]
    public function selectClient(int $clientId): void
    {
        if ($this->showAllClients) {
            return;
        }

        if (!$this->clients->contains('id', $clientId)) {
            return;
        }

        $this->selectedClientId = $clientId;
        $this->activeThreadId = null;
        $this->messageBody = '';
        $this->showNewThreadForm = false;

        $this->selectFirstThread();
    }

    public function toggleNewThreadForm(): void
    {
        $this->showNewThreadForm = !$this->showNewThreadForm;
    }

    public function createThread(): void
    {
        $clientId = $this->showAllClients ? $this->newThreadClientId : $this->selectedClientId;

        if (!$clientId) {
            return;
        }

        $this->validate([
            'newThreadClientId' => [$this->showAllClients ? 'required' : 'nullable', 'integer'],
            'newThreadTitle' => ['nullable', 'string', 'max:120'],
        ]);

        try {
            $thread = $this->chat()->createThread(
                auth()->user(),
                $clientId,
                trim($this->newThreadTitle) ?: null,
            );

            $this->activeThreadId = $thread->id;
            $this->newThreadTitle = '';
            $this->showNewThreadForm = false;
            $this->dispatch('chat-thread-selected', threadId: $thread->id);
        } catch (AuthorizationException|ValidationException $exception) {
            $this->notifyError($exception->getMessage());
        }
    }

    public function selectThread(int $threadId): void
    {
        try {
            $this->chat()->authorizeThreadAccess(auth()->user(), $threadId);
            $this->activeThreadId = $threadId;
            $this->messageBody = '';
            $this->chat()->markAsRead(auth()->user(), $threadId);
            $this->dispatch('chat-thread-selected', threadId: $threadId);
        } catch (AuthorizationException $exception) {
            $this->notifyError($exception->getMessage());
        }
    }

    public function sendMessage(): void
    {
        if (!$this->activeThreadId) {
            return;
        }

        $this->validate([
            'messageBody' => ['nullable', 'string', 'max:5000'],
        ]);

        try {
            $this->chat()->sendMessage(auth()->user(), $this->activeThreadId, $this->messageBody);
            $this->messageBody = '';
            $this->chat()->markAsRead(auth()->user(), $this->activeThreadId);
            $this->dispatch('chat-message-posted');
        } catch (AuthorizationException|ValidationException $exception) {
            $this->notifyError($exception->getMessage());
        }
    }

    public function handleBroadcastedMessage(?int $messageId = null): void
    {
        if ($this->activeThreadId) {
            $this->chat()->markAsRead(auth()->user(), $this->activeThreadId);
        }
    }

    public function render()
    {
        return view('livewire.client.panel.chat-panel', [
            'hasClients' => $this->clients->isNotEmpty(),
            'selectedClient' => $this->selectedClient,
            'threads' => $this->threads,
            'activeThread' => $this->activeThread,
            'messages' => $this->messages,
        ]);
    }

    public function getSelectedClientProperty(): ?Client
    {
        if (!$this->selectedClientId) {
            return null;
        }

        return $this->clients->firstWhere('id', $this->selectedClientId);
    }

    public function getThreadsProperty(): Collection
    {
        if ($this->showAllClients) {
            return $this->chat()->threadsForInbox(auth()->user());
        }

        if (!$this->selectedClientId) {
            return collect();
        }

        try {
            return $this->chat()->threadsForClient(auth()->user(), $this->selectedClientId);
        } catch (AuthorizationException) {
            return collect();
        }
    }

    public function getActiveThreadProperty(): ?ChatThread
    {
        if (!$this->activeThreadId) {
            return null;
        }

        return ChatThread::query()
            ->forUser(auth()->user())
            ->with([
                'client:id,name,logo',
                'participants.user:id,name,email,avatar_url',
            ])
            ->find($this->activeThreadId);
    }

    public function getMessagesProperty(): Collection
    {
        if (!$this->activeThreadId) {
            return collect();
        }

        return $this->activeThread
            ? $this->activeThread->messages()
                ->with('user:id,name,email,avatar_url')
                ->oldest()
                ->get()
            : collect();
    }

    private function loadClients(): void
    {
        $this->clients = $this->chat()->accessibleClients(auth()->user());
    }

    private function selectFirstThread(): void
    {
        $thread = $this->threads->first();

        if ($thread) {
            $this->activeThreadId = $thread->id;
            $this->dispatch('chat-thread-selected', threadId: $thread->id);
        }
    }

    private function chat(): ChatService
    {
        return app(ChatService::class);
    }

    private function notifyError(string $message): void
    {
        Notification::make()
            ->title('Chat belum bisa diproses')
            ->body($message)
            ->danger()
            ->send();
    }
}
