<?php

namespace App\Livewire\Client\Management;

use App\Livewire\Clients\ClientListClickup;
use App\Models\Application;
use App\Models\Client;
use App\Models\ClientCredential;
use App\Support\Ui\ClientAvatar;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\DB;
use Livewire\Component;

/**
 * Credential viewer/editor for one client, shown in the Clients list modal and
 * the Filament "Kelola Kredensial" action. Lets staff view, edit, and create
 * three groups of credentials at once: client (Core Tax / DJP / Email),
 * per-application, and PIC.
 */
class CredentialManager extends Component
{
    /**
     * Client logins, one table row each: [name, icon, account field, secret field, account label].
     * Fields are keys of $clientCred.
     */
    public const CLIENT_LOGINS = [
        'core_tax' => ['Core Tax', 'heroicon-o-building-library', 'core_tax_user_id', 'core_tax_password', 'User ID'],
        'djp' => ['DJP Online', 'heroicon-o-globe-alt', 'djp_account', 'djp_password', 'Akun'],
        'email' => ['Email', 'heroicon-o-envelope', 'email', 'email_password', 'Alamat email'],
    ];

    public int $clientId;

    /** Rendered in a frame of our own (Clients list): shows a close button and handles Escape. */
    public bool $closable = false;

    /** 'view' (read-only, default) or 'edit'. */
    public string $mode = 'view';

    public string $clientName = '';

    public ?string $clientLogo = null;

    public ?string $clientNpwp = null;

    /** Core Tax / DJP / Email credentials (ClientCredential). */
    public array $clientCred = [
        'core_tax_user_id'  => '',
        'core_tax_password' => '',
        'djp_account'       => '',
        'djp_password'      => '',
        'email'             => '',
        'email_password'    => '',
        'notes'             => '',
    ];

    /** Per-application credentials (ApplicationClient rows). */
    public array $appCreds = [];

    public bool $hasPic = false;

    public array $pic = [
        'name'     => '',
        'nik'      => '',
        'password' => '',
        'status'   => null,
    ];

    /** [id => name] options for the application select. */
    public array $applicationOptions = [];

    public function mount(int $clientId, bool $closable = false): void
    {
        $this->clientId = $clientId;
        $this->closable = $closable;
        $this->applicationOptions = Application::orderBy('name')->pluck('name', 'id')->toArray();
        $this->loadData();
    }

    protected function loadData(): void
    {
        $client = Client::with(['clientCredential', 'applicationCredentials.application', 'pic'])
            ->findOrFail($this->clientId);

        $this->clientName = $client->name;
        $this->clientLogo = $client->logo;
        $this->clientNpwp = ClientListClickup::formatNpwp($client->NPWP);

        $cred = $client->clientCredential;
        $this->clientCred = [
            'core_tax_user_id'  => $cred?->core_tax_user_id ?? '',
            'core_tax_password' => $cred?->core_tax_password ?? '',
            'djp_account'       => $cred?->djp_account ?? '',
            'djp_password'      => $cred?->djp_password ?? '',
            'email'             => $cred?->email ?? '',
            'email_password'    => $cred?->email_password ?? '',
            'notes'             => $cred?->notes ?? '',
        ];

        $this->appCreds = $client->applicationCredentials
            ->map(fn ($c) => [
                'id'              => $c->id,
                'application_id'  => $c->application_id,
                'username'        => $c->username ?? '',
                'password'        => $c->password ?? '',
                'activation_code' => $c->activation_code ?? '',
                'account_period'  => optional($c->account_period)->format('Y-m-d'),
                'is_active'       => (bool) $c->is_active,
                'notes'           => $c->notes ?? '',
            ])
            ->values()
            ->toArray();

        $this->hasPic = (bool) $client->pic;
        if ($client->pic) {
            $this->pic = [
                'name'     => $client->pic->name,
                'nik'      => $client->pic->nik,
                'password' => $client->pic->password,
                'status'   => $client->pic->status,
            ];
        }
    }

    public function enableEdit(): void
    {
        $this->mode = 'edit';
    }

    public function cancelEdit(): void
    {
        $this->resetErrorBag();
        $this->loadData();
        $this->mode = 'view';
    }

    public function addAppCred(): void
    {
        $this->appCreds[] = [
            'id'              => null,
            'application_id'  => null,
            'username'        => '',
            'password'        => '',
            'activation_code' => '',
            'account_period'  => null,
            'is_active'       => true,
            'notes'           => '',
        ];
    }

    public function removeAppCred(int $index): void
    {
        unset($this->appCreds[$index]);
        $this->appCreds = array_values($this->appCreds);
    }

    protected function rules(): array
    {
        return [
            'appCreds.*.application_id' => ['required'],
            'appCreds.*.username'       => ['required', 'string', 'max:255'],
            'appCreds.*.password'       => ['required', 'string', 'max:255'],
            'clientCred.email'          => ['nullable', 'email', 'max:255'],
            'pic.nik'                   => ['nullable', 'string', 'max:16'],
        ];
    }

    protected function messages(): array
    {
        return [
            'appCreds.*.application_id.required' => 'Pilih aplikasinya.',
            'appCreds.*.username.required' => 'Username wajib diisi.',
            'appCreds.*.username.max' => 'Username maksimal 255 karakter.',
            'appCreds.*.password.required' => 'Kata sandi wajib diisi.',
            'appCreds.*.password.max' => 'Kata sandi maksimal 255 karakter.',
            'clientCred.email.email' => 'Format email belum benar.',
            'clientCred.email.max' => 'Email maksimal 255 karakter.',
            'pic.nik.max' => 'NIK maksimal 16 karakter.',
        ];
    }

    protected function validationAttributes(): array
    {
        return [
            'appCreds.*.application_id' => 'aplikasi',
            'appCreds.*.username'       => 'username',
            'appCreds.*.password'       => 'password',
            'clientCred.email'          => 'email',
            'pic.nik'                   => 'NIK',
        ];
    }

    public function save(): void
    {
        $this->validate();

        DB::transaction(function () {
            $client = Client::findOrFail($this->clientId);

            // 1) Client credential (Core Tax / DJP / Email).
            $data = [
                'core_tax_user_id'  => $this->clientCred['core_tax_user_id'] ?: null,
                'core_tax_password' => $this->clientCred['core_tax_password'] ?: null,
                'djp_account'       => $this->clientCred['djp_account'] ?: null,
                'djp_password'      => $this->clientCred['djp_password'] ?: null,
                'email'             => $this->clientCred['email'] ?: null,
                'email_password'    => $this->clientCred['email_password'] ?: null,
                'notes'             => $this->clientCred['notes'] ?: null,
            ];
            $hasAny = collect($data)->contains(fn ($v) => filled($v));

            if ($cred = $client->clientCredential) {
                $cred->update($data);
            } elseif ($hasAny) {
                // The link is clients.credential_id -> client_credentials.id
                // (client_credentials has no client_id column).
                $cred = ClientCredential::create(array_merge($data, [
                    'credential_type' => 'general',
                    'is_active'       => true,
                ]));

                // credential_id is not in Client::$fillable — set it directly.
                $client->credential_id = $cred->id;
                $client->save();
            }

            // 2) Application credentials — sync (create / update / delete removed).
            $keptIds = collect($this->appCreds)->pluck('id')->filter()->all();
            $client->applicationCredentials()
                ->when(! empty($keptIds), fn ($q) => $q->whereNotIn('id', $keptIds))
                ->delete();

            foreach ($this->appCreds as $item) {
                $payload = [
                    'application_id'  => $item['application_id'],
                    'username'        => $item['username'],
                    'password'        => $item['password'],
                    'activation_code' => $item['activation_code'] ?: null,
                    'account_period'  => $item['account_period'] ?: null,
                    'is_active'       => (bool) ($item['is_active'] ?? true),
                    'notes'           => $item['notes'] ?: null,
                ];

                if (! empty($item['id'])) {
                    $client->applicationCredentials()->whereKey($item['id'])->first()?->update($payload);
                } else {
                    $client->applicationCredentials()->create($payload);
                }
            }

            // 3) PIC — shared master record across all its clients.
            if ($client->pic) {
                $pic = $client->pic;
                $pic->name = $this->pic['name'] ?: $pic->name;
                $pic->nik  = $this->pic['nik'] ?: $pic->nik;
                if (filled($this->pic['password'])) {
                    $pic->password = $this->pic['password'];
                }
                $pic->save();
            }
        });

        $this->loadData();
        $this->mode = 'view';

        Notification::make()
            ->success()
            ->title('Kredensial disimpan')
            ->body('Perubahan kredensial untuk ' . $this->clientName . ' berhasil disimpan.')
            ->send();
    }

    public static function initials(?string $name): string
    {
        return ClientAvatar::initials($name);
    }

    public function render()
    {
        return view('livewire.client.management.credential-manager', [
            'logoUrl' => ClientAvatar::url($this->clientLogo),
        ]);
    }
}
