<?php

namespace App\Services\ClientManagement;

use App\Models\ClientIntake;
use App\Models\User;
use Filament\Notifications\Actions\Action;
use Filament\Notifications\Notification;
use Illuminate\Support\Collection;

/**
 * Notifikasi database Filament untuk klien masuk dari Client Management.
 * Penerima: role di services.client_management.notify_roles.
 */
class IntakeNotifier
{
    /** @return Collection<int, User> */
    public function recipients(): Collection
    {
        $roles = (array) config('services.client_management.notify_roles', []);

        // whereHas + whereIn: role yang tidak ada (mis. "manager") diabaikan,
        // tidak seperti User::role() yang melempar RoleDoesNotExist.
        return User::query()
            ->where('status', 'active')
            ->whereHas('roles', fn ($q) => $q->whereIn('name', $roles))
            ->get();
    }

    public function received(ClientIntake $intake): void
    {
        $body = $intake->summaryLine();

        if ($suggested = $intake->suggestedClient) {
            $body .= " Mungkin klien lama: {$suggested->name}.";
        }

        $this->send(
            Notification::make()
                ->title('Klien masuk dari Client Management')
                ->body($body)
                ->icon('heroicon-o-inbox-arrow-down')
                ->iconColor('info')
                ->actions($this->actions($intake, 'Cek & proses')),
        );
    }

    /** Lead yang sempat mundur/ditutup kembali ke Deal di CM. */
    public function reactivated(ClientIntake $intake): void
    {
        $next = $intake->isCompleted()
            ? 'Klien dan proyeknya sudah dibuat sebelumnya.'
            : 'Kembali ke daftar Klien Masuk, menunggu dicek.';

        $this->send(
            Notification::make()
                ->title('Deal aktif lagi di Client Management')
                ->body($intake->summaryLine() . ' ' . $next)
                ->icon('heroicon-o-arrow-uturn-right')
                ->iconColor('info')
                ->actions($this->actions($intake, $intake->isCompleted() ? 'Lihat' : 'Cek & proses')),
        );
    }

    public function withdrawn(ClientIntake $intake): void
    {
        $body = filled($intake->withdrawn_detail) ? "{$intake->name} — {$intake->withdrawn_detail}." : "{$intake->name}.";

        if (filled($intake->withdrawn_note)) {
            $body .= ' Catatan: ' . $intake->withdrawn_note;
        }

        $body .= $intake->isCompleted()
            ? ' Klien dan proyeknya tetap ada di PM; tinjau apakah perlu dihentikan.'
            : ' Dipindahkan ke tab "Batal di Client Management".';

        $this->send(
            Notification::make()
                ->title('Deal dibatalkan di Client Management')
                ->body($body)
                ->icon('heroicon-o-x-circle')
                ->iconColor('warning')
                ->actions($this->actions($intake, 'Lihat')),
        );
    }

    protected function actions(ClientIntake $intake, string $label): array
    {
        $actions = [
            Action::make('open')
                ->label($label)
                ->url($intake->reviewUrl())
                ->button()
                ->markAsRead(),
        ];

        if (filled($intake->lead_url)) {
            $actions[] = Action::make('lead')
                ->label('Lihat lead di Client Management')
                ->url($intake->lead_url, shouldOpenInNewTab: true)
                ->link();
        }

        return $actions;
    }

    protected function send(Notification $notification): void
    {
        $users = $this->recipients();

        if ($users->isEmpty()) {
            return;
        }

        $notification->sendToDatabase($users);

        // Toast realtime lewat Reverb, seperti notifikasi lain di PM. Gagal
        // broadcast tidak boleh menggagalkan panggilan API dari CM.
        try {
            $notification->broadcast($users);
        } catch (\Throwable $e) {
            report($e);
        }
    }
}
