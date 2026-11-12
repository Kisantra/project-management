<?php

namespace Tests\Feature\Projects;

use App\Livewire\Projects\ProjectListClickup;
use App\Models\Client;
use App\Models\Project;
use App\Models\Sop;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Livewire\Livewire;
use Tests\TestCase;

/** The SOP filter on the Projects page follows the order admins set on the SOP page. */
class SopOrderTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();

        config(['broadcasting.default' => 'null']);
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        $this->actingAs(User::forceCreate([
            'name' => 'Uji SOP',
            'email' => 'uji-sop-' . uniqid() . '@example.test',
            'password' => bcrypt('rahasia'),
            'status' => 'active',
        ]));
    }

    public function test_new_sop_joins_the_end_of_the_order(): void
    {
        $last = (int) Sop::max('sort_order');

        $sop = Sop::create(['name' => 'Uji SOP Baru ' . uniqid()]);

        $this->assertSame($last + 1, $sop->sort_order);
    }

    public function test_sop_facets_follow_sort_order_not_name_or_count(): void
    {
        $client = Client::forceCreate(['name' => 'PT UJI SOP ' . uniqid(), 'status' => 'Active']);
        $front = Sop::forceCreate(['name' => 'ZZ Uji Depan', 'sort_order' => 2]);
        $back = Sop::forceCreate(['name' => 'AA Uji Belakang', 'sort_order' => 500]);

        foreach ([[$front, 1], [$back, 3]] as [$sop, $projects]) {
            for ($i = 0; $i < $projects; $i++) {
                Project::forceCreate([
                    'client_id' => $client->id,
                    'name' => "Uji {$sop->name} {$i}",
                    'type' => 'single',
                    'due_date' => now()->addMonth()->toDateString(),
                    'status' => 'in_progress',
                    'sop_id' => $sop->id,
                ]);
            }
        }

        $ids = collect(Livewire::test(ProjectListClickup::class)->instance()->sopFacets['sops'])->pluck('id');

        $this->assertLessThan($ids->search((string) $back->id), $ids->search((string) $front->id));
    }
}
