<?php

namespace App\Livewire\TaxReport;

use App\Models\TaxReport;
use App\Models\User;
use App\Models\UserClient;
use Filament\Actions\Action;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use App\Support\TaxPeriod;
use Illuminate\Support\Facades\Storage;
use Livewire\Attributes\On;
use Livewire\Component;

/**
 * Tab "Upload SPT Masa" — unggah file SPT dari Coretax per jenis pajak yang
 * dikontrak klien. Saat diunggah, status jenis itu ditandai Sudah Lapor &
 * Sudah Bayar (terintegrasi dengan indikator bulan & tab jenis).
 */
class SptUpload extends Component implements HasForms, HasActions
{
    use InteractsWithForms;
    use InteractsWithActions;

    public TaxReport $taxReport;

    /** Jika diisi, komponen hanya menangani satu jenis pajak (mode sub-tab). */
    public ?string $type = null;

    public const TYPE_LABELS = [
        'ppn'       => 'PPN',
        'pph'       => 'PPh',
        'bupot'     => 'PPh Unifikasi',
        'pph_badan' => 'PPh Badan',
    ];

    public const CONTRACT_COLUMN = [
        'ppn'       => 'ppn_contract',
        'pph'       => 'pph_contract',
        'bupot'     => 'bupot_contract',
        'pph_badan' => 'pph_badan_contract',
    ];

    public function mount(int $taxReportId, ?string $type = null): void
    {
        $this->type = $type;
        $this->taxReport = TaxReport::with(['client', 'taxCalculationSummaries'])->findOrFail($taxReportId);
    }

    /** Jenis pajak yang dikontrak klien + status SPT-nya saat ini. */
    public function getRowsProperty(): array
    {
        $client = $this->taxReport->client;
        $summaries = $this->taxReport->taxCalculationSummaries->keyBy('tax_type');

        $rows = [];
        foreach (self::TYPE_LABELS as $type => $label) {
            if ($this->type && $type !== $this->type) {
                continue; // mode single-type (sub-tab per jenis pajak)
            }
            if (! ($client->{self::CONTRACT_COLUMN[$type]} ?? false)) {
                continue;
            }

            $s = $summaries->get($type);
            $ext = ($s && $s->bukti_lapor) ? strtolower(pathinfo($s->bukti_lapor, PATHINFO_EXTENSION)) : null;
            $rows[] = [
                'type'        => $type,
                'label'       => $label,
                'reported'    => $s && $s->report_status === 'Sudah Lapor',
                'paid'        => $s && $s->bayar_status === 'Sudah Bayar',
                'fileUrl'     => ($s && $s->bukti_lapor) ? Storage::disk('public')->url($s->bukti_lapor) : null,
                'isImage'     => in_array($ext, ['jpg', 'jpeg', 'png', 'webp', 'gif'], true),
                'nomor'       => $s?->nomor_bukti_lapor,
                'reportedAt'  => TaxPeriod::date($s?->reported_at),
                'paidAt'      => TaxPeriod::date($s?->bayar_at),
                'noActivity'  => (bool) $s?->no_activity,
            ];
        }

        return $rows;
    }

    public function uploadSptAction(): Action
    {
        return Action::make('uploadSpt')
            ->label('Unggah SPT')
            ->modalHeading(fn (array $arguments) => 'Unggah SPT ' . (self::TYPE_LABELS[$arguments['type']] ?? ''))
            ->modalWidth('lg')
            ->fillForm(function (array $arguments) {
                $s = $this->taxReport->taxCalculationSummaries->firstWhere('tax_type', $arguments['type']);

                return [
                    'bukti_lapor'       => $s?->bukti_lapor,
                    'reported_at'       => $s?->reported_at ?? now(),
                    'nomor_bukti_lapor' => $s?->nomor_bukti_lapor,
                ];
            })
            ->form([
                FileUpload::make('bukti_lapor')
                    ->label('File SPT (dari Coretax)')
                    ->required()
                    ->disk('public')
                    ->directory('spt/' . $this->taxReport->id)
                    ->acceptedFileTypes(['application/pdf', 'image/jpeg', 'image/png'])
                    ->maxSize(10240)
                    ->openable()
                    ->downloadable()
                    ->helperText('Unggah berkas SPT/BPE hasil pelaporan di Coretax (PDF atau gambar, maks 10MB).'),
                DatePicker::make('reported_at')
                    ->label('Tanggal Lapor')
                    ->required()
                    ->native(false)
                    ->default(now()),
                TextInput::make('nomor_bukti_lapor')
                    ->label('Nomor Bukti (NTTE / BPE)')
                    ->maxLength(100)
                    ->placeholder('Opsional'),
            ])
            ->action(function (array $arguments, array $data) {
                $type = $arguments['type'];

                $summary = $this->taxReport->taxCalculationSummaries()->firstOrCreate(['tax_type' => $type]);
                $summary->update([
                    'bukti_lapor'       => $data['bukti_lapor'],
                    'nomor_bukti_lapor' => $data['nomor_bukti_lapor'] ?? null,
                    'reported_at'       => $data['reported_at'],
                    'report_status'     => 'Sudah Lapor',
                    'bayar_status'      => 'Sudah Bayar',
                    'bayar_at'          => $data['reported_at'],

                    // Penanda nihil dibersihkan: begitu ada SPT sungguhan, masa
                    // ini jelas bukan masa tanpa aktivitas. Tanpa ini satu baris
                    // bisa mengaku keduanya sekaligus.
                    'no_activity'       => false,
                    'no_activity_by'    => null,
                    'no_activity_at'    => null,
                ]);

                $this->taxReport->refresh();

                Notification::make()
                    ->title('SPT ' . (self::TYPE_LABELS[$type] ?? '') . ' tersimpan')
                    ->body('Status ditandai Sudah Lapor & Sudah Bayar.')
                    ->success()
                    ->send();

                // Kirim notifikasi ke user klien terkait
                $this->notifyClient($type);

                $this->dispatch('spt-updated');
            });
    }

    public function removeSptAction(): Action
    {
        return Action::make('removeSpt')
            ->label('Hapus SPT')
            ->color('danger')
            ->requiresConfirmation()
            ->modalHeading('Hapus SPT?')
            ->modalDescription('Berkas SPT dihapus dan status dikembalikan ke Belum Lapor & Belum Bayar.')
            ->action(function (array $arguments) {
                $type = $arguments['type'];
                $summary = $this->taxReport->taxCalculationSummaries->firstWhere('tax_type', $type);

                if ($summary) {
                    if ($summary->bukti_lapor) {
                        Storage::disk('public')->delete($summary->bukti_lapor);
                    }

                    $summary->update([
                        'bukti_lapor'       => null,
                        'nomor_bukti_lapor' => null,
                        'reported_at'       => null,
                        'report_status'     => 'Belum Lapor',
                        'bayar_status'      => 'Belum Bayar',
                        'bayar_at'          => null,
                    ]);

                    $this->taxReport->refresh();
                }

                Notification::make()->title('SPT dihapus')->success()->send();

                $this->dispatch('spt-updated');
            });
    }

    /** Kirim notifikasi database ke semua user klien bahwa SPT telah dilaporkan. */
    protected function notifyClient(string $type): void
    {
        $recipientIds = UserClient::where('client_id', $this->taxReport->client_id)->pluck('user_id');

        if ($recipientIds->isEmpty()) {
            return;
        }

        // Hanya user klien (role 'client') — jangan kirim ke staff/PM/internal.
        $recipients = User::whereIn('id', $recipientIds)->role('client')->get();

        if ($recipients->isEmpty()) {
            return;
        }

        $label = self::TYPE_LABELS[$type] ?? strtoupper($type);
        // The period year, not the year the report row was created.
        $masa = $this->periodLabel();

        foreach ($recipients as $recipient) {
            Notification::make()
                ->title('SPT ' . $label . ' telah dilaporkan')
                ->body('SPT Masa ' . $label . ' periode ' . $masa . ' telah diunggah & dilaporkan oleh Kisantra.')
                ->icon('heroicon-o-document-check')
                ->iconColor('success')
                ->sendToDatabase($recipient);
        }
    }

    public function periodLabel(): string
    {
        return TaxPeriod::label($this->taxReport->month, $this->taxReport->year ?? $this->taxReport->created_at?->year);
    }

    /** Another panel changed the status (activity confirmation, another SPT row). */
    #[On('spt-updated')]
    public function refreshRows(): void
    {
        $this->taxReport->refresh();
    }

    /** "Ada, unggah SPT" on the activity confirmation opens the upload form here. */
    #[On('open-spt-upload')]
    public function openUpload(string $type): void
    {
        if ($this->type === null || $this->type === $type) {
            $this->mountAction('uploadSpt', ['type' => $type]);
        }
    }

    public function render()
    {
        return view('livewire.tax-report.spt-upload');
    }
}
