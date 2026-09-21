<?php

namespace App\Filament\Pages\Letters;

use App\Models\LetterNumberCounter;
use App\Models\LetterNumberingSetting;
use App\Models\LetterTemplate;
use App\Services\LetterService;
use Filament\Forms;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Facades\DB;

/**
 * Pengaturan penomoran surat: format, kode kantor, reset, dan penghitung.
 */
class NumberingSettings extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-hashtag';
    protected static ?string $navigationGroup = 'Penyuratan';
    protected static ?string $navigationLabel = 'Pengaturan Nomor';
    protected static ?int $navigationSort = 4;
    protected static ?string $slug = 'surat/pengaturan-nomor';
    protected static ?string $title = 'Pengaturan Penomoran Surat';
    protected static string $view = 'filament.pages.letters.numbering-settings';

    public ?array $data = [];

    public static function shouldRegisterNavigation(): bool
    {
        return auth()->user()->hasAnyRole(['super-admin', 'direktur']);
    }

    public static function canAccess(): bool
    {
        return auth()->user()->hasAnyRole(['super-admin', 'direktur']);
    }

    public function getBreadcrumbs(): array
    {
        return [
            Index::getUrl() => 'Surat & Berita Acara',
            '#' => 'Pengaturan penomoran',
        ];
    }

    public function mount(): void
    {
        $setting = LetterNumberingSetting::current();

        $this->form->fill([
            'number_format'       => $setting->number_format,
            'office_code'         => $setting->office_code,
            'sequence_digits'     => $setting->sequence_digits,
            'reset_period'        => $setting->reset_period,
            'sequence_per_prefix' => $setting->sequence_per_prefix,
            'counters'            => LetterNumberCounter::query()
                ->orderByDesc('period_key')->orderBy('prefix')
                ->get(['id', 'prefix', 'period_key', 'last_sequence'])
                ->toArray(),
        ]);
    }

    public function form(Form $form): Form
    {
        $prefixes = LetterTemplate::query()->distinct()->orderBy('number_prefix')->pluck('number_prefix')->all();

        return $form->statePath('data')->schema([
            Forms\Components\Section::make('Format nomor')
                ->description('Nomor diberikan otomatis saat surat diajukan. Draft tidak memakai nomor.')
                ->columns(2)
                ->schema([
                    Forms\Components\TextInput::make('number_format')
                        ->label('Pola')
                        ->required()
                        ->rule('regex:/\{seq\}/')
                        ->validationMessages(['regex' => 'Pola harus memuat {seq}.'])
                        ->live(onBlur: true)
                        ->columnSpanFull()
                        ->helperText(new \Illuminate\Support\HtmlString(
                            collect(LetterNumberingSetting::TOKENS)
                                ->map(fn ($desc, $token) => '<code class="rounded bg-gray-100 px-1 dark:bg-white/10">' . e($token) . '</code> ' . e($desc))
                                ->join(' &middot; ')
                        )),
                    Forms\Components\TextInput::make('office_code')->label('Kode kantor')->required()->maxLength(16)->live(onBlur: true),
                    Forms\Components\TextInput::make('sequence_digits')->label('Jumlah digit nomor urut')->numeric()->minValue(1)->maxValue(6)->required()->live(onBlur: true),
                    Forms\Components\Select::make('reset_period')->label('Nomor urut diulang')->options(LetterNumberingSetting::RESET_PERIODS)->required()->live(),
                    Forms\Components\Toggle::make('sequence_per_prefix')
                        ->label('Hitung terpisah per awalan')
                        ->helperText('Aktif: S-001 dan BA-001 berjalan sendiri-sendiri. Nonaktif: satu urutan bersama untuk semua awalan.')
                        ->inline(false)
                        ->live(),
                    Forms\Components\Placeholder::make('preview')
                        ->label('Contoh nomor berikutnya')
                        ->columnSpanFull()
                        ->content(fn (Forms\Get $get) => new \Illuminate\Support\HtmlString($this->previewHtml($get, $prefixes))),
                ]),

            Forms\Components\Section::make('Penghitung')
                ->description('Nomor urut terakhir yang sudah dipakai per awalan dan periode. Ubah bila perlu menyambung dari register lama; nomor berikutnya adalah nilai ini ditambah satu. Baris untuk periode baru dibuat otomatis saat pertama kali dipakai.')
                ->schema([
                    Forms\Components\Repeater::make('counters')
                        ->label('')
                        ->schema([
                            Forms\Components\TextInput::make('prefix')->label('Awalan')->required()->maxLength(8)
                                ->datalist(array_merge($prefixes, ['*']))
                                ->helperText('Gunakan * untuk urutan bersama.'),
                            Forms\Components\TextInput::make('period_key')->label('Periode')->required()->maxLength(8)
                                ->placeholder(now()->format('Y'))
                                ->helperText('2026, 2026-09, atau all'),
                            Forms\Components\TextInput::make('last_sequence')
                                ->label('Nomor terakhir dipakai')
                                ->numeric()
                                ->required()
                                ->live(onBlur: true)
                                // Never wind the counter below a number that is already printed on a letter:
                                // the next submit would collide with it (letters.number is unique).
                                ->minValue(fn (Forms\Get $get) => $this->highestIssued($get('prefix'), $get('period_key')))
                                ->validationMessages(['min' => 'Tidak boleh di bawah nomor yang sudah terbit (:min).'])
                                ->helperText(function (Forms\Get $get) {
                                    $issued = $this->highestIssued($get('prefix'), $get('period_key'));

                                    return $issued > 0
                                        ? "Nomor tertinggi yang sudah terbit di periode ini: {$issued}. Nilai di bawah itu ditolak."
                                        : 'Belum ada surat bernomor di periode ini.';
                                }),
                            Forms\Components\Hidden::make('id'),
                        ])
                        ->columns(3)
                        ->addActionLabel('Tambah penghitung')
                        ->reorderable(false)
                        ->itemLabel(fn (array $state) => trim(($state['prefix'] ?? '') . ' · ' . ($state['period_key'] ?? ''), ' ·') ?: 'Penghitung')
                        ->collapsed(false),
                ]),
        ]);
    }

    /** Highest sequence already stamped on a letter for this counter row (0 when none). */
    protected function highestIssued(?string $prefix, ?string $periodKey): int
    {
        $prefix = trim((string) $prefix);
        $periodKey = trim((string) $periodKey);

        if ($prefix === '' || $periodKey === '') {
            return 0;
        }

        return app(LetterService::class)->highestIssuedForCounter($prefix, $periodKey);
    }

    protected function previewHtml(Forms\Get $get, array $prefixes): string
    {
        $setting = new LetterNumberingSetting([
            'number_format'       => (string) ($get('number_format') ?: '{seq}'),
            'office_code'         => (string) $get('office_code'),
            'sequence_digits'     => (int) ($get('sequence_digits') ?: 3),
            'reset_period'        => (string) ($get('reset_period') ?: 'yearly'),
            'sequence_per_prefix' => (bool) $get('sequence_per_prefix'),
        ]);

        $service = app(LetterService::class);
        $rows = collect($prefixes ?: ['S'])->map(function ($prefix) use ($setting, $service) {
            $next = $service->peekNextSequence($prefix, now(), $setting);

            return '<span class="inline-flex items-center gap-2 rounded-full bg-gray-100 px-3 py-1 text-sm dark:bg-white/10">'
                . '<span class="text-gray-500 dark:text-gray-400">' . e($prefix) . '</span>'
                . '<span class="font-mono font-semibold">' . e($setting->format($prefix, $next, now())) . '</span>'
                . '</span>';
        })->join(' ');

        return '<div class="flex flex-wrap gap-2">' . $rows . '</div>';
    }

    public function save(): void
    {
        $data = $this->form->getState();

        DB::transaction(function () use ($data) {
            LetterNumberingSetting::current()->update([
                'number_format'       => $data['number_format'],
                'office_code'         => $data['office_code'],
                'sequence_digits'     => (int) $data['sequence_digits'],
                'reset_period'        => $data['reset_period'],
                'sequence_per_prefix' => (bool) $data['sequence_per_prefix'],
            ]);

            $keepIds = [];
            foreach ($data['counters'] ?? [] as $row) {
                $counter = LetterNumberCounter::updateOrCreate(
                    ['prefix' => trim($row['prefix']), 'period_key' => trim($row['period_key'])],
                    ['last_sequence' => (int) $row['last_sequence']],
                );
                $keepIds[] = $counter->id;
            }
            LetterNumberCounter::whereNotIn('id', $keepIds)->delete();
        });

        $this->mount();

        Notification::make()->title('Pengaturan penomoran disimpan')->success()->send();
    }

    protected function getFormActions(): array
    {
        return [];
    }
}
