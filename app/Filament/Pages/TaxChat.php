<?php

namespace App\Filament\Pages;

use Filament\Pages\Page;
use App\Neuron\TaxBotAgent;
use NeuronAI\Chat\Messages\UserMessage;
use Filament\Notifications\Notification;

class TaxChat extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-chat-bubble-left-right';
    
    protected static ?string $navigationLabel = 'Tax Chat Assistant';
    
    protected static ?string $navigationGroup = 'Tax Management';
    protected static ?int $navigationSort = 3;
    protected static ?string $title = 'Tax Chat Assistant';

    protected static string $view = 'filament.pages.tax-chat';

    public string $message = '';
    
    public array $chatHistory = [];

    
    /**
     * Hidden from the sidebar on purpose; the page stays reachable by URL
     * for anyone with the tax-chat permission (see canAccess()).
     */
    public static function shouldRegisterNavigation(): bool
    {
        return false;
    }
    
    public static function canAccess(): bool
    {
        return auth()->user()->can('tax-chat.*');
    }

    // Tambahkan computed property untuk check apakah message kosong
    public function getIsMessageEmptyProperty(): bool
    {
        return empty(trim($this->message));
    }

    public function mount(): void
    {
        // Initialize chat dengan greeting
        $this->chatHistory[] = [
            'role' => 'assistant',
            'content' => 'Halo! Saya TaxBot, asisten pajak Anda. Bagaimana saya bisa membantu Anda hari ini?',
            'timestamp' => now()->format('H:i'),
        ];
    }

    public function sendMessage(): void
    {
        if (empty(trim($this->message))) {
            return;
        }

        // Tambahkan pesan user ke history
        $this->chatHistory[] = [
            'role' => 'user',
            'content' => $this->message,
            'timestamp' => now()->format('H:i'),
        ];

        try {
            // Kirim pesan ke TaxBot Agent
            $agent = TaxBotAgent::make();
            $response = $agent->chat(new UserMessage($this->message));

            // Tambahkan response ke history
            $this->chatHistory[] = [
                'role' => 'assistant',
                'content' => $response->getContent(),
                'timestamp' => now()->format('H:i'),
            ];

        } catch (\Exception $e) {
            Notification::make()
                ->title('Error')
                ->danger()
                ->body('Terjadi kesalahan: ' . $e->getMessage())
                ->send();

            $this->chatHistory[] = [
                'role' => 'assistant',
                'content' => 'Maaf, terjadi kesalahan saat memproses permintaan Anda. Silakan coba lagi.',
                'timestamp' => now()->format('H:i'),
            ];
        }

        // Reset message input
        $this->message = '';
    }

    public function clearChat(): void
    {
        $this->chatHistory = [];
        $this->mount(); // Re-initialize dengan greeting
        
        Notification::make()
            ->title('Chat berhasil dibersihkan')
            ->success()
            ->send();
    }
}