<?php

namespace Tests\Feature\ClientIntake;

use App\Filament\Resources\ClientIntakeResource\Pages\ListClientIntakes;
use App\Filament\Resources\ClientIntakeResource\Pages\ProsesClientIntake;
use App\Filament\Resources\ClientResource;
use App\Filament\Resources\ProjectResource\Pages\CreateProject;
use App\Jobs\SendHandoffToClientManagement;
use App\Models\AccountRepresentative;
use App\Models\Client;
use App\Models\ClientDocument;
use App\Models\ClientIntake;
use App\Models\DailyTask;
use App\Models\Pic;
use App\Models\Project;
use App\Models\Sop;
use App\Models\User;
use App\Services\ClientManagement\ClientIntakeService;
use App\Services\Clients\DefaultClientTeam;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

class ProsesClientIntakePageTest extends ClientIntakeTestCase
{
    protected User $staff;

    protected User $teamMember;

    protected AccountRepresentative $ar;

    protected Pic $pic;

    protected function setUp(): void
    {
        parent::setUp();

        Notification::fake();
        Bus::fake([SendHandoffToClientManagement::class]);
        Storage::fake('public');

        Filament::setCurrentPanel(Filament::getPanel('admin'));

        $this->staff = $this->grantClientAccess($this->makeUser('direktur', ['name' => 'Rina']));
        $this->teamMember = $this->makeUser('staff');
        $this->actingAs($this->staff);

        $this->ar = AccountRepresentative::forceCreate(['name' => 'AR Uji', 'kpp' => 'KPP Pratama Jakarta Setiabudi', 'status' => 'active']);
        $this->pic = Pic::forceCreate(['name' => 'PIC Uji', 'nik' => (string) random_int(1000000000000000, 9999999999999999), 'password' => 'rahasia123', 'status' => 'active']);
    }

    protected function fakeCm(int $kontrakStatus = 200, int $lampiranStatus = 200): void
    {
        Http::fake([
            'cm.test/api/pm/leads/*/documents' => Http::response(['data' => [
                ['id' => 31, 'kind' => 'kontrak', 'label' => 'Kontrak / surat perjanjian', 'name' => 'Perjanjian.pdf', 'mime' => 'application/pdf',
                    'size' => 1250000, 'uploaded_at' => '2026-11-10T14:02:00+07:00', 'preview_url' => 'https://cm.test/api/pm/lampiran/31?signature=a'],
                ['id' => 33, 'kind' => 'lampiran', 'label' => 'Foto NPWP', 'name' => 'npwp.jpg', 'mime' => 'image/jpeg',
                    'size' => 2048, 'uploaded_at' => null, 'preview_url' => 'https://cm.test/api/pm/lampiran/33?signature=b'],
            ]]),
            'cm.test/api/pm/lampiran/31*' => Http::response('%PDF-1.4 kontrak uji', $kontrakStatus, ['Content-Type' => 'application/pdf']),
            'cm.test/api/pm/lampiran/33*' => Http::response('JPEG uji', $lampiranStatus, ['Content-Type' => 'image/jpeg']),
        ]);
    }

    protected function receive(array $overrides = []): ClientIntake
    {
        return app(ClientIntakeService::class)->receive($this->intakePayload($overrides));
    }

    protected function page(ClientIntake $intake)
    {
        return Livewire::test(ProsesClientIntake::class, ['record' => $intake->getKey()]);
    }

    /** Isian wajib yang tidak ada di CM: AR, PIC (Badan), tenggat proyek. */
    protected function requiredFields(): array
    {
        return [
            'client.ar_id' => $this->ar->id,
            'client.pic_id' => $this->pic->id,
            'project.due_date' => '2027-04-30',
        ];
    }

    public function test_list_shows_pending_intakes_with_cm_explanation(): void
    {
        $pending = $this->receive(['name' => 'PT Antre Uji']);
        $done = $this->receive(['name' => 'PT Selesai Uji']);
        $done->update(['completed_at' => now()]);

        Livewire::test(ListClientIntakes::class)
            ->assertSee('Client Management (CM)')
            ->assertSee('aplikasi tim marketing Kisantra')
            ->assertCanSeeTableRecords([$pending])
            ->assertCanNotSeeTableRecords([$done])
            ->assertSee('Rp 12.500.000')
            ->assertSee('Cek &amp; proses', escape: false);
    }

    public function test_new_client_is_prefilled_from_cm(): void
    {
        $this->fakeCm();
        $intake = $this->receive(['entity' => 'PT', 'service' => 'PPh Badan']);

        $this->page($intake)
            ->assertSuccessful()
            ->assertSee('Client Management (CM)')
            ->assertFormSet([
                'mode' => 'new',
                'client.name' => $intake->name,
                'client.client_type' => 'Badan',
                'client.client_subtype' => 'Perseroan Terbatas (PT)',
                'client.pph_badan_contract' => true,
                'contact.name' => 'Budi Hartono',
                'contact.mobile' => '0812-3456-7810', // nomor seluler -> HP/WhatsApp
                'contact.phone' => null,
                'project.name' => 'PPh Badan 2026',
                'project.type' => 'yearly',
            ])
            ->assertSee('Rp 12.500.000');
    }

    public function test_creates_client_project_and_documents_in_one_go(): void
    {
        $this->fakeCm();
        $intake = $this->receive();

        $page = $this->page($intake)
            ->call('loadCmDocuments')
            ->assertSet('data.documents', [31, 33])
            ->fillForm($this->requiredFields())
            ->call('process')
            ->assertHasNoFormErrors();

        $intake->refresh();
        $client = Client::findOrFail($intake->client_id);
        $project = Project::findOrFail($intake->project_id);

        $page->assertRedirect(ClientResource::getUrl('view', ['record' => $client]));

        // Klien baru dengan data dari CM dan isian staf
        $this->assertSame($intake->name, $client->name);
        $this->assertSame('Perseroan Terbatas (PT)', $client->client_subtype);
        $this->assertSame($this->ar->id, $client->ar_id);
        $this->assertTrue((bool) $client->pph_badan_contract);
        $this->assertDatabaseHas('client_contacts', ['client_id' => $client->id, 'name' => 'Budi Hartono', 'type' => 'primary', 'mobile' => '0812-3456-7810', 'phone' => null]);
        $this->assertTrue($client->userClients()->where('user_id', $this->teamMember->id)->exists(), 'tim default ditugaskan');

        // Proyek lewat alur yang sama dengan Create Project
        $this->assertSame('PPh Badan 2026', $project->name);
        $this->assertSame($client->id, $project->client_id);
        $this->assertSame('yearly', $project->type);
        $this->assertStringContainsString('Rp 12.500.000', $project->description);
        $this->assertTrue($project->userProject()->where('user_id', $this->staff->id)->exists(), 'direktur otomatis jadi anggota');
        $this->assertTrue(DailyTask::where('project_id', $project->id)->exists());

        // Dokumen CM menjadi dokumen klien + kontrak
        $documents = ClientDocument::where('client_id', $client->id)->get();
        $this->assertCount(2, $documents);
        $this->assertEqualsCanonicalizing(['Perjanjian.pdf', 'npwp.jpg'], $documents->pluck('original_filename')->all());
        $this->assertTrue($documents->every(fn ($d) => $d->document_category === 'additional' && $d->status === 'valid'));
        foreach ($documents as $document) {
            Storage::disk('public')->assertExists($document->file_path);
        }
        $this->assertStringStartsWith('client-contracts/', $client->contract_file);
        $this->assertSame('%PDF-1.4 kontrak uji', Storage::disk('public')->get($client->contract_file));

        // Klien masuk selesai, CM dikabari lewat antrean
        $this->assertTrue($intake->isCompleted());
        $this->assertFalse($intake->matched);
        $this->assertSame($this->staff->id, $intake->completed_by);

        Bus::assertDispatched(SendHandoffToClientManagement::class, fn (SendHandoffToClientManagement $job) => $job->intakeId === $intake->id
            && $job->payload['matched'] === false
            && $job->payload['project_name'] === 'PPh Badan 2026'
            && $job->payload['pm_client_id'] === $client->id);
    }

    public function test_links_suggested_existing_client_and_suggests_subtype(): void
    {
        $this->fakeCm();
        $existing = $this->existingClient(['name' => 'PT Lama Sentosa', 'NPWP' => '01.234.567.8-901.444', 'email' => 'lama-' . uniqid() . '@example.test']);
        $intake = $this->receive(['name' => 'PT Lama Sentosa Tbk', 'npwp' => '0012345678901444', 'entity' => 'PT']);
        $clients = Client::count();

        $this->page($intake)
            ->assertFormSet([
                'mode' => 'link',
                'existing_client_id' => $existing->id,
                'client.name' => 'PT Lama Sentosa',
                'client.client_subtype' => 'Perseroan Terbatas (PT)', // kosong di PM -> saran dari bentuk usaha CM
            ])
            ->assertSee('Mungkin klien lama: PT Lama Sentosa')
            ->assertSee('Di CM: PT Lama Sentosa Tbk')
            ->assertSee('Tautkan &amp; buat proyek', escape: false)
            ->fillForm($this->requiredFields())
            ->set('data.documents', [])
            ->call('process')
            ->assertHasNoFormErrors();

        $intake->refresh();

        $this->assertSame($clients, Client::count(), 'tidak membuat klien baru');
        $this->assertSame($existing->id, $intake->client_id);
        $this->assertTrue($intake->matched);
        $this->assertSame($this->ar->id, $existing->fresh()->ar_id);
        $this->assertSame('Perseroan Terbatas (PT)', $existing->fresh()->client_subtype);
        $this->assertSame($existing->id, Project::find($intake->project_id)->client_id);
        Bus::assertDispatched(SendHandoffToClientManagement::class, fn ($job) => $job->payload['matched'] === true);
    }

    public function test_switching_to_new_client_refills_from_cm(): void
    {
        $this->fakeCm();
        $existing = $this->existingClient(['name' => 'PT Lama Ganti', 'NPWP' => '0099887766554433']);
        $intake = $this->receive(['name' => 'PT Baru Dari CM', 'npwp' => '0099887766554433']);

        $this->page($intake)
            ->assertFormSet(['client.name' => 'PT Lama Ganti'])
            ->set('data.mode', 'new')
            ->assertFormSet(['client.name' => 'PT Baru Dari CM', 'client.client_subtype' => 'Perseroan Terbatas (PT)']);
    }

    public function test_sop_steps_are_created_through_the_shared_project_form(): void
    {
        $this->fakeCm();
        $sop = Sop::forceCreate(['name' => 'SOP PPh Badan Uji']);
        $step = $sop->steps()->forceCreate(['name' => 'Kumpulkan dokumen', 'order' => 1]);
        $step->tasks()->forceCreate(['title' => 'Minta laporan keuangan']);
        $step->requiredDocuments()->forceCreate(['name' => 'Laporan keuangan 2026']);

        $intake = $this->receive();

        $this->page($intake)
            ->fillForm($this->requiredFields())
            ->fillForm(['project.sop_id' => $sop->id])
            // Saran jenis dari layanan (PPh Badan -> yearly) tidak boleh hilang saat SOP dipilih.
            ->assertFormSet(['project.type' => 'yearly'])
            ->set('data.documents', [])
            ->call('process')
            ->assertHasNoFormErrors();

        $project = Project::findOrFail($intake->fresh()->project_id);
        $projectStep = $project->steps()->first();

        $this->assertSame($sop->id, $project->sop_id);
        $this->assertSame('yearly', $project->type);
        $this->assertSame('Kumpulkan dokumen', $projectStep?->name);
        $this->assertSame(['Minta laporan keuangan'], $projectStep->tasks()->pluck('title')->all());
        $this->assertSame(['Laporan keuangan 2026'], $projectStep->requiredDocuments()->pluck('name')->all());
    }

    public function test_failed_download_names_the_file_and_saves_nothing(): void
    {
        $this->fakeCm(lampiranStatus: 500);
        $intake = $this->receive();
        $counts = [Client::count(), Project::count(), ClientDocument::count()];

        $this->page($intake)
            ->call('loadCmDocuments')
            ->fillForm($this->requiredFields())
            ->call('process')
            ->assertHasErrors(['data.documents'])
            ->assertSee('npwp.jpg');

        $this->assertSame($counts, [Client::count(), Project::count(), ClientDocument::count()]);
        $this->assertTrue($intake->fresh()->isPending());
        $this->assertSame([], Storage::disk('public')->allFiles());
        Bus::assertNotDispatched(SendHandoffToClientManagement::class);

        // Hapus centang dokumen yang gagal lalu coba lagi: berhasil
        $this->page($intake)
            ->call('loadCmDocuments')
            ->fillForm($this->requiredFields())
            ->set('data.documents', [31])
            ->call('process')
            ->assertHasNoFormErrors();

        $this->assertTrue($intake->fresh()->isCompleted());
        $this->assertSame(1, ClientDocument::where('client_id', $intake->fresh()->client_id)->count());
    }

    public function test_withdrawn_intake_shows_banner_and_cannot_be_processed(): void
    {
        $intake = $this->receive();
        app(ClientIntakeService::class)->withdraw($intake, ['reason' => 'ditutup', 'detail' => 'Ditolak']);
        $clients = Client::count();

        $this->page($intake)
            ->assertSee('Deal dibatalkan di Client Management')
            ->assertSee('Tidak diproses')
            ->call('process');

        $this->assertFalse($intake->fresh()->isCompleted());
        $this->assertSame($clients, Client::count());
    }

    public function test_intake_processed_elsewhere_is_not_processed_twice(): void
    {
        $this->fakeCm();
        $intake = $this->receive();
        $page = $this->page($intake)->fillForm($this->requiredFields())->set('data.documents', []);

        $intake->update(['completed_at' => now()]);
        $clients = Client::count();

        $page->call('process');

        $this->assertSame($clients, Client::count());
        Bus::assertNotDispatched(SendHandoffToClientManagement::class);
    }

    public function test_manual_create_project_uses_same_steps_and_keeps_pic_and_priority(): void
    {
        $client = $this->existingClient(['name' => 'PT Proyek Manual']);
        DefaultClientTeam::assign($client);

        Livewire::test(CreateProject::class)
            ->fillForm([
                'name' => 'Proyek Manual Uji',
                'client_id' => $client->id,
                'pic_id' => $this->staff->id,
                'type' => 'monthly',
                'priority' => 'urgent',
                'due_date' => '2027-01-31',
                // Form manual mulai dengan satu baris kosong; dikosongkan seperti pengguna menghapusnya.
                'steps' => [],
                'userProject' => [],
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $project = Project::where('name', 'Proyek Manual Uji')->firstOrFail();

        $this->assertSame($this->staff->id, $project->pic_id);
        $this->assertSame('urgent', $project->priority);
        $this->assertTrue($project->userProject()->where('user_id', $this->staff->id)->exists());
        $this->assertTrue(DailyTask::where('project_id', $project->id)->exists());
    }

    public function test_choosing_sop_keeps_project_type_on_manual_create_project(): void
    {
        $sop = Sop::forceCreate(['name' => 'SOP Jenis Uji']);
        $sop->steps()->forceCreate(['name' => 'Tahap uji', 'order' => 1]);

        Livewire::test(CreateProject::class)
            ->fillForm(['type' => 'monthly'])
            ->fillForm(['sop_id' => $sop->id])
            ->assertFormSet(['type' => 'monthly', 'steps' => fn (array $steps) => count($steps) === 1]);
    }
}
