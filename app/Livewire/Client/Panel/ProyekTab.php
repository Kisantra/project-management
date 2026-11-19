<?php

namespace App\Livewire\Client\Panel;

use App\Livewire\Client\Panel\Concerns\UsesCurrentClient;
use App\Models\Project;
use Filament\Notifications\Notification;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Livewire\Component;

class ProyekTab extends Component
{
    use UsesCurrentClient;

    public $selectedProjectId;

    public function mount()
    {
        // Open the newest project first.
        $this->selectedProjectId = $this->projects->first()?->id;
    }

    public function getProjectsProperty(): Collection
    {
        if (! $this->selectedClientId) {
            return collect();
        }

        return Project::query()
            ->where('client_id', $this->selectedClientId)
            ->with(['client:id,name', 'pic:id,name', 'statusRecord'])
            ->orderBy('created_at', 'desc')
            ->get()
            ->each(function (Project $project) {
                $project->deliverable_files = $project->deliverable_files ?? [];
            });
    }

    public function getActiveProjectsCountProperty()
    {
        // Anything not in the done or closed bucket counts as "active"
        return $this->projects
            ->filter(fn ($p) => ! in_array($p->statusRecord?->category, ['done', 'closed'], true))
            ->count();
    }

    public function getCompletedProjectsCountProperty()
    {
        return $this->projects
            ->filter(fn ($p) => $p->statusRecord?->category === 'done')
            ->count();
    }

    public function downloadDeliverable($projectId, $fileIndex)
    {
        $project = Project::query()
            ->where('client_id', $this->selectedClientId)
            ->findOrFail($projectId);

        $deliverableFiles = is_array($project->deliverable_files) ? $project->deliverable_files : [];

        if (isset($deliverableFiles[$fileIndex])) {
            $fileData = $deliverableFiles[$fileIndex];
            $filePath = is_string($fileData) ? $fileData : ($fileData['path'] ?? null);

            if ($filePath && Storage::disk('public')->exists($filePath)) {
                return Storage::disk('public')->download($filePath, is_array($fileData) ? ($fileData['name'] ?? null) : null);
            }
        }

        Notification::make()
            ->title('File tidak ditemukan')
            ->body('Berkas hasil proyek ini tidak ada di server. Hubungi tim Kisantra.')
            ->warning()
            ->send();
    }

    public function render()
    {
        return view('livewire.client.panel.proyek-tab');
    }
}
