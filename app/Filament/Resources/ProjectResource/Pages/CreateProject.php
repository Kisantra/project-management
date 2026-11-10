<?php

namespace App\Filament\Resources\ProjectResource\Pages;

use App\Filament\Resources\ProjectResource;
use App\Services\Projects\ProjectCreator;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;

class CreateProject extends CreateRecord
{
    protected static string $resource = ProjectResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        // Store user project data for use after creation
        $this->userProjectData = $data['userProject'] ?? [];

        // Only keep the fields that belong to the projects table
        return ProjectCreator::attributes($data);
    }

    protected function afterCreate(): void
    {
        // Same steps the Client Management intake runs after creating its project
        $result = app(ProjectCreator::class)->afterCreate($this->record, $this->userProjectData);

        // Show success notification with assignment details
        Notification::make()
            ->title('Project Created Successfully')
            ->body("Project has been assigned to {$result['assigned']} users with roles: {$result['roles']}")
            ->success()
            ->duration(5000)
            ->send();
    }

    protected $userProjectData = [];
}
