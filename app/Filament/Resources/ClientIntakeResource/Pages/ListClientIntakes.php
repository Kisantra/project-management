<?php

namespace App\Filament\Resources\ClientIntakeResource\Pages;

use App\Filament\Resources\ClientIntakeResource;
use App\Models\ClientIntake;
use Filament\Resources\Components\Tab;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\HtmlString;

/**
 * Klien Masuk (docs/mockups/klien-masuk-redesign.html): the three tabs are drawn
 * as one summary strip (list-client-intakes.blade.php); the table changes its
 * columns per tab (ClientIntakeResource::table()).
 */
class ListClientIntakes extends ListRecords
{
    protected static string $resource = ClientIntakeResource::class;

    protected static string $view = 'filament.resources.client-intake-resource.pages.list-client-intakes';

    public function getTabs(): array
    {
        return [
            'menunggu' => Tab::make('Menunggu dicek')
                ->modifyQueryUsing(fn ($query) => $query->pending()),
            'selesai' => Tab::make('Selesai')
                ->modifyQueryUsing(fn ($query) => $query->completed()),
            'batal' => Tab::make('Batal di Client Management')
                ->modifyQueryUsing(fn ($query) => $query->withdrawn()),
        ];
    }

    public function getDefaultActiveTab(): string | int | null
    {
        return 'menunggu';
    }

    /** The title says it; Filament's default would read "Klien Masuk › List". */
    public function getBreadcrumbs(): array
    {
        return [];
    }

    /** One sentence instead of the old callout card: what CM is and what to do here. */
    public function getSubheading(): string | Htmlable | null
    {
        return new HtmlString(
            '<span class="kis-lead">Deal dari <b>Client Management (CM)</b>, aplikasi tim marketing Kisantra. '
            . 'Cek datanya sebelum dijadikan klien dan proyek. '
            . '<span class="kis-lead__hint" tabindex="0" title="Begitu lead mencapai Deal di CM, datanya dikirim ke sini untuk dicek dan diproses tim pajak. Isian bertanda CM di halaman proses berasal dari sana.">Cara kerjanya</span></span>'
        );
    }

    /** Figures for the strip, one query per tab. */
    public function getQueueSummary(): array
    {
        $pending = ClientIntake::query()->pending();
        $completed = ClientIntake::query()->completed();
        $oldest = (clone $pending)->oldest()->first();

        return [
            'menunggu' => [
                'count' => (clone $pending)->count(),
                'value' => (int) (clone $pending)->sum('deal_value'),
                'overdue' => ClientIntake::query()->overdue()->count(),
                'oldest' => $oldest?->waitingDays(),
            ],
            'selesai' => [
                'count' => (clone $completed)->count(),
                'thisMonth' => (clone $completed)->where('completed_at', '>=', now()->startOfMonth())->count(),
                'failed' => (clone $completed)->whereNull('handoff_sent_at')->whereNotNull('handoff_error')->where('handoff_error', '!=', '')->count(),
            ],
            'batal' => [
                'count' => ClientIntake::query()->withdrawn()->count(),
                'processed' => ClientIntake::query()->withdrawn()->whereNotNull('completed_at')->count(),
            ],
        ];
    }
}
