<?php

namespace Tests\Feature\Clients;

use App\Livewire\Clients\ClientListClickup;
use App\Models\Client;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Livewire\Livewire;
use Tests\TestCase;

/** Clients page redesign: summary strip, quick filters, new columns, safe sorting. */
class ClientListRedesignTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();

        config(['broadcasting.default' => 'null']);
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $this->actingAs(User::forceCreate([
            'name' => 'Uji Klien',
            'email' => 'uji-klien-' . uniqid() . '@example.test',
            'password' => bcrypt('rahasia'),
            'status' => 'active',
        ]));
    }

    public function test_summary_counts_active_clients(): void
    {
        Client::forceCreate(['name' => 'UJI TANPA PIC ' . uniqid(), 'status' => 'Active', 'client_type' => 'Badan', 'pkp_status' => 'PKP', 'ppn_contract' => true]);

        $summary = Livewire::test(ClientListClickup::class)->instance()->summary;
        $active = Client::where('status', 'Active');

        $this->assertSame((clone $active)->count(), $summary['total']);
        $this->assertSame((clone $active)->where('pkp_status', 'PKP')->count(), $summary['pkp']);
        $this->assertSame((clone $active)->whereNull('pic_id')->count(), $summary['noPic']);
        $this->assertSame((clone $active)->where('ppn_contract', true)->count(), $summary['contracts']['PPN']);
        $this->assertSame(
            (clone $active)->where(fn ($q) => $q->where('ppn_contract', true)->orWhere('pph_contract', true)->orWhere('bupot_contract', true)->orWhere('pph_badan_contract', true))->count(),
            $summary['contracted'],
        );
    }

    public function test_quick_filters_start_from_the_defaults_and_mark_their_tile(): void
    {
        $page = Livewire::test(ClientListClickup::class)
            ->set('typeFilter', ['Badan'])
            ->call('quickFilter', 'nopic')
            ->assertSet('noPic', true)
            ->assertSet('typeFilter', [])
            ->assertSet('statusFilter', ['Active'])
            ->assertSee('Tanpa PIC');

        $this->assertSame('nopic', $page->instance()->activeTile());
        $this->assertTrue($page->instance()->clients->every(fn (Client $client) => $client->pic_id === null));

        $page->call('quickFilter', 'contract')
            ->assertSet('noPic', false)
            ->assertSet('contractFilter', array_keys(ClientListClickup::CONTRACTS))
            ->assertSee('Berkontrak pajak');
        $this->assertSame('contract', $page->instance()->activeTile());

        $page->set('pkpFilter', ['Non-PKP']);
        $this->assertNull($page->instance()->activeTile(), 'a mixed filter matches no tile');

        $page->call('quickFilter', 'all');
        $this->assertSame('all', $page->instance()->activeTile());
    }

    public function test_contract_and_pic_columns_are_on_by_default(): void
    {
        Livewire::test(ClientListClickup::class)
            ->assertSet('visibleColumns', ['contract', 'pic', 'pkp', 'group'])
            ->assertSee('Kontrak')
            ->assertSee('PIC');
    }

    public function test_sorting_only_accepts_known_fields(): void
    {
        $page = Livewire::test(ClientListClickup::class)
            ->call('setSort', 'password:asc')
            ->assertSet('sortField', 'name')
            ->call('setSort', 'created_at:desc')
            ->assertSet('sortField', 'created_at')
            ->assertSet('sortDirection', 'desc')
            ->set('sortField', 'password; drop table clients');

        $this->assertSame('name:asc', $page->instance()->currentSort(), 'a tampered ?sort= falls back to name A–Z');
        $this->assertIsIterable($page->instance()->clients);
    }

    public function test_npwp_is_formatted_for_reading(): void
    {
        $this->assertSame('01.234.567.8-901.000', ClientListClickup::formatNpwp('012345678901000'));
        $this->assertSame('01.234.567.8-901.000', ClientListClickup::formatNpwp('01.234.567.8-901.000'));
        $this->assertSame('6402 0618 1084 0001', ClientListClickup::formatNpwp('6402061810840001'));
        $this->assertSame('123', ClientListClickup::formatNpwp('123'));
        $this->assertNull(ClientListClickup::formatNpwp(null));
    }
}
