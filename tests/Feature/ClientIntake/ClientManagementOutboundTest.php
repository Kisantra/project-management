<?php

namespace Tests\Feature\ClientIntake;

use App\Jobs\SendHandoffToClientManagement;
use App\Livewire\Clients\IntakeContractPanel;
use App\Models\ClientIntake;
use App\Services\ClientManagement\ClientIntakeService;
use App\Services\ClientManagement\ClientManagementClient;
use Illuminate\Http\Client\Request;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use Spatie\Permission\PermissionRegistrar;

class ClientManagementOutboundTest extends ClientIntakeTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Notification::fake();
    }

    protected function intake(): ClientIntake
    {
        return app(ClientIntakeService::class)->receive($this->intakePayload(['cm_lead_id' => 12]));
    }

    protected function documentsResponse(): array
    {
        return ['data' => [
            [
                'id' => 31,
                'kind' => 'kontrak',
                'label' => 'Kontrak / surat perjanjian',
                'name' => 'Perjanjian.pdf',
                'mime' => 'application/pdf',
                'size' => 1250000,
                'uploaded_at' => '2026-11-10T14:02:00+07:00',
                'preview_url' => 'https://cm.test/api/pm/lampiran/31?expires=1&signature=abc',
            ],
            [
                'id' => 32,
                'kind' => 'foto-npwp', // jenis yang belum dikenal PM
                'label' => 'Foto NPWP',
                'name' => 'jahat.png',
                'mime' => 'image/png',
                'size' => 2048,
                'uploaded_at' => null,
                'preview_url' => 'javascript:alert(1)',
            ],
        ]];
    }

    public function test_documents_are_fetched_with_bearer_token_and_unsafe_urls_dropped(): void
    {
        Http::fake(['cm.test/api/pm/leads/12/documents' => Http::response($this->documentsResponse())]);

        $documents = app(ClientManagementClient::class)->documents(12);

        $this->assertCount(2, $documents);
        $this->assertSame('Perjanjian.pdf', $documents[0]['name']);
        $this->assertSame('kontrak', $documents[0]['kind']);
        $this->assertSame('lampiran', $documents[1]['kind'], 'Jenis tak dikenal diperlakukan sebagai lampiran');
        $this->assertStringStartsWith('https://cm.test/api/pm/lampiran/31', $documents[0]['preview_url']);
        $this->assertNull($documents[1]['preview_url']);

        Http::assertSent(fn (Request $request) => $request->method() === 'GET'
            && $request->url() === 'https://cm.test/api/pm/leads/12/documents'
            && $request->hasHeader('Authorization', 'Bearer ' . self::TOKEN));
    }

    public function test_contract_panel_previews_first_contract_and_refetches_on_open(): void
    {
        Http::fake(['cm.test/*' => Http::response($this->documentsResponse())]);
        $this->actingAs($this->makeUser('direktur')->givePermissionTo(
            app(PermissionRegistrar::class)->getPermissionClass()::findOrCreate('clients.*', 'web')
        ));

        Livewire::test(IntakeContractPanel::class, ['cmLeadId' => 12])
            ->assertSet('previewId', 31)
            ->assertSee('Perjanjian.pdf')
            ->assertSeeHtml('<iframe')
            ->call('closePreview')
            ->assertSet('previewId', null)
            ->call('preview', 31)
            ->assertSet('previewId', 31)
            ->call('reload');

        // Sekali saat mount, sekali saat pratinjau dibuka, sekali saat muat ulang.
        Http::assertSentCount(3);
    }

    public function test_contract_panel_shows_message_when_cm_is_unreachable_or_not_configured(): void
    {
        $this->actingAs($this->makeUser('direktur')->givePermissionTo(
            app(PermissionRegistrar::class)->getPermissionClass()::findOrCreate('clients.*', 'web')
        ));

        Http::fake(['cm.test/*' => Http::response('down', 503)]);

        Livewire::test(IntakeContractPanel::class, ['cmLeadId' => 12])
            ->assertSet('error', 'unreachable')
            ->assertSee('Client Management tidak dapat dihubungi');

        config(['services.client_management.url' => null]);

        Livewire::test(IntakeContractPanel::class, ['cmLeadId' => 12])
            ->assertSet('error', 'not-configured')
            ->assertSee('belum diatur');
    }

    public function test_handoff_job_posts_contract_payload_and_records_success(): void
    {
        Http::fake(['cm.test/api/pm/leads/12/handoff' => Http::response(['ok' => true, 'stage' => 'client'])]);

        $intake = $this->intake();
        $payload = [
            'status' => 'completed',
            'pm_client_id' => $intake->client_id,
            'completed_by' => 'Rina',
            'completed_at' => '2026-11-12T09:15:00+08:00',
            'client_url' => 'https://pm.test/clients/' . $intake->client_id,
        ];

        (new SendHandoffToClientManagement($intake->id, $payload))->handle(app(ClientManagementClient::class));

        Http::assertSent(fn (Request $request) => $request->method() === 'POST'
            && $request->url() === 'https://cm.test/api/pm/leads/12/handoff'
            && $request->hasHeader('Authorization', 'Bearer ' . self::TOKEN)
            && $request->data() === $payload);

        $intake->refresh();
        $this->assertNotNull($intake->handoff_sent_at);
        $this->assertNull($intake->handoff_error);
    }

    public function test_handoff_payload_includes_match_and_project(): void
    {
        \Illuminate\Support\Facades\Bus::fake([SendHandoffToClientManagement::class]);

        $intake = $this->intake();
        $user = $this->makeUser('direktur', ['name' => 'Rina']);
        $client = $this->existingClient(['name' => 'PT Payload Uji']);
        $project = \App\Models\Project::withoutEvents(fn () => \App\Models\Project::forceCreate([
            'client_id' => $client->id, 'name' => 'PPh Badan 2026', 'type' => 'yearly', 'due_date' => '2027-04-30',
        ]));
        $intake->update(['client_id' => $client->id, 'project_id' => $project->id, 'matched' => true, 'completed_at' => now(), 'completed_by' => $user->id]);

        app(ClientIntakeService::class)->dispatchHandoff($intake->fresh());

        \Illuminate\Support\Facades\Bus::assertDispatched(SendHandoffToClientManagement::class, fn (SendHandoffToClientManagement $job) => $job->payload['status'] === 'completed'
            && $job->payload['pm_client_id'] === $client->id
            && $job->payload['completed_by'] === 'Rina'
            && $job->payload['matched'] === true
            && $job->payload['project_name'] === 'PPh Badan 2026'
            && str_ends_with($job->payload['project_url'], "/projects/{$project->id}")
            && str_ends_with($job->payload['client_url'], "/clients/{$client->id}"));
    }

    public function test_handoff_job_retries_on_server_error(): void
    {
        Http::fake(['cm.test/*' => Http::response('maintenance', 503)]);
        $intake = $this->intake();

        try {
            (new SendHandoffToClientManagement($intake->id, ['status' => 'completed']))->handle(app(ClientManagementClient::class));
            $this->fail('5xx harus melempar exception supaya antrean mengulang');
        } catch (RequestException) {
            // diharapkan
        }

        $intake->refresh();
        $this->assertNull($intake->handoff_sent_at);
        $this->assertStringContainsString('HTTP 503', $intake->handoff_error);
    }

    public function test_handoff_job_does_not_retry_client_errors_or_missing_config(): void
    {
        Http::fake(['cm.test/*' => Http::response(['message' => 'Lead tidak dikenal'], 404)]);
        $intake = $this->intake();

        // 4xx: tidak dilempar (job ditandai gagal tanpa diulang)
        (new SendHandoffToClientManagement($intake->id, ['status' => 'completed']))->handle(app(ClientManagementClient::class));
        $this->assertStringContainsString('HTTP 404', $intake->fresh()->handoff_error);

        config(['services.client_management.token' => null]);

        (new SendHandoffToClientManagement($intake->id, ['status' => 'completed']))->handle(app(ClientManagementClient::class));
        $this->assertStringContainsString('belum dikonfigurasi', $intake->fresh()->handoff_error);
    }
}
