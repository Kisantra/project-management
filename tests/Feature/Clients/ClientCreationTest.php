<?php

namespace Tests\Feature\Clients;

use App\Filament\Resources\ClientResource\Pages\CreateClient;
use App\Models\Client;
use App\Models\User;
use Filament\Facades\Filament;
use Filament\Forms\Components\Select;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/** Creating a client with an empty Status PKP (Watchpost error #16, events from 19 November). */
class ClientCreationTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();

        config(['broadcasting.default' => 'null']);
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        $user = User::forceCreate([
            'name' => 'Uji Tambah Klien',
            'email' => 'uji-tambah-klien-' . uniqid() . '@example.test',
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

    public function test_create_page_asks_for_status_pkp_instead_of_failing_in_the_database(): void
    {
        $name = 'PT UJI PKP KOSONG ' . uniqid();

        Livewire::test(CreateClient::class)
            ->fillForm($this->badan($name) + ['pkp_status' => null])
            ->call('create')
            ->assertHasFormErrors(['pkp_status' => 'required']);

        $this->assertDatabaseMissing('clients', ['name' => $name]);
    }

    public function test_status_pkp_has_no_empty_option(): void
    {
        Livewire::test(CreateClient::class)
            ->assertFormFieldExists('pkp_status', fn (Select $field): bool => $field->isRequired() && ! $field->canSelectPlaceholder())
            ->assertFormSet(['pkp_status' => 'Non-PKP']);
    }

    public function test_create_page_still_saves_a_client_as_non_pkp_by_default(): void
    {
        $name = 'PT UJI PKP DEFAULT ' . uniqid();

        Livewire::test(CreateClient::class)
            ->fillForm($this->badan($name))
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertSame('Non-PKP', Client::where('name', $name)->sole()->pkp_status);
    }

    /** The fields from the failed production insert, without a contact person. */
    private function badan(string $name): array
    {
        return [
            'client_type' => 'Badan',
            'client_subtype' => 'Perseroan Terbatas (PT)',
            'name' => $name,
            'email' => 'uji-pkp-' . uniqid() . '@example.test',
            'adress' => 'Jln. Uji No. 1, Banjarbaru',
            'status' => 'Active',
            'NPWP' => '28.703.942.4-732.000',
            'klu' => '09900',
            'contacts' => [],
        ];
    }
}
