<?php

namespace App\Livewire\Client\Panel;

use App\Livewire\Client\Panel\Concerns\UsesCurrentClient;
use Livewire\Component;
use App\Models\Client;
use App\Models\ClientCredential;
use Filament\Notifications\Notification;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

class ProfileTab extends Component
{
    use UsesCurrentClient;

    public Collection $clients;
    public bool $showCredentialModal = false;

    public string $credCoreTaxUserId = '';
    public string $credCoreTaxPassword = '';
    public string $credDjpAccount = '';
    public string $credDjpPassword = '';
    public string $credEmail = '';
    public string $credEmailPassword = '';

    protected int $cacheDuration = 300;

    protected $rules = [
        'credCoreTaxUserId'   => 'nullable|string|max:255',
        'credCoreTaxPassword' => 'nullable|string|max:255',
        'credDjpAccount'      => 'nullable|string|max:255',
        'credDjpPassword'     => 'nullable|string|max:255',
        'credEmail'           => 'nullable|email|max:255',
        'credEmailPassword'   => 'nullable|string|max:255',
    ];

    protected $messages = [
        'credEmail.email' => 'Format email tidak valid.',
    ];

    protected $listeners = ['refreshProfile' => 'refresh'];

    public function mount()
    {
        $this->loadClientData();
    }

    protected function loadClientData()
    {
        $this->clients = $this->clientContext()->clients();
    }

    public function getSelectedClientProperty(): ?Client
    {
        if (!$this->selectedClientId) {
            return null;
        }

        $userId = auth()->id();
        $clientId = $this->selectedClientId;

        return Cache::remember(
            "profile_client_{$userId}_{$clientId}",
            $this->cacheDuration,
            function () use ($clientId) {
                return Client::query()
                    ->where('id', $clientId)
                    ->with([
                        'pic:id,name,nik',
                        'accountRepresentative:id,name,email,phone_number,kpp',
                        'clientCredential',
                        'contacts' => fn($q) => $q->where('is_active', true)->limit(5),
                    ])
                    ->first();
            }
        );
    }

    protected function clearClientCache()
    {
        if (!$this->selectedClientId) {
            return;
        }

        $userId = auth()->id();
        $clientId = $this->selectedClientId;

        Cache::forget("profile_client_{$userId}_{$clientId}");
    }

    public function openCredentialModal(): void
    {
        if (!$this->selectedClientId || !$this->userOwnsClient($this->selectedClientId)) {
            return;
        }

        $cred = $this->selectedClient?->load('clientCredential')->clientCredential;

        if ($cred) {
            $this->credCoreTaxUserId   = $cred->core_tax_user_id ?? '';
            $this->credCoreTaxPassword = $cred->core_tax_password ?? '';
            $this->credDjpAccount      = $cred->djp_account ?? '';
            $this->credDjpPassword     = $cred->djp_password ?? '';
            $this->credEmail           = $cred->email ?? '';
            $this->credEmailPassword   = $cred->email_password ?? '';
        } else {
            $this->resetCredentialForm();
        }

        $this->showCredentialModal = true;
    }

    public function closeCredentialModal(): void
    {
        $this->showCredentialModal = false;
        $this->resetCredentialForm();
        $this->resetErrorBag();
    }

    public function saveCredentials(): void
    {
        $this->validate();

        $client = $this->selectedClient;

        if (!$client || !$this->userOwnsClient($client->id)) {
            return;
        }

        $data = [
            'core_tax_user_id'  => $this->credCoreTaxUserId ?: null,
            'core_tax_password' => $this->credCoreTaxPassword ?: null,
            'djp_account'       => $this->credDjpAccount ?: null,
            'djp_password'      => $this->credDjpPassword ?: null,
            'email'             => $this->credEmail ?: null,
            'email_password'    => $this->credEmailPassword ?: null,
        ];

        if ($client->credential_id && $client->clientCredential) {
            $client->clientCredential->update($data);
        } else {
            $credential = ClientCredential::create($data);
            $client->credential_id = $credential->id;
            $client->save();
        }

        $this->clearClientCache();
        $this->closeCredentialModal();

        Notification::make()
            ->title('Kredensial berhasil disimpan')
            ->success()
            ->send();
    }

    protected function userOwnsClient(int $clientId): bool
    {
        return $this->clientContext()->owns($clientId);
    }

    protected function resetCredentialForm(): void
    {
        $this->credCoreTaxUserId   = '';
        $this->credCoreTaxPassword = '';
        $this->credDjpAccount      = '';
        $this->credDjpPassword     = '';
        $this->credEmail           = '';
        $this->credEmailPassword   = '';
    }

    public function getContractStatusProperty(): array
    {
        $client = $this->selectedClient;

        if (!$client) {
            return [];
        }

        return [
            [
                'name' => 'PPN',
                'active' => $client->ppn_contract ?? false,
                'description' => 'Pajak Pertambahan Nilai',
            ],
            [
                'name' => 'PPh 21',
                'active' => $client->pph_contract ?? false,
                'description' => 'Pajak Penghasilan Pasal 21',
            ],
            [
                'name' => 'PPh Unifikasi',
                'active' => $client->bupot_contract ?? false,
                'description' => 'Bukti Potong Unifikasi',
            ],
            [
                'name' => 'PPh Badan',
                'active' => $client->pph_badan_contract ?? false,
                'description' => 'PPh Badan Tahunan',
            ],
        ];
    }

    public function refresh()
    {
        $this->clearClientCache();
        $this->loadClientData();
    }

    public function render()
    {
        return view('livewire.client.panel.profile-tab', [
            'selectedClient' => $this->selectedClient,
            'contractStatus' => $this->contractStatus,
        ]);
    }
}
