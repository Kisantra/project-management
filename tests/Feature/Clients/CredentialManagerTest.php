<?php

namespace Tests\Feature\Clients;

use App\Livewire\Client\Management\CredentialManager;
use App\Livewire\Clients\ClientListClickup;
use App\Models\Application;
use App\Models\ApplicationClient;
use App\Models\Client;
use App\Models\ClientCredential;
use App\Models\Pic;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Livewire\Livewire;
use Tests\TestCase;

/** Credential modal redesign: one table of logins, Indonesian messages, close handled by the frame. */
class CredentialManagerTest extends TestCase
{
    use DatabaseTransactions;

    private Client $client;

    private Application $application;

    protected function setUp(): void
    {
        parent::setUp();

        config(['broadcasting.default' => 'null']);
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $this->actingAs(User::forceCreate([
            'name' => 'Uji Kredensial',
            'email' => 'uji-kredensial-' . uniqid() . '@example.test',
            'password' => bcrypt('rahasia'),
            'status' => 'active',
        ]));

        $credential = ClientCredential::forceCreate([
            'core_tax_user_id' => '0123456789012000',
            'core_tax_password' => 'Rahasia#1',
            'credential_type' => 'general',
            'is_active' => true,
        ]);
        $pic = Pic::forceCreate(['name' => 'Uji Pic Bersama', 'nik' => (string) random_int(1000000000000000, 9999999999999999), 'password' => 'pic-lama']);
        $this->client = Client::forceCreate([
            'name' => 'PT UJI KREDENSIAL ' . uniqid(),
            'NPWP' => '012345678901000',
            'status' => 'Active',
            'credential_id' => $credential->id,
            'pic_id' => $pic->id,
        ]);
        $this->application = Application::forceCreate(['name' => 'Uji e-Bupot ' . uniqid(), 'logo' => '', 'app_url' => '']);
        ApplicationClient::forceCreate([
            'client_id' => $this->client->id,
            'application_id' => $this->application->id,
            'username' => 'pengguna-uji',
            'password' => 'sandi-uji',
            'account_period' => '2020-01-31',
            'is_active' => true,
        ]);
    }

    public function test_view_lists_every_login_in_one_table(): void
    {
        Livewire::test(CredentialManager::class, ['clientId' => $this->client->id])
            ->assertSet('mode', 'view')
            ->assertSee('NPWP 01.234.567.8-901.000')
            ->assertSeeInOrder(['Layanan', 'Akun / ID', 'Kata sandi', 'Akun pajak &amp; email', 'Core Tax', 'DJP Online', 'Email', 'Aplikasi', $this->application->name, 'PIC', 'Uji Pic Bersama'], false)
            ->assertSee('0123456789012000')
            ->assertSee('Kedaluwarsa 31 Jan 2020')
            ->assertSee('Belum diisi')
            // Outside the Clients list frame the host modal closes itself.
            ->assertDontSeeHtml('kis-cred__close');
    }

    public function test_validation_messages_are_in_indonesian(): void
    {
        Livewire::test(CredentialManager::class, ['clientId' => $this->client->id])
            ->call('enableEdit')
            ->call('addAppCred')
            ->call('save')
            ->assertHasErrors(['appCreds.1.application_id', 'appCreds.1.username', 'appCreds.1.password'])
            ->assertSee('Pilih aplikasinya.')
            ->assertSee('Username wajib diisi.')
            ->assertSee('Kata sandi wajib diisi.')
            ->assertSee('3 kolom perlu diperbaiki.')
            ->assertSet('mode', 'edit');
    }

    public function test_save_updates_all_three_groups_and_returns_to_view(): void
    {
        Livewire::test(CredentialManager::class, ['clientId' => $this->client->id])
            ->call('enableEdit')
            ->set('clientCred.djp_account', 'akun-djp-baru')
            ->set('appCreds.0.password', 'sandi-baru')
            ->set('pic.password', 'pic-baru')
            ->call('save')
            ->assertHasNoErrors()
            ->assertSet('mode', 'view')
            ->assertSee('akun-djp-baru');

        $this->client->refresh();
        $this->assertSame('akun-djp-baru', $this->client->clientCredential->djp_account);
        $this->assertSame('sandi-baru', $this->client->applicationCredentials()->first()->password);
        $this->assertSame('pic-baru', $this->client->pic->password);
    }

    public function test_clients_list_opens_the_closable_manager(): void
    {
        Livewire::test(ClientListClickup::class)
            ->call('openCredentials', $this->client->id)
            ->assertSeeHtml('aria-labelledby="kis-cred-title-' . $this->client->id . '"')
            ->assertSeeHtml('kis-cred__close')
            ->call('closeCredentials')
            ->assertDontSeeHtml('aria-labelledby="kis-cred-title-' . $this->client->id . '"');
    }
}
