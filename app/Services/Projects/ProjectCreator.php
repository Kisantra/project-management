<?php

namespace App\Services\Projects;

use App\Filament\Resources\ProjectResource;
use App\Models\DailyTask;
use App\Models\Project;
use App\Models\User;
use Carbon\Carbon;

/**
 * Langkah membuat proyek yang sama untuk form "Create Project" dan pemrosesan
 * Klien Masuk. Relasi dari form (tahapan SOP, anggota, departemen) disimpan
 * oleh Filament; kelas ini menangani kolom proyek dan langkah sesudahnya.
 */
class ProjectCreator
{
    /** Kolom proyek dari isian form proyek. */
    public static function attributes(array $data): array
    {
        return [
            'name' => $data['name'],
            'client_id' => $data['client_id'],
            'pic_id' => $data['pic_id'] ?? null,
            'type' => $data['type'],
            'priority' => $data['priority'] ?? 'normal',
            'sop_id' => $data['sop_id'] ?? null,
            'due_date' => isset($data['due_date']) ? Carbon::parse($data['due_date'])->format('Y-m-d') : null,
            'description' => $data['description'] ?? null,
        ];
    }

    /**
     * Setelah proyek dan relasi form tersimpan: anggota wajib (direktur,
     * super-admin), notifikasi tim, dan DailyTask berisi subtugas per tahapan.
     *
     * @param  array<int, array{user_id: int}>  $memberData  anggota dari state form (repeater relasi tidak ikut di sini)
     * @return array{assigned: int, roles: string}
     */
    public function afterCreate(Project $project, array $memberData = []): array
    {
        // Get all users with required roles (directors, project managers, and verificators)
        $requiredRoleUsers = User::whereHas('roles', function ($query) {
            $query->whereIn('name', ['direktur', 'super-admin']);
        })->get();

        // Get existing user IDs from form data
        $existingUserIds = collect($memberData)->pluck('user_id')->toArray();

        // Add users with required roles to userProject data if not already included
        foreach ($requiredRoleUsers as $user) {
            if (! in_array($user->id, $existingUserIds)) {
                $memberData[] = [
                    'user_id' => $user->id,
                ];
            }
        }

        // Create user project relationships
        foreach ($memberData as $userData) {
            $project->userProject()->create([
                'user_id' => $userData['user_id'],
            ]);
        }

        // Log the assignment
        \Log::info('Project assigned to users', [
            'project_id' => $project->id,
            'project_name' => $project->name,
            'assigned_users' => collect($memberData)->pluck('user_id')->toArray(),
            'auto_assigned_roles' => ['direktur', 'project-manager', 'verificator'],
        ]);

        // Send notifications
        $client = $project->client;
        ProjectResource::sendProjectNotifications(
            "New Project Assigned",
            sprintf(
                "<strong>Client:</strong> %s<br><strong>Project:</strong> %s<br><strong>Type:</strong> %s<br><strong>Due Date:</strong> %s<br><strong>Created by:</strong> %s",
                $client->name,
                $project->name,
                ucwords($project->type),
                $project->due_date->format('d M Y'),
                auth()->user()->name
            ),
            $project,
            'success',
            'View Project'
        );

        // Auto-create a DailyTask for this project
        $memberIds = collect($memberData)->pluck('user_id')->unique()->toArray();

        if (! empty($memberIds)) {
            $dailyTask = DailyTask::create([
                'title' => "{$project->name}",
                'description' => "{$project->name} - Client: {$client->name}",
                'project_id' => $project->id,
                'created_by' => auth()->id(),
                'priority' => $project->priority ?? 'normal',
                'status' => 'pending',
                'task_date' => $project->due_date,
            ]);

            $dailyTask->assignedUsers()->sync($memberIds);

            // Create subtasks from project steps
            $project->load('steps');
            foreach ($project->steps->sortBy('order') as $step) {
                $dailyTask->subtasks()->create([
                    'title' => $step->name,
                    'status' => 'pending',
                ]);
            }
        }

        return [
            'assigned' => count($memberData),
            'roles' => $requiredRoleUsers->pluck('roles.*.name')->flatten()->unique()->implode(', '),
        ];
    }
}
