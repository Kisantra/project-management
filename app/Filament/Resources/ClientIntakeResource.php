<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ClientIntakeResource\Pages;
use App\Models\ClientIntake;
use Filament\Resources\Resource;
use Filament\Support\Enums\FontWeight;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

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

    public static function getNavigationBadgeColor(): ?string
    {
        return 'warning';
    }

    public static function getNavigationBadgeTooltip(): ?string
    {
        return 'Menunggu dicek';
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->with(['suggestedClient:id,name', 'client:id,name']))
            ->defaultSort('created_at', 'desc')
            ->recordUrl(fn (ClientIntake $record) => $record->reviewUrl())
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Klien')
                    ->weight(FontWeight::Medium)
                    ->searchable()
                    ->sortable()
                    ->description(fn (ClientIntake $record) => collect([$record->entity, $record->sent('client_type')])->filter()->implode(' · ') ?: null),

                Tables\Columns\TextColumn::make('service')
                    ->label('Layanan')
                    ->searchable()
                    ->placeholder('—'),

                Tables\Columns\TextColumn::make('deal_value')
                    ->label('Nilai deal')
                    ->formatStateUsing(fn (?int $state) => ClientIntake::formatRupiah($state))
                    ->placeholder('—')
                    ->alignEnd()
                    ->sortable(),

                Tables\Columns\TextColumn::make('marketing_owner')
                    ->label('PJ marketing')
                    ->searchable()
                    ->placeholder('—'),

                Tables\Columns\TextColumn::make('deal_at')
                    ->label('Tanggal deal')
                    ->date('j M Y')
                    ->placeholder('—')
                    ->sortable(),

                // Menunggu: saran pencocokan. Selesai: klien yang dibuat/ditautkan.
                Tables\Columns\TextColumn::make('pm_client')
                    ->label('Klien di PM')
                    ->state(fn (ClientIntake $record) => match (true) {
                        $record->client !== null => ($record->matched ? 'Ditautkan ke ' : 'Dibuat: ') . $record->client->name,
                        $record->suggestedClient !== null => 'Mungkin klien lama: ' . $record->suggestedClient->name,
                        default => null,
                    })
                    ->color(fn (ClientIntake $record) => $record->client ? 'gray' : 'warning')
                    ->placeholder('Klien baru')
                    ->wrap(),

                Tables\Columns\TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->state(fn (ClientIntake $record) => $record->statusLabel())
                    ->color(fn (ClientIntake $record) => match (true) {
                        $record->isWithdrawn() => 'danger',
                        $record->isCompleted() => 'success',
                        default => 'warning',
                    }),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Diterima')
                    ->since()
                    ->sortable(),
            ])
            ->actions([
                Tables\Actions\Action::make('proses')
                    ->label(fn (ClientIntake $record) => $record->isPending() ? 'Cek & proses' : 'Lihat')
                    ->icon(fn (ClientIntake $record) => $record->isPending() ? 'heroicon-m-arrow-right-circle' : 'heroicon-m-eye')
                    ->color(fn (ClientIntake $record) => $record->isPending() ? 'primary' : 'gray')
                    ->button(fn (ClientIntake $record) => $record->isPending())
                    ->url(fn (ClientIntake $record) => $record->reviewUrl()),
            ])
            ->emptyStateHeading('Belum ada klien masuk')
            ->emptyStateDescription('Lead yang mencapai Deal di Client Management akan muncul di sini.')
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
