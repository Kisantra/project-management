<?php

namespace App\Livewire\Projects\Components;

use Livewire\Component;
use App\Models\Project;
use App\Models\User;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Route;

class ProjectMember extends Component
{
    public Project $project;
    public $search = '';
    public $selectedRole = null;

    public function mount(Project $project)
    {
        $this->project = $project;
    }

    public function getUsersProperty()
    {
        return $this->project->userProject()
            ->with(['user'])
            ->get()
            ->unique('user_id') // the pivot can hold the same user twice; show each person once
            ->values()
            ->map(function ($userProject) {
                $user = $userProject->user;
                return [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'avatar' => "https://ui-avatars.com/api/?name=" . urlencode($user->name) . "&color=7F9CF5&background=EBF4FF",
                    'comments_count' => $user->comments()
                        ->whereHasMorph('commentable', ['App\Models\Task'], function ($query) {
                            $query->whereIn('project_step_id', $this->project->steps->pluck('id'));
                        })->count(),
                    'documents_count' => $user->submittedDocuments()
                        ->whereHas('requiredDocument.projectStep', function ($query) {
                            $query->where('project_id', $this->project->id);
                        })->count(),
                    'last_active' => $user->comments()
                        ->whereHasMorph('commentable', ['App\Models\Task'], function ($query) {
                            $query->whereIn('project_step_id', $this->project->steps->pluck('id'));
                        })
                        ->latest()
                        ->first()?->created_at?->locale('id')->diffForHumans(),
                ];
            });
    }

    public function getAvailableUsersProperty()
    {
        if (auth()->user()->hasRole('staff')) {
            return collect();
        }

        return User::query()
            ->whereDoesntHave('userProjects', function ($query) {
                $query->where('project_id', $this->project->id);
            })
            ->when(!auth()->user()->hasRole('super-admin'), function ($query) {
                $query->whereHas('userClients', function ($q) {
                    $q->whereIn('client_id', auth()->user()->userClients->pluck('client_id'));
                });
            })
            ->when($this->search, function ($query) {
                $query->where(function ($q) {
                    $q->where('name', 'like', "%{$this->search}%")
                        ->orWhere('email', 'like', "%{$this->search}%");
                });
            })
            ->get();
    }

    public function addUserToProject($userId)
    {
        if (auth()->user()->hasRole('staff')) {
            return;
        }

        try {
            $this->project->userProject()->create([
                'user_id' => $userId
            ]);

            // Dapatkan detail user dan project
            $addedUser = User::find($userId);
            $client = $this->project->client;

            // Buat notifikasi untuk user yang ditambahkan
            $notification = Notification::make()
                ->title('Penugasan Proyek')
                ->body(sprintf(
                    "<strong>Klien:</strong> %s<br><strong>Proyek:</strong> %s<br><strong>Ditugaskan oleh:</strong> %s",
                    $client->name,
                    $this->project->name,
                    auth()->user()->name
                ))
                ->success();

            // Kirim ke user yang baru ditambahkan
            $notification->sendToDatabase($addedUser)->broadcast($addedUser)->persistent();

            // Notifikasi UI untuk user saat ini
            Notification::make()
                ->title('Anggota berhasil ditambahkan')
                ->success()
                ->send();

            $this->dispatch('refresh');
            $this->dispatch('close-modal');

        } catch (\Exception $e) {
            Notification::make()
                ->title('Gagal menambahkan anggota')
                ->danger()
                ->send();
        }
    }

    public function removeMember($userId)
    {
        if (auth()->user()->hasRole('staff')) {
            return;
        }

        try {
            // Dapatkan detail user sebelum dihapus
            $removedUser = User::find($userId);
            $client = $this->project->client;

            $this->project->userProject()
                ->where('user_id', $userId)
                ->delete();

            // Buat notifikasi untuk user yang dihapus
            $notification = Notification::make()
                ->title('Penghapusan dari Proyek')
                ->body(sprintf(
                    "<strong>Klien:</strong> %s<br><strong>Proyek:</strong> %s<br><strong>Dihapus oleh:</strong> %s<br><strong>Tanggal Penghapusan:</strong> %s",
                    $client->name,
                    $this->project->name,
                    auth()->user()->name,
                    now()->format('d M Y H:i')
                ))
                ->warning();

            // Kirim ke user yang dihapus
            $notification->sendToDatabase($removedUser)->broadcast($removedUser)->persistent();

            // Notifikasi UI untuk user saat ini
            Notification::make()
                ->title('Anggota berhasil dihapus')
                ->success()
                ->send();

            $this->dispatch('refresh');

        } catch (\Exception $e) {
            Notification::make()
                ->title('Gagal menghapus anggota')
                ->danger()
                ->send();
        }
    }

    public function render()
    {
        return view('livewire.projects.components.project-member', [
            'users' => $this->users,
            'availableUsers' => $this->availableUsers
        ]);
    }
}