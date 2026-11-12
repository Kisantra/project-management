<?php

namespace Tests\Feature\Clients;

use App\Filament\Resources\ClientGroupResource;
use App\Filament\Resources\ClientGroupResource\Pages\ListClientGroups;
use App\Filament\Resources\ClientGroupResource\Pages\ViewClientGroup;
use App\Filament\Resources\ClientGroupResource\RelationManagers\ClientsRelationManager;
use App\Livewire\Clients\GroupList;
use App\Models\Client;
use App\Models\ClientGroup;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/** Client groups redesign (docs/mockups/client-groups-redesign.html). */
class ClientGroupsRedesignTest extends TestCase
{
    use DatabaseTransactions;

    private ClientGroup $group;

    protected function setUp(): void
    {
        parent::setUp();

        config(['broadcasting.default' => 'null']);
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        $user = User::forceCreate([
            'name' => 'Uji Grup',
            'email' => 'uji-grup-' . uniqid() . '@example.test',
            'password' => bcrypt('rahasia'),
            'status' => 'active',
        ]);
        Role::findOrCreate('super-admin', 'web');
        $user->assignRole('super-admin');
        $this->actingAs($user);

        $this->group = ClientGroup::create(['name' => 'Grup Uji ' . uniqid(), 'status' => 'active', 'contact_name' => 'Rina Uji']);
        foreach (['Alfa', 'Bravo', 'Charlie', 'Delta', 'Echo'] as $i => $name) {
            Client::forceCreate(['name' => "PT UJI {$name} " . uniqid(), 'status' => $i === 4 ? 'Inactive' : 'Active', 'group_id' => $this->group->id]);
        }
    }

    protected function tearDown(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        parent::tearDown();
    }

    public function test_list_shows_a_card_per_group_with_a_member_preview(): void
    {
        Livewire::test(GroupList::class)
            ->assertSee($this->group->name)
            ->assertSee('Kontak Rina Uji')
            ->assertSee('Perusahaan · 5')
            ->assertSee('+1 perusahaan lainnya')
            ->assertSee('4 dari 5 aktif')
            ->assertSee('Buat grup baru')
            ->assertDontSee('ui-avatars.com');
    }

    public function test_search_matches_member_names_and_the_rail_filters_status(): void
    {
        $member = $this->group->clients()->orderBy('name')->first();

        Livewire::test(GroupList::class)
            ->set('search', mb_substr($member->name, 0, 14))
            ->assertSee($this->group->name)
            ->call('setStatus', 'inactive')
            ->assertDontSee($this->group->name)
            ->assertSee('Tidak ada grup yang cocok')
            ->call('setStatus', 'bogus')
            ->assertSet('statusFilter', '');
    }

    public function test_heading_counts_link_to_clients_without_a_group(): void
    {
        Client::forceCreate(['name' => 'PT UJI TANPA GRUP ' . uniqid(), 'status' => 'Active']);

        $subheading = (string) Livewire::test(ListClientGroups::class)->instance()->getSubheading();

        $this->assertStringContainsString('grup aktif', $subheading);
        $this->assertStringContainsString('klien belum punya grup', $subheading);
        $this->assertStringContainsString('/clients?group=group', $subheading);
    }

    public function test_detail_shows_group_info_and_an_editable_member_table(): void
    {
        $this->get(ClientGroupResource::getUrl('view', ['record' => $this->group]))
            ->assertOk()
            ->assertSee('Informasi grup')
            ->assertSee('Perusahaan dalam grup')
            ->assertSee('5</b> perusahaan', false)
            ->assertSee('Tambah client');

        // Member tiles are local initials; client names are not sent to an avatar service.
        Livewire::test(ClientsRelationManager::class, ['ownerRecord' => $this->group, 'pageClass' => ViewClientGroup::class])
            ->assertDontSee('ui-avatars.com');
    }

    public function test_adding_offers_only_clients_without_a_group_and_changes_refresh_the_heading(): void
    {
        $other = ClientGroup::create(['name' => 'Grup Lain ' . uniqid(), 'status' => 'active']);
        $elsewhere = Client::forceCreate(['name' => 'PT UJI DI GRUP LAIN ' . uniqid(), 'status' => 'Active', 'group_id' => $other->id]);
        $free = Client::forceCreate(['name' => 'PT UJI BEBAS ' . uniqid(), 'status' => 'Active']);

        $table = Livewire::test(ClientsRelationManager::class, ['ownerRecord' => $this->group, 'pageClass' => ViewClientGroup::class])
            ->assertTableActionVisible('associate')
            ->mountTableAction('associate');

        $select = $table->instance()->getMountedTableActionForm()->getComponent(fn ($component) => $component instanceof \Filament\Forms\Components\Select);
        $this->assertArrayNotHasKey($elsewhere->id, $select->getSearchResults($elsewhere->name), 'A client of another group is not moved from here');
        $this->assertArrayHasKey($free->id, $select->getSearchResults($free->name));

        $table->set('mountedTableActionsData.0.recordId', $free->id)
            ->callMountedTableAction()
            ->assertHasNoTableActionErrors()
            ->assertDispatched('group-members-changed');
        $this->assertSame($this->group->id, $free->fresh()->group_id);

        Livewire::test(ClientsRelationManager::class, ['ownerRecord' => $this->group, 'pageClass' => ViewClientGroup::class])
            ->callTableAction('dissociate', $this->group->clients()->first())
            ->assertDispatched('group-members-changed');

        $this->assertSame(5, $this->group->clients()->count(), '5 + 1 added - 1 removed');
    }
}
