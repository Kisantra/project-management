<?php

namespace App\Livewire\TaxReport;

use App\Models\TaxCalculationSummary;
use App\Models\TaxReport;
use Filament\Actions\Action;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Livewire\Attributes\On;
use Livewire\Component;

/**
 * Konfirmasi ada/tidaknya aktivitas untuk jenis pajak yang bersifat kondisional.
 *
 * PPh Unifikasi dan PPh Badan hanya wajib dilaporkan bila masa itu memang ada
 * pemotongan. Tanpa penyataan eksplisit, sistem tidak bisa membedakan "memang
 * tidak ada aktivitas" dari "datanya belum diinput", dan masa itu akan terus
 * terhitung sebagai tunggakan.
 *
 * Menandai tanpa aktivitas menyetel status menjadi Sudah Lapor & Sudah Bayar
 * agar seluruh perhitungan yang sudah ada terus bekerja, sementara kolom
 * no_activity yang membedakannya dari pelaporan sungguhan.
 */
class ActivityConfirmation extends Component implements HasForms, HasActions
{
    use InteractsWithForms;
    use InteractsWithActions;

    public TaxReport $taxReport;

    /** 'bupot' atau 'pph_badan'. */
    public string $type;

    public const LABELS = [
        'bupot' => 'PPh Unifikasi',
        'pph_badan' => 'PPh Badan',
    ];

    public function mount(int $taxReportId, string $type): void
    {
        abort_unless(\in_array($type, TaxCalculationSummary::CONDITIONAL_TYPES, true), 404);

        $this->type = $type;
        $this->taxReport = TaxReport::with(['client', 'taxCalculationSummaries'])->findOrFail($taxReportId);
    }

    public function getLabelProperty(): string
    {
        return self::LABELS[$this->type] ?? strtoupper($this->type);
    }

    public function getSummaryProperty(): ?TaxCalculationSummary
    {
        return $this->taxReport->taxCalculationSummaries->firstWhere('tax_type', $this->type);
    }

    /**
     * Apakah masa ini sudah punya jejak aktivitas nyata.
     *
     * Dipakai untuk memperingatkan sebelum menandai nihil: menandai masa yang
     * jelas ada aktivitasnya hampir pasti keliru.
     */
    public function getHasEvidenceProperty(): bool
    {
        $s = $this->summary;

        if ($this->type === 'bupot' && $this->taxReport->bupots()->exists()) {
            return true;
        }

        return $s && ($s->bukti_lapor !== null
            || (float) ($s->pajak_keluar ?? 0) !== 0.0
            || (float) ($s->saldo_final ?? 0) !== 0.0);
    }

    public function getIsNoActivityProperty(): bool
    {
        return (bool) ($this->summary?->no_activity);
    }

    public function markNoActivityAction(): Action
    {
        return Action::make('markNoActivity')
            ->label('Tandai tidak ada aktivitas')
            ->icon('heroicon-o-minus-circle')
            ->color('gray')
            ->requiresConfirmation()
            ->modalHeading('Tidak ada aktivitas ' . $this->label . '?')
            ->modalDescription(fn () => $this->hasEvidence
                ? 'Masa ini sudah punya data ' . $this->label . '. Menandainya tanpa aktivitas akan menyatakan tidak ada yang perlu dilaporkan, padahal datanya menunjukkan sebaliknya.'
                : 'Masa ini akan ditandai selesai tanpa SPT, karena tidak ada yang wajib dilaporkan. Bisa dibatalkan kapan saja.')
            ->modalSubmitActionLabel('Ya, tidak ada aktivitas')
            ->action(function () {
                $summary = $this->taxReport->taxCalculationSummaries()
                    ->firstOrCreate(['tax_type' => $this->type]);

                $summary->markNoActivity(auth()->id());

                $this->taxReport->refresh();

                Notification::make()
                    ->title($this->label . ' ditandai tanpa aktivitas')
                    ->body('Masa ini tidak lagi dihitung sebagai tunggakan.')
                    ->success()
                    ->send();

                $this->dispatch('spt-updated');
            });
    }

    public function clearNoActivityAction(): Action
    {
        return Action::make('clearNoActivity')
            ->label('Batalkan')
            ->icon('heroicon-o-arrow-uturn-left')
            ->color('gray')
            ->requiresConfirmation()
            ->modalHeading('Batalkan penandaan?')
            ->modalDescription('Masa ini kembali dihitung sebagai kewajiban yang belum dilaporkan.')
            ->action(function () {
                $this->summary?->clearNoActivity();

                $this->taxReport->refresh();

                Notification::make()
                    ->title('Penandaan dibatalkan')
                    ->body($this->label . ' kembali menjadi kewajiban.')
                    ->success()
                    ->send();

                $this->dispatch('spt-updated');
            });
    }

    #[On('spt-updated')]
    public function refreshState(): void
    {
        $this->taxReport->refresh();
    }

    public function periodLabel(): string
    {
        return \App\Support\TaxPeriod::label($this->taxReport->month, $this->taxReport->year ?? $this->taxReport->created_at?->year);
    }

    public function render()
    {
        return view('livewire.tax-report.activity-confirmation');
    }
}
