<?php

namespace App\Filament\Resources\ClientGroupResource\Pages;

use App\Filament\Resources\ClientGroupResource;
use App\Support\Ui\ClientAvatar;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\HtmlString;
use Livewire\Attributes\On;

/**
 * One client group (docs/mockups/client-groups-redesign.html): group details on
 * the left, the member table (ClientsRelationManager) on the right.
 */
class ViewClientGroup extends ViewRecord
{
    protected static string $resource = ClientGroupResource::class;

    protected static string $view = 'filament.resources.client-group-resource.pages.view-group';

    public function getTitle(): string|Htmlable
    {
        return $this->record->name;
    }

    public function getHeading(): string|Htmlable
    {
        $active = $this->record->status === 'active';

        return new HtmlString(
            '<span class="kis-ghead"><span class="kis-gtile is-lg" aria-hidden="true">' . e(ClientAvatar::initials($this->record->name)) . '</span>'
            . '<span>' . e($this->record->name) . '</span>'
            . '<span class="kis-badge kis-badge--dot kis-badge--' . ($active ? 'blue' : 'neutral') . '">' . ($active ? 'Aktif' : 'Nonaktif') . '</span>'
            . '</span>'
        );
    }

    /** "3 perusahaan · 2 aktif · 1 nonaktif · Kontak Admin" */
    public function getSubheading(): string|Htmlable|null
    {
        $total = $this->record->clients()->count();
        $active = $this->record->clients()->where('status', 'Active')->count();
        $parts = ['<b>' . $total . '</b> perusahaan', '<b>' . $active . '</b> aktif'];
        if ($total - $active > 0) {
            $parts[] = '<b>' . ($total - $active) . '</b> nonaktif';
        }
        if ($this->record->contact_name) {
            $parts[] = 'Kontak ' . e($this->record->contact_name);
        }

        return new HtmlString('<span class="kis-lead">' . implode(' · ', $parts) . '</span>');
    }

    public function getBreadcrumbs(): array
    {
        return [
            ClientGroupResource::getUrl('index') => 'Grup Client',
            $this->record->name,
        ];
    }

    /** Members were added or removed in the table: refresh the counts in the heading. */
    #[On('group-members-changed')]
    public function refreshCounts(): void
    {
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make()
                ->label('Ubah grup')
                ->icon('heroicon-m-pencil-square')
                ->color('gray'),
        ];
    }
}
