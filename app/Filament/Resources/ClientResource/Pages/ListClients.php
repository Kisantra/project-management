<?php

namespace App\Filament\Resources\ClientResource\Pages;

use App\Exports\Clients\ClientsDetailedExport;
use App\Exports\Clients\ClientsExport;
use App\Filament\Resources\ClientResource;
use App\Models\Client;
use Filament\Actions;
use Filament\Resources\Pages\Page;
use Maatwebsite\Excel\Facades\Excel;

class ListClients extends Page
{
    protected static string $resource = ClientResource::class;

    protected static string $view = 'filament.resources.client-resource.pages.list-clients-clickup';

    public function getTitle(): string
    {
        return 'Clients';
    }

    public function getSubheading(): ?string
    {
        $counts = Client::query()->whereIn('status', ['Active', 'Inactive'])->selectRaw('status, COUNT(*) AS n')->groupBy('status')->pluck('n', 'status');

        return number_format((int) ($counts['Active'] ?? 0), 0, ',', '.') . ' klien aktif · ' . number_format((int) ($counts['Inactive'] ?? 0), 0, ',', '.') . ' nonaktif';
    }

    /**
     * Resolve which client IDs match the current Livewire filter state.
     *
     * The Livewire component (ClientListClickup) persists its filters in the
     * URL via #[Url] attributes, so we can read them straight from the request
     * and replay the same query here without coupling to the component.
     *
     * Returns null when no filter is active → exports get the full unfiltered set.
     */
    protected function getFilteredClientIds(): ?array
    {
        $q        = (string) request()->query('q', '');
        $statuses = (array) request()->query('status', []);
        $types    = (array) request()->query('type', []);
        $pkps     = (array) request()->query('pkp', []);

        $hasFilters = $q !== '' || !empty($statuses) || !empty($types) || !empty($pkps);
        if (!$hasFilters) {
            return null;
        }

        $query = Client::query();

        if ($q !== '') {
            $term = '%' . $q . '%';
            $query->where(function ($w) use ($term) {
                $w->where('name', 'like', $term)
                  ->orWhere('NPWP', 'like', $term)
                  ->orWhere('email', 'like', $term);
            });
        }

        if (!empty($statuses)) $query->whereIn('status', $statuses);
        if (!empty($types))    $query->whereIn('client_type', $types);
        if (!empty($pkps))     $query->whereIn('pkp_status', $pkps);

        return $query->pluck('id')->all();
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\ActionGroup::make([
                Actions\Action::make('export_simple')
                    ->label('Simple Export')
                    ->icon('heroicon-o-document-arrow-down')
                    ->action(function () {
                        $ids = $this->getFilteredClientIds();
                        return Excel::download(
                            new ClientsExport([], false, $ids),
                            'clients-' . now()->format('Y-m-d-H-i') . '.xlsx',
                        );
                    }),

                Actions\Action::make('export_detailed')
                    ->label('Detailed Export (Multi-Sheet)')
                    ->icon('heroicon-o-document-chart-bar')
                    ->action(function () {
                        $ids = $this->getFilteredClientIds();
                        return Excel::download(
                            new ClientsDetailedExport([], $ids),
                            'clients-detailed-' . now()->format('Y-m-d-H-i') . '.xlsx',
                        );
                    }),
            ])
                ->label('Export')
                ->icon('heroicon-o-arrow-down-tray')
                ->color('gray')
                ->button(),

            Actions\CreateAction::make()
                ->url(fn () => ClientResource::getUrl('create'))
                ->label('Tambah Client Baru')
                ->icon('heroicon-o-plus'),
        ];
    }
}
