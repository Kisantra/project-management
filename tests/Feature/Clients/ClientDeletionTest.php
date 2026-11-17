<?php

namespace Tests\Feature\Clients;

use App\Filament\Resources\ClientResource\Pages\EditClient;
use App\Models\Client;
use App\Models\Project;
use App\Models\User;
use Filament\Actions\DeleteAction;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/** Deleting a client that still has projects (Watchpost error #16). */
class ClientDeletionTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();

        config(['broadcasting.default' => 'null']);
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        $user = User::forceCreate([
            'name' => 'Uji Hapus Klien',
            'email' => 'uji-hapus-klien-' . uniqid() . '@example.test',
            'password' => bcrypt('rahasia'),
            'status' => 'active',
        ]);
        Role::findOrCreate('super-admin', 'web');
        $user->assignRole('super-admin');
        $this->actingAs($user);
    }

    protected function tearDown(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        parent::tearDown();
    }

    public function test_edit_page_refuses_to_delete_a_client_with_projects(): void
    {
        $client = $this->client('PT UJI BERPROYEK');
        $this->project($client);

        Livewire::test(EditClient::class, ['record' => $client->getRouteKey()])
            ->callAction(DeleteAction::class)
            ->assertNotified('Klien tidak bisa dihapus');

        $this->assertModelExists($client);
    }

    public function test_edit_page_still_deletes_a_client_without_projects(): void
    {
        $client = $this->client('PT UJI TANPA PROYEK');

        Livewire::test(EditClient::class, ['record' => $client->getRouteKey()])
            ->callAction(DeleteAction::class);

        $this->assertModelMissing($client);
    }

    private function client(string $name): Client
    {
        return Client::forceCreate(['name' => $name . ' ' . uniqid(), 'status' => 'Active']);
    }

    private function project(Client $client): Project
    {
        return Project::withoutEvents(fn () => Project::forceCreate([
            'client_id' => $client->id,
            'name' => 'Uji Proyek ' . uniqid(),
            'type' => 'single',
            'due_date' => '2031-03-01',
            'status' => 'in_progress',
        ]));
    }
}
