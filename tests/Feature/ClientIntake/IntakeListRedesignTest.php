<?php

namespace Tests\Feature\ClientIntake;

use App\Filament\Resources\ClientIntakeResource;
use App\Filament\Resources\ClientIntakeResource\Pages\ListClientIntakes;
use App\Filament\Resources\ClientIntakeResource\Pages\ProsesClientIntake;
use App\Jobs\SendHandoffToClientManagement;
use App\Models\ClientIntake;
use App\Services\ClientManagement\ClientIntakeService;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;

/** Klien Masuk redesign (docs/mockups/klien-masuk-redesign.html). */
class IntakeListRedesignTest extends ClientIntakeTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Notification::fake();
        Bus::fake([SendHandoffToClientManagement::class]);
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $this->actingAs($this->grantClientAccess($this->makeUser('direktur', ['name' => 'Rina'])));
    }

    private function receive(array $overrides = [], ?int $daysAgo = null): ClientIntake
    {
        $intake = app(ClientIntakeService::class)->receive($this->intakePayload($overrides));

        if ($daysAgo !== null) {
            $intake->forceFill(['created_at' => now()->subDays($daysAgo)])->save();
        }

        return $intake->fresh();
    }

    public function test_waiting_queue_runs_oldest_first_and_marks_overdue_intakes(): void
    {
        $fresh = $this->receive(['name' => 'PT Baru Masuk'], 1);
        $old = $this->receive(['name' => 'PT Lama Menunggu'], ClientIntake::OVERDUE_DAYS + 2);

        $this->assertTrue($old->isOverdue());
        $this->assertFalse($fresh->isOverdue());
        $this->assertSame(1, ClientIntake::query()->overdue()->count());

        Livewire::test(ListClientIntakes::class)
            ->assertCanSeeTableRecords([$old, $fresh], inOrder: true)
            ->assertSee(($old->waitingDays()) . ' hari')
            ->assertSee('1 lewat ' . ClientIntake::OVERDUE_DAYS . ' hari')
            ->assertSee('Cek &amp; proses', escape: false);

        $this->assertSame('danger', ClientIntakeResource::getNavigationBadgeColor());
    }

    public function test_done_tab_shows_what_was_made_and_a_failed_handoff(): void
    {
        $done = $this->receive(['name' => 'PT Sudah Diproses']);
        $done->forceFill(['completed_at' => now(), 'handoff_error' => 'Client Management tidak dapat dihubungi'])->save();

        Livewire::test(ListClientIntakes::class)
            ->set('activeTab', 'selesai')
            ->assertCanSeeTableRecords([$done])
            ->assertSee('Klien &amp; proyek di PM', escape: false)
            ->assertSee('Gagal')
            ->assertSee('1 kabar ke CM gagal')
            ->assertDontSee('Menunggu</span>', escape: false);

        $this->assertSame('failed', $done->fresh()->handoffState());
    }

    public function test_cancelled_tab_flags_intakes_that_were_already_processed(): void
    {
        $intake = $this->receive(['name' => 'PT Batal Telanjur']);
        $intake->forceFill(['completed_at' => now()->subDay()])->save();
        app(ClientIntakeService::class)->withdraw($intake->fresh(), ['reason' => 'ditutup', 'detail' => 'Klien menunda']);

        Livewire::test(ListClientIntakes::class)
            ->set('activeTab', 'batal')
            ->assertCanSeeTableRecords([$intake])
            ->assertSee('Klien menunda')
            ->assertSee('Klien &amp; proyek sudah dibuat', escape: false)
            ->assertSee('1 kliennya telanjur dibuat');
    }

    public function test_process_page_summarises_the_deal_above_the_wizard(): void
    {
        $intake = $this->receive(['name' => 'PT Ringkas', 'service' => 'PPh Badan', 'deal_value' => 12500000], ClientIntake::OVERDUE_DAYS + 1);

        Livewire::test(ProsesClientIntake::class, ['record' => $intake->getKey()])
            ->assertSee('Client Management (CM)')
            ->assertSee('PT Ringkas')
            ->assertSee('Menunggu ' . $intake->waitingDays() . ' hari')
            ->assertSee('Rp 12.500.000')
            ->assertSee('Klien baru');
    }
}
