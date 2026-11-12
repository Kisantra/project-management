<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ClientIntakeResource\Pages;
use App\Models\ClientIntake;
use Filament\Resources\Resource;
use Filament\Support\Enums\FontWeight;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\HtmlString;

/**
 * "Klien Masuk": lead yang mencapai Deal di Client Management (CM), menunggu
 * dicek tim pajak sebelum dijadikan klien + proyek di PM.
 */
class ClientIntakeResource extends Resource
{
    protected static ?string $model = ClientIntake::class;

    protected static ?string $slug = 'klien-masuk';

    protected static ?string $navigationGroup = 'Client Management';

    protected static ?string $navigationIcon = 'heroicon-o-inbox-arrow-down';

    protected static ?string $navigationLabel = 'Klien Masuk';

    protected static ?string $modelLabel = 'klien masuk';

    protected static ?string $pluralModelLabel = 'Klien Masuk';

    protected static ?string $breadcrumb = 'Klien Masuk';

    protected static ?string $recordTitleAttribute = 'name';

    protected static ?int $navigationSort = 2;

    // Memproses klien masuk membuat klien, jadi aksesnya mengikuti menu Clients.
    public static function shouldRegisterNavigation(): bool
    {
        return auth()->user()->can('clients.*');
    }

    public static function canAccess(): bool
    {
        return auth()->user()->can('clients.*');
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit(Model $record): bool
    {
        return false;
    }

    public static function canDelete(Model $record): bool
    {
        return false;
    }

    public static function canDeleteAny(): bool
    {
        return false;
    }

    public static function getNavigationBadge(): ?string
    {
        $pending = ClientIntake::query()->pending()->count();

        return $pending > 0 ? (string) $pending : null;
    }

    /** The queue is work, not a problem: blue, red only when something waits too long. */
    public static function getNavigationBadgeColor(): ?string
    {
        return ClientIntake::query()->overdue()->exists() ? 'danger' : 'primary';
    }

    public static function getNavigationBadgeTooltip(): ?string
    {
        $overdue = ClientIntake::query()->overdue()->count();

        return $overdue > 0
            ? "Menunggu dicek, {$overdue} lewat " . ClientIntake::OVERDUE_DAYS . ' hari'
            : 'Menunggu dicek';
    }

    /**
     * Columns depend on the tab (docs/mockups/klien-masuk-redesign.html):
     * waiting shows the match and how long it waits, done shows what was
     * created and whether Client Management heard back, cancelled shows the
     * reason and whether a client was already made.
     */
    public static function table(Table $table): Table
    {
        $tab = fn (string ...$tabs) => fn ($livewire) => in_array($livewire->activeTab ?? 'menunggu', $tabs, true);
        $date = fn ($value) => $value?->locale('id')->translatedFormat('j M Y');
        $link = fn (string $url, string $label) => new HtmlString('<a href="' . e($url) . '" class="font-semibold text-primary-700 hover:underline dark:text-primary-300">' . e($label) . '</a>');

        return $table
            ->modifyQueryUsing(fn ($query) => $query->with(['suggestedClient:id,name', 'client:id,name', 'project:id,name', 'completedBy:id,name']))
            // The waiting queue runs oldest first; the history newest first.
            ->defaultSort(fn (Builder $query, $livewire) => ($livewire->activeTab ?? 'menunggu') === 'menunggu'
                ? $query->orderBy('created_at')
                : $query->orderByDesc('created_at'))
            ->recordUrl(fn (ClientIntake $record) => $record->reviewUrl())
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Klien')
                    ->weight(FontWeight::SemiBold)
                    ->searchable()
                    ->sortable()
                    ->description(fn (ClientIntake $record) => collect([$record->entity, $record->sent('client_type')])->filter()->implode(' · ') ?: null),

                Tables\Columns\TextColumn::make('service')
                    ->label('Layanan')
                    ->searchable()
                    ->wrap()
                    ->placeholder('–'),

                Tables\Columns\TextColumn::make('deal_value')
                    ->label('Nilai deal')
                    ->formatStateUsing(fn (?int $state) => ClientIntake::formatRupiah($state))
                    ->weight(FontWeight::Bold)
                    ->placeholder('–')
                    ->alignEnd()
                    ->sortable(),

                Tables\Columns\TextColumn::make('deal_at')
                    ->label('Deal')
                    ->formatStateUsing(fn ($state) => $date($state))
                    ->description(fn (ClientIntake $record) => filled($record->marketing_owner) ? 'oleh ' . $record->marketing_owner : null)
                    ->placeholder('–')
                    ->sortable()
                    ->visible($tab('menunggu')),

                // Searchable through the Deal column's "oleh …" line.
                Tables\Columns\TextColumn::make('marketing_owner')
                    ->label('PJ marketing')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('pm_match')
                    ->label('Di PM')
                    ->state(fn (ClientIntake $record) => $record->suggestedClient ? 'Mungkin klien lama' : 'Klien baru')
                    ->badge(fn (ClientIntake $record) => $record->suggestedClient !== null)
                    ->color(fn (ClientIntake $record) => $record->suggestedClient ? 'primary' : 'gray')
                    ->description(fn (ClientIntake $record) => $record->suggestedClient?->name)
                    ->visible($tab('menunggu')),

                Tables\Columns\TextColumn::make('waiting')
                    ->label('Menunggu')
                    ->state(fn (ClientIntake $record) => $record->waitingDays() === 0 ? 'Hari ini' : $record->waitingDays() . ' hari')
                    ->color(fn (ClientIntake $record) => $record->isOverdue() ? 'danger' : 'gray')
                    ->icon(fn (ClientIntake $record) => $record->isOverdue() ? 'heroicon-m-exclamation-circle' : null)
                    ->weight(FontWeight::Bold)
                    ->tooltip(fn (ClientIntake $record) => 'Diterima ' . $record->created_at->locale('id')->translatedFormat('j M Y H.i'))
                    ->visible($tab('menunggu')),

                // Client and project made from the deal, both linked.
                Tables\Columns\TextColumn::make('pm_client')
                    ->label('Klien & proyek di PM')
                    ->state(fn (ClientIntake $record) => $record->client
                        ? $link(ClientResource::getUrl('view', ['record' => $record->client]), $record->client->name)
                        : 'Klien sudah dihapus')
                    ->html()
                    ->description(fn (ClientIntake $record) => new HtmlString(
                        ($record->matched ? 'Ditautkan ke klien lama' : 'Klien baru dibuat') . ' · proyek '
                        . ($record->project
                            ? $link(ProjectResource::getUrl('view', ['record' => $record->project]), $record->project->name)
                            : 'sudah dihapus')
                    ))
                    ->wrap()
                    ->visible($tab('selesai')),

                Tables\Columns\TextColumn::make('completed_at')
                    ->label('Diproses')
                    ->formatStateUsing(fn ($state) => $date($state))
                    ->description(fn (ClientIntake $record) => $record->completedBy?->name)
                    ->sortable()
                    ->visible($tab('selesai')),

                Tables\Columns\TextColumn::make('handoff')
                    ->label('Kabar ke CM')
                    ->badge()
                    ->state(fn (ClientIntake $record) => match ($record->handoffState()) {
                        'sent' => 'Terkirim',
                        'failed' => 'Gagal',
                        default => 'Dalam antrean',
                    })
                    ->color(fn (ClientIntake $record) => match ($record->handoffState()) {
                        'sent' => 'primary',
                        'failed' => 'danger',
                        default => 'gray',
                    })
                    ->description(fn (ClientIntake $record) => $record->handoffState() === 'failed' ? 'Kirim ulang di detail' : null)
                    ->visible($tab('selesai')),

                Tables\Columns\TextColumn::make('withdrawn_detail')
                    ->label('Alasan di CM')
                    ->state(fn (ClientIntake $record) => $record->withdrawn_detail ?: $record->withdrawn_reason)
                    ->description(fn (ClientIntake $record) => $record->withdrawn_note)
                    ->placeholder('–')
                    ->wrap()
                    ->visible($tab('batal')),

                Tables\Columns\TextColumn::make('withdrawn_at')
                    ->label('Dibatalkan')
                    ->formatStateUsing(fn ($state) => $date($state))
                    ->sortable()
                    ->visible($tab('batal')),

                Tables\Columns\TextColumn::make('withdrawn_pm')
                    ->label('Di PM')
                    ->state(fn (ClientIntake $record) => $record->isCompleted() ? 'Klien & proyek sudah dibuat' : 'Belum diproses')
                    ->badge()
                    ->color(fn (ClientIntake $record) => $record->isCompleted() ? 'danger' : 'gray')
                    ->description(fn (ClientIntake $record) => $record->isCompleted() ? 'Tinjau apakah proyek perlu dihentikan' : null)
                    ->visible($tab('batal')),
            ])
            ->actions([
                Tables\Actions\Action::make('proses')
                    ->label('Cek & proses')
                    ->icon('heroicon-m-arrow-right')
                    ->iconPosition('after')
                    ->button()
                    ->size('sm')
                    ->visible(fn (ClientIntake $record) => $record->isPending())
                    ->url(fn (ClientIntake $record) => $record->reviewUrl()),
                // Done or cancelled: the row opens the detail too, so an icon is enough.
                Tables\Actions\Action::make('lihat')
                    ->label('Lihat')
                    ->icon('heroicon-m-eye')
                    ->iconButton()
                    ->color('gray')
                    ->tooltip('Lihat detail')
                    ->visible(fn (ClientIntake $record) => ! $record->isPending())
                    ->url(fn (ClientIntake $record) => $record->reviewUrl()),
            ])
            ->emptyStateHeading(fn ($livewire) => match ($livewire->activeTab ?? 'menunggu') {
                'selesai' => 'Belum ada klien masuk yang diproses',
                'batal' => 'Tidak ada deal yang dibatalkan',
                default => 'Semua klien masuk sudah dicek',
            })
            ->emptyStateDescription(fn ($livewire) => ($livewire->activeTab ?? 'menunggu') === 'menunggu'
                ? 'Lead yang mencapai Deal di Client Management akan muncul di sini.'
                : null)
            ->emptyStateIcon('heroicon-o-inbox');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListClientIntakes::route('/'),
            'proses' => Pages\ProsesClientIntake::route('/{record}/proses'),
        ];
    }
}
