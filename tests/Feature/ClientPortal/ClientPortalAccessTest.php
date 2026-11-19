<?php

namespace Tests\Feature\ClientPortal;

use App\Livewire\Client\Panel\ClientSwitcher;
use App\Livewire\Client\Panel\DocumentTab;
use App\Livewire\Client\Panel\ProfileTab;
use App\Livewire\Projects\Modals\GlobalDocumentModal;
use App\Models\Client;
use App\Models\ClientDocument;
use App\Models\ClientDocumentRequirement;
use App\Models\SopLegalDocument;
use App\Models\User;
use App\Models\UserClient;
use App\Support\ClientPortal\ClientContext;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Features\SupportLockedProperties\CannotUpdateLockedPropertyException;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * Client portal (/klien): a client account only ever sees the companies it is
 * linked to in user_clients, whatever ids the browser sends. Run against a
 * migrated test database (DatabaseTransactions, like the other tests here):
 *
 *   DB_DATABASE=project_management_test ./vendor/bin/phpunit tests/Feature/ClientPortal
 */
class ClientPortalAccessTest extends TestCase
{
    use DatabaseTransactions;

    private User $user;

    private Client $own;

    private Client $second;

    private Client $foreign;

    protected function setUp(): void
    {
        parent::setUp();

        config(['broadcasting.default' => 'null']);
        Filament::setCurrentPanel(Filament::getPanel('klien'));
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        // Signed in first: creating a Client writes a Trackable activity row that needs a user.
        $this->user = $this->makeClientUser();
        $this->actingAs($this->user);

        $this->own = $this->makeClient('PT UJI PORTAL ALFA');
        $this->second = $this->makeClient('PT UJI PORTAL BETA');
        $this->foreign = $this->makeClient('PT UJI PORTAL LAIN');

        foreach ([$this->own, $this->second] as $client) {
            UserClient::forceCreate(['user_id' => $this->user->id, 'client_id' => $client->id]);
        }
    }

    protected function tearDown(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        parent::tearDown();
    }

    public function test_context_uses_the_session_company_only_when_the_user_owns_it(): void
    {
        $context = app(ClientContext::class);

        // First company by name when nothing is chosen yet.
        $this->assertSame($this->own->id, $context->id());

        session([ClientContext::SESSION_KEY => $this->foreign->id]);
        $this->assertSame($this->own->id, app(ClientContext::class)->id());

        $this->assertFalse($context->switchTo($this->foreign->id));
        $this->assertTrue($context->switchTo($this->second->id));
        $this->assertSame($this->second->id, session(ClientContext::SESSION_KEY));
    }

    public function test_switcher_ignores_a_company_the_user_is_not_linked_to(): void
    {
        session([ClientContext::SESSION_KEY => $this->own->id]);

        Livewire::test(ClientSwitcher::class)
            ->call('switchClient', $this->foreign->id)
            ->assertNoRedirect();

        $this->assertSame($this->own->id, session(ClientContext::SESSION_KEY));
    }

    public function test_switcher_changes_company_and_reloads_the_page(): void
    {
        session([ClientContext::SESSION_KEY => $this->own->id]);

        Livewire::test(ClientSwitcher::class)
            ->assertSee($this->own->name)
            ->assertSee('1 dari 2 perusahaan')
            ->call('switchClient', $this->second->id)
            ->assertRedirect();

        $this->assertSame($this->second->id, session(ClientContext::SESSION_KEY));
    }

    public function test_selected_company_cannot_be_set_from_the_browser(): void
    {
        $this->expectException(CannotUpdateLockedPropertyException::class);

        Livewire::test(ProfileTab::class)->set('selectedClientId', $this->foreign->id);
    }

    public function test_profile_follows_the_company_chosen_in_the_switcher(): void
    {
        session([ClientContext::SESSION_KEY => $this->second->id]);

        Livewire::test(ProfileTab::class)
            ->assertSet('selectedClientId', $this->second->id)
            ->assertSee($this->second->name);
    }

    public function test_documents_of_another_company_cannot_be_previewed_or_downloaded(): void
    {
        $document = ClientDocument::forceCreate([
            'client_id' => $this->foreign->id,
            'user_id' => $this->user->id,
            'file_path' => 'clients/uji/rahasia.pdf',
            'original_filename' => 'rahasia.pdf',
            'status' => 'valid',
        ]);

        Livewire::test(DocumentTab::class)
            ->call('previewDocuments', $document->id)
            ->assertSet('previewDocument', null)
            ->call('downloadDocument', $document->id)
            ->assertNoFileDownloaded();
    }

    public function test_upload_dialog_refuses_another_company(): void
    {
        Livewire::test(DocumentTab::class)
            ->call('openUploadModal', $this->foreign->id, null, true)
            ->assertForbidden();
    }

    public function test_upload_dialog_refuses_a_requirement_of_another_company(): void
    {
        $requirement = $this->makeRequirement($this->foreign);

        Livewire::test(DocumentTab::class)
            ->call('openUploadModal', $this->own->id, null, false, $requirement->id)
            ->assertForbidden();
    }

    public function test_upload_link_from_ringkasan_opens_only_the_companys_own_items(): void
    {
        $sop = SopLegalDocument::forceCreate([
            'name' => 'Dokumen Uji Portal ' . uniqid(),
            'client_type' => 'Both',
            'is_required' => true,
            'category' => 'Dasar',
            'order' => 999,
            'is_active' => true,
        ]);
        $foreignRequirement = $this->makeRequirement($this->foreign);

        $own = Livewire::withQueryParams(['unggah' => 'sop-' . $sop->id])->test(DocumentTab::class);
        $this->assertTrue($own->get('openUploadOnLoad'));
        $this->assertSame($sop->id, (int) $own->get('selectedSopId'));

        $foreign = Livewire::withQueryParams(['unggah' => 'req-' . $foreignRequirement->id])->test(DocumentTab::class);
        $this->assertFalse($foreign->get('openUploadOnLoad'));
    }

    public function test_additional_document_keeps_its_name_and_file_name(): void
    {
        Storage::fake('public');
        config(['broadcasting.default' => 'null']);

        Livewire::test(DocumentTab::class)
            ->call('openUploadModal', $this->own->id, null, true)
            ->set('documentName', 'Laporan Uji')
            ->set('documentDescription', 'Catatan uji')
            ->set('uploadFile', [UploadedFile::fake()->create('uji.pdf', 20, 'application/pdf')])
            ->call('uploadDocument')
            ->assertHasNoErrors();

        $document = ClientDocument::where('client_id', $this->own->id)->latest('id')->firstOrFail();
        $this->assertSame("Laporan Uji\nCatatan uji", $document->description);
        $this->assertSame('uji.pdf', $document->original_filename);
    }

    public function test_staff_pages_are_not_part_of_the_client_panel(): void
    {
        $panel = Filament::getPanel('klien');
        // Only the portal's own sections, plus the error pages plugin's 403/404 pages.
        $pages = collect($panel->getPages())
            ->reject(fn ($page) => str_starts_with($page, 'Cmsmaxinc\\FilamentErrorPages\\'))
            ->map(fn ($page) => class_basename($page));

        $this->assertEmpty($panel->getResources(), 'Portal klien tidak boleh punya resource (user activities).');
        $this->assertFalse($pages->contains('ActiveUsers'));
        $this->assertFalse($pages->contains('PWASettingsPage'));
        $this->assertFalse($pages->contains('SettingsHub'));
        $this->assertEqualsCanonicalizing(
            ['DashboardClient', 'Projects', 'Documents', 'TaxReports', 'Profile'],
            $pages->all(),
        );
    }

    public function test_global_document_modal_refuses_client_accounts(): void
    {
        Livewire::test(GlobalDocumentModal::class)->assertForbidden();
    }

    private function makeClient(string $name): Client
    {
        return Client::forceCreate([
            'name' => $name . ' ' . uniqid(),
            'NPWP' => (string) random_int(100000000000000, 999999999999999),
            'status' => 'Active',
        ]);
    }

    private function makeRequirement(Client $client): ClientDocumentRequirement
    {
        return ClientDocumentRequirement::forceCreate([
            'client_id' => $client->id,
            'created_by' => $this->user->id,
            'name' => 'Persyaratan Uji ' . uniqid(),
            'category' => 'other',
            'is_required' => true,
            'status' => 'pending',
        ]);
    }

    private function makeClientUser(): User
    {
        $user = User::forceCreate([
            'name' => 'Uji Portal Klien',
            'email' => 'uji-portal-' . uniqid() . '@example.test',
            'password' => bcrypt('rahasia'),
            'status' => 'active',
        ]);

        Role::findOrCreate('client', 'web');
        $user->assignRole('client');

        return $user;
    }
}
