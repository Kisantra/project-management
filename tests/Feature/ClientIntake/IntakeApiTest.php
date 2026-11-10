<?php

namespace Tests\Feature\ClientIntake;

use App\Models\Client;
use App\Models\ClientContact;
use App\Models\ClientIntake;
use App\Models\Project;
use App\Services\ClientManagement\ClientIntakeService;
use Filament\Notifications\DatabaseNotification;
use Illuminate\Support\Facades\Notification;

class IntakeApiTest extends ClientIntakeTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Notification::fake();
    }

    protected function withdraw(int $cmLeadId, array $body)
    {
        return $this->postJson("/api/v1/intake/clients/{$cmLeadId}/withdraw", $body, ['Authorization' => 'Bearer ' . self::TOKEN]);
    }

    public function test_rejects_missing_or_wrong_token(): void
    {
        $payload = $this->intakePayload();

        $this->postIntake($payload, token: null)->assertStatus(401);
        $this->postIntake($payload, token: 'salah')->assertStatus(401);
        $this->postJson("/api/v1/intake/clients/{$payload['cm_lead_id']}/withdraw", ['reason' => 'mundur'], ['Authorization' => 'Bearer salah'])
            ->assertStatus(401);

        $this->assertDatabaseMissing('client_intakes', ['cm_lead_id' => $payload['cm_lead_id']]);
    }

    public function test_rejects_everything_when_token_not_configured(): void
    {
        config(['services.client_management.token' => '']);

        // Header kosong tidak boleh "cocok" dengan token kosong.
        $this->postJson('/api/v1/intake/clients', $this->intakePayload(), ['Authorization' => 'Bearer '])
            ->assertStatus(503);
    }

    public function test_validation_errors_are_json_even_without_accept_header(): void
    {
        $this->post('/api/v1/intake/clients', ['cm_lead_id' => 5, 'value' => -1], ['Authorization' => 'Bearer ' . self::TOKEN])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['name', 'client_type', 'value']);
    }

    public function test_stores_intake_only_and_notifies_recipients(): void
    {
        $direktur = $this->makeUser('direktur');
        $manager = $this->makeUser('project-manager');
        $staff = $this->makeUser('staff');
        $inactive = $this->makeUser('direktur', ['status' => 'inactive']);

        $clients = Client::count();
        $contacts = ClientContact::count();
        $payload = $this->intakePayload(['value' => 0]);

        $response = $this->postIntake($payload)->assertOk();

        $intake = ClientIntake::where('cm_lead_id', $payload['cm_lead_id'])->firstOrFail();

        $response->assertExactJson([
            'intake_id' => $intake->id,
            'status' => 'received',
            'completed' => false,
            'pm_client_id' => null,
            'client_url' => $intake->reviewUrl(),
        ]);
        $this->assertStringContainsString("/klien-masuk/{$intake->id}/proses", $response->json('client_url'));

        // Belum ada klien, kontak, atau penugasan tim sebelum staf memproses
        $this->assertSame($clients, Client::count());
        $this->assertSame($contacts, ClientContact::count());
        $this->assertNull($intake->client_id);
        $this->assertNull($intake->suggested_client_id);
        $this->assertTrue($intake->isPending());

        $this->assertSame($payload['name'], $intake->name);
        $this->assertSame('PT', $intake->entity);
        $this->assertSame('PPh Badan', $intake->service);
        $this->assertSame(0, $intake->deal_value);
        $this->assertSame('Dimas', $intake->marketing_owner);
        $this->assertSame('0812-3456-7810', $intake->sent('contact.phone'));

        foreach ([$direktur, $manager] as $recipient) {
            Notification::assertSentTo($recipient, DatabaseNotification::class, function (DatabaseNotification $n) use ($payload, $intake) {
                return $n->data['title'] === 'Klien masuk dari Client Management'
                    && str_starts_with($n->data['body'], $payload['name'] . ' · PPh Badan. Deal 10 Nov 2026 oleh Dimas.')
                    && $n->data['actions'][0]['label'] === 'Cek & proses'
                    && $n->data['actions'][0]['url'] === $intake->reviewUrl();
            });
        }

        Notification::assertNotSentTo($staff, DatabaseNotification::class);
        Notification::assertNotSentTo($inactive, DatabaseNotification::class);
    }

    public function test_suggests_existing_client_by_npwp_with_different_formatting(): void
    {
        $direktur = $this->makeUser('direktur');

        $existing = $this->existingClient([
            'name' => 'PT LAMA SINAR',
            'NPWP' => '01.234.567.8-901.200', // 15 digit lama
            'email' => 'lama-' . uniqid() . '@example.test',
        ]);

        $this->postIntake($this->intakePayload([
            'name' => 'PT Sinar (nama di CM)',
            'npwp' => '0012 3456 7890 1200', // 16 digit, format lain
            'email' => 'beda-' . uniqid() . '@example.test',
        ]))->assertOk()->assertJson(['status' => 'received', 'pm_client_id' => null]);

        $intake = ClientIntake::latest('id')->first();
        $this->assertSame($existing->id, $intake->suggested_client_id);
        $this->assertSame('npwp', $intake->suggested_by);

        // Hanya saran: data klien lama tidak disentuh
        $this->assertSame('PT LAMA SINAR', $existing->fresh()->name);

        Notification::assertSentTo($direktur, DatabaseNotification::class,
            fn (DatabaseNotification $n) => str_contains($n->data['body'], 'Mungkin klien lama: PT LAMA SINAR.'));
    }

    public function test_suggests_existing_client_by_email_case_insensitive(): void
    {
        $existing = $this->existingClient([
            'name' => 'CV Maju Bersama Uji',
            'email' => 'Keuangan-' . ($id = uniqid()) . '@Maju.test',
        ]);

        $this->postIntake($this->intakePayload(['npwp' => null, 'email' => 'keuangan-' . $id . '@maju.test']))->assertOk();

        $intake = ClientIntake::latest('id')->first();
        $this->assertSame($existing->id, $intake->suggested_client_id);
        $this->assertSame('email', $intake->suggested_by);
    }

    public function test_resend_is_idempotent_and_refreshes_pending_data(): void
    {
        $direktur = $this->makeUser('direktur');
        $payload = $this->intakePayload();

        $first = $this->postIntake($payload)->assertOk()->json();
        $second = $this->postIntake(['service' => 'PPN'] + $payload)->assertOk()->json();

        $this->assertSame($first, $second);
        $this->assertSame(1, ClientIntake::where('cm_lead_id', $payload['cm_lead_id'])->count());
        $this->assertSame('PPN', ClientIntake::find($first['intake_id'])->service, 'Belum diproses: ikut data terbaru CM');
        Notification::assertSentToTimes($direktur, DatabaseNotification::class, 1);
    }

    public function test_resend_of_processed_intake_reports_client(): void
    {
        $payload = $this->intakePayload();
        $intakeId = $this->postIntake($payload)->assertOk()->json('intake_id');

        $client = $this->existingClient(['name' => 'PT Sudah Diproses']);
        ClientIntake::whereKey($intakeId)->update(['client_id' => $client->id, 'completed_at' => now()]);

        $this->postIntake($payload)->assertOk()->assertExactJson([
            'intake_id' => $intakeId,
            'status' => 'received',
            'completed' => true,
            'pm_client_id' => $client->id,
            'client_url' => \App\Filament\Resources\ClientResource::getUrl('view', ['record' => $client], panel: 'admin'),
        ]);
    }

    public function test_withdraw_pending_moves_to_batal_and_resend_reactivates(): void
    {
        $direktur = $this->makeUser('direktur');
        $payload = $this->intakePayload();
        $intakeId = $this->postIntake($payload)->assertOk()->json('intake_id');

        $this->withdraw($payload['cm_lead_id'], ['reason' => 'mundur', 'detail' => 'Mundur ke Proposal', 'note' => 'Minta revisi harga'])
            ->assertOk()
            ->assertExactJson(['ok' => true]);

        $intake = ClientIntake::find($intakeId);
        $this->assertTrue($intake->isWithdrawn());
        $this->assertSame('Mundur ke Proposal', $intake->withdrawn_detail);
        $this->assertSame(0, ClientIntake::pending()->whereKey($intakeId)->count());
        $this->assertSame(1, ClientIntake::withdrawn()->whereKey($intakeId)->count());

        Notification::assertSentTo($direktur, DatabaseNotification::class,
            fn (DatabaseNotification $n) => $n->data['title'] === 'Deal dibatalkan di Client Management'
                && str_contains($n->data['body'], 'Mundur ke Proposal')
                && str_contains($n->data['body'], 'Batal di Client Management'));

        // Kiriman withdraw yang sama tidak memberi notifikasi kedua
        $this->withdraw($payload['cm_lead_id'], ['reason' => 'mundur', 'detail' => 'Mundur ke Proposal'])->assertOk();
        Notification::assertSentToTimes($direktur, DatabaseNotification::class, 2); // masuk + batal

        // Lead kembali ke Deal: menunggu dicek lagi
        $this->postIntake($payload)->assertOk()->assertJson(['intake_id' => $intakeId, 'completed' => false]);

        $this->assertTrue($intake->fresh()->isPending());
        Notification::assertSentTo($direktur, DatabaseNotification::class,
            fn (DatabaseNotification $n) => $n->data['title'] === 'Deal aktif lagi di Client Management');
    }

    public function test_withdraw_processed_intake_keeps_client_and_project(): void
    {
        $direktur = $this->makeUser('direktur');
        $payload = $this->intakePayload();
        $intakeId = $this->postIntake($payload)->assertOk()->json('intake_id');

        $client = $this->existingClient(['name' => 'PT Proyek Jalan']);
        $project = Project::withoutEvents(fn () => Project::forceCreate([
            'client_id' => $client->id, 'name' => 'PPh Badan 2026', 'type' => 'yearly', 'due_date' => '2027-04-30',
        ]));
        ClientIntake::whereKey($intakeId)->update(['client_id' => $client->id, 'project_id' => $project->id, 'completed_at' => now()]);

        $this->withdraw($payload['cm_lead_id'], ['reason' => 'ditutup', 'detail' => 'Ditolak'])->assertOk();

        $intake = ClientIntake::find($intakeId);
        $this->assertTrue($intake->isWithdrawn());
        $this->assertTrue($intake->isCompleted());
        $this->assertNotNull(Client::find($client->id));
        $this->assertNotNull(Project::find($project->id));

        Notification::assertSentTo($direktur, DatabaseNotification::class,
            fn (DatabaseNotification $n) => $n->data['title'] === 'Deal dibatalkan di Client Management'
                && str_contains($n->data['body'], 'tetap ada di PM'));
    }

    public function test_withdraw_unknown_lead_returns_404_and_validates_reason(): void
    {
        $this->withdraw(987654321, ['reason' => 'ditutup', 'detail' => 'Ditolak'])->assertNotFound();

        $payload = $this->intakePayload();
        $this->postIntake($payload)->assertOk();

        $this->withdraw($payload['cm_lead_id'], ['reason' => 'lainnya'])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['reason']);
    }

    public function test_phone_goes_to_mobile_only_for_indonesian_mobile_numbers(): void
    {
        $this->assertSame(['phone' => null, 'mobile' => '0812-3456-7810'], ClientIntakeService::splitPhone('0812-3456-7810'));
        $this->assertSame(['phone' => null, 'mobile' => '+62 812 3456 7810'], ClientIntakeService::splitPhone('+62 812 3456 7810'));
        $this->assertSame(['phone' => null, 'mobile' => '6281234567810'], ClientIntakeService::splitPhone('6281234567810'));
        $this->assertSame(['phone' => '(021) 555-1234', 'mobile' => null], ClientIntakeService::splitPhone('(021) 555-1234'));
        $this->assertSame(['phone' => null, 'mobile' => null], ClientIntakeService::splitPhone(null));
    }
}
