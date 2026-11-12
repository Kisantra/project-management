<?php

namespace App\Services\TaxReport;

use App\Filament\Resources\TaxReportResource;
use App\Livewire\Clients\ClientListClickup;
use App\Models\Client;
use App\Models\Employee;
use App\Models\IncomeTax;
use App\Models\TaxCalculationSummary;
use App\Models\TaxReport;
use App\Support\TaxPeriod;
use App\Support\Ui\ClientAvatar;
use App\Support\Ui\Tone;
use Illuminate\Support\Collection;

/**
 * Everything the tax report page shows above the tax-type workspaces:
 * client header, period navigation, and one summary cell per tax type.
 * Reporting progress counts only the tax types the client has contracted.
 */
final class ReportOverview
{
    /** tax type => [label, client contract column, URL key] in display order */
    public const TYPES = [
        'ppn' => ['PPN', 'ppn_contract', 'ppn'],
        'pph' => ['PPh', 'pph_contract', 'pph'],
        'bupot' => ['PPh Unifikasi', 'bupot_contract', 'unifikasi'],
        'pph_badan' => ['PPh Badan', 'pph_badan_contract', 'badan'],
    ];

    /** Only these tabs lock without a contract; PPN and PPh stay open so existing data stays reachable. */
    public const LOCKABLE = ['bupot', 'pph_badan'];

    public const REPORTED = 'reported';

    public const NO_ACTIVITY = 'no_activity';

    public const PENDING = 'pending';

    public const NOT_CONTRACTED = 'not_contracted';

    private ?Collection $clientReports = null;

    private ?Collection $summaries = null;

    private function __construct(public readonly TaxReport $report)
    {
    }

    public static function for(TaxReport $report): self
    {
        $report->loadMissing(['client', 'taxCalculationSummaries']);

        return new self($report);
    }

    public function year(): int
    {
        return (int) ($this->report->year ?? $this->report->created_at->year);
    }

    public function monthIndex(): int
    {
        return TaxPeriod::index($this->report->month);
    }

    public function periodLabel(): string
    {
        return TaxPeriod::label($this->report->month, $this->year());
    }

    /** URL key of a tab (ppn, pph, unifikasi, badan) for the tax type. */
    public static function urlKey(string $type): string
    {
        return self::TYPES[$type][2];
    }

    public function client(): array
    {
        $client = $this->report->client;

        return [
            'name' => $client->name,
            'npwp' => ClientListClickup::formatNpwp($client->NPWP),
            'initials' => ClientAvatar::initials($client->name),
            'logo' => ClientAvatar::url($client->logo),
            'contracts' => array_values(array_map(fn ($type) => self::TYPES[$type][0], $this->contracted())),
        ];
    }

    /** @return list<string> contracted tax types, in display order */
    public function contracted(): array
    {
        $client = $this->report->client;

        return array_values(array_filter(array_keys(self::TYPES), fn ($type) => (bool) $client->{self::TYPES[$type][1]}));
    }

    public function isLocked(string $type): bool
    {
        return in_array($type, self::LOCKABLE, true) && ! in_array($type, $this->contracted(), true);
    }

    /** URL keys of the tabs that can be opened. */
    public function openTabs(): array
    {
        return array_values(array_map(
            fn ($type) => self::urlKey($type),
            array_filter(array_keys(self::TYPES), fn ($type) => ! $this->isLocked($type)),
        ));
    }

    public function summary(string $type): ?TaxCalculationSummary
    {
        $this->summaries ??= $this->report->taxCalculationSummaries->keyBy('tax_type');

        return $this->summaries->get($type);
    }

    /** reported | no_activity | pending, from a summary row (Sudah Lapor covers "tidak ada aktivitas"). */
    public static function statusOf(?TaxCalculationSummary $summary): string
    {
        if ($summary?->report_status !== 'Sudah Lapor') {
            return self::PENDING;
        }

        return $summary->no_activity ? self::NO_ACTIVITY : self::REPORTED;
    }

    public static function statusLabel(string $status): string
    {
        return match ($status) {
            self::REPORTED => 'Sudah lapor',
            self::NO_ACTIVITY => 'Tidak ada aktivitas',
            self::NOT_CONTRACTED => 'Tidak dikontrak',
            default => 'Belum lapor',
        };
    }

    public static function statusTone(string $status): string
    {
        return match ($status) {
            self::REPORTED => Tone::BLUE,
            self::PENDING => Tone::RED,
            default => Tone::NEUTRAL,
        };
    }

    /** "{n} dari {m} jenis sudah lapor", counting contracted types only. */
    public function progress(): array
    {
        $contracted = $this->contracted();
        $done = count(array_filter($contracted, fn ($type) => self::statusOf($this->summary($type)) !== self::PENDING));

        return ['done' => $done, 'total' => count($contracted)];
    }

    /** One summary cell per tax type for the strip (doubles as the type tabs). */
    public function cards(): array
    {
        $contracted = $this->contracted();
        $cards = [];

        foreach (self::TYPES as $type => [$label]) {
            $summary = $this->summary($type);
            $status = in_array($type, $contracted, true) ? self::statusOf($summary) : self::NOT_CONTRACTED;

            $cards[$type] = [
                'type' => $type,
                'key' => self::urlKey($type),
                'label' => $label,
                'locked' => $this->isLocked($type),
                'status' => $status,
                'statusLabel' => self::statusLabel($status),
                'tone' => self::statusTone($status),
            ] + $this->figure($type, $summary, $status);
        }

        return $cards;
    }

    private function figure(string $type, ?TaxCalculationSummary $summary, string $status): array
    {
        $reportedOn = ($date = TaxPeriod::date($summary?->reported_at)) ? 'Dilapor ' . $date : null;
        $spt = $summary?->bukti_lapor ? 'SPT sudah diunggah' : 'SPT belum diunggah';

        if ($status === self::NOT_CONTRACTED && $this->isLocked($type)) {
            return ['value' => '–', 'note' => null, 'noteTone' => null, 'context' => 'Kontrak belum aktif untuk klien ini'];
        }

        switch ($type) {
            case 'ppn':
                $final = $summary?->status_final ?: 'Nihil';

                return [
                    'value' => self::rupiah(abs((float) ($summary?->saldo_final ?? 0))),
                    'note' => mb_strtolower($final) === 'nihil' ? 'Nihil' : ucfirst(mb_strtolower($final)),
                    'noteTone' => Tone::forPaymentStatus($final),
                    'context' => number_format($this->report->invoices()->count(), 0, ',', '.') . ' faktur · ' . ($reportedOn ?? $spt),
                ];

            case 'pph':
                $pph21 = IncomeTax::where('tax_report_id', $this->report->id)->where('jenis_pajak', 'Pasal 21')
                    ->selectRaw('COUNT(*) as n, COALESCE(SUM(pajak_penghasilan), 0) as total')->first();
                $employees = Employee::where('client_id', $this->report->client_id)->where('status', 'active')->count();

                return [
                    'value' => self::rupiah((float) $pph21->total),
                    'note' => 'PPh 21',
                    'noteTone' => null,
                    'context' => $pph21->n . ' bukti potong · ' . $employees . ' karyawan aktif',
                ];

            default:
                $count = $type === 'bupot' ? $this->report->bupots()->count() : null;

                return [
                    'value' => $count === null ? '–' : (string) $count,
                    'note' => $count === null ? null : 'bupot',
                    'noteTone' => null,
                    'context' => match ($status) {
                        self::REPORTED => $reportedOn ?? 'SPT sudah diunggah',
                        self::NO_ACTIVITY => 'Ditandai tidak ada aktivitas',
                        default => $type === 'bupot' ? 'Konfirmasi ada atau tidak ada pemotongan' : 'Konfirmasi ada atau tidak ada aktivitas',
                    },
                ];
        }
    }

    /** Every report of this client with its summaries and data counts (one query). */
    private function clientReports(): Collection
    {
        return $this->clientReports ??= TaxReport::where('client_id', $this->report->client_id)
            ->with(['taxCalculationSummaries:id,tax_report_id,tax_type,report_status,no_activity'])
            ->withCount(['invoices', 'incomeTaxs', 'bupots'])
            ->get(['id', 'client_id', 'month', 'year', 'created_at'])
            ->each(function (TaxReport $report) {
                $report->setAttribute('period_year', (int) ($report->year ?? $report->created_at->year));
                $report->setAttribute('period_month', TaxPeriod::index($report->month));
            })
            ->sortBy(fn (TaxReport $report) => $report->period_year * 100 + $report->period_month)
            ->values();
    }

    private static function url(TaxReport $report): string
    {
        return TaxReportResource::getUrl('view', ['record' => $report->id]);
    }

    /** Years with reports, newest first; each links to its first month. */
    public function years(): array
    {
        return $this->clientReports()
            ->groupBy('period_year')
            ->sortKeysDesc()
            ->map(fn (Collection $reports, int $year) => [
                'year' => $year,
                'count' => $reports->count(),
                'url' => self::url($reports->first()),
                'current' => $year === $this->year(),
            ])
            ->values()
            ->all();
    }

    /** Twelve cells for the selected year: state, link and tooltip. */
    public function months(): array
    {
        $contracted = $this->contracted();
        $byMonth = $this->clientReports()->where('period_year', $this->year())->keyBy('period_month');
        $cells = [];

        foreach (TaxPeriod::SHORT as $number => $short) {
            $report = $byMonth->get($number);
            $cell = [
                'number' => $number,
                'short' => $short,
                'name' => TaxPeriod::NAMES[$number],
                'current' => $number === $this->monthIndex(),
                'url' => $report ? self::url($report) : null,
                'state' => 'nodata',
                'activity' => false,
                'tip' => null,
            ];

            if ($report) {
                $summaries = $report->taxCalculationSummaries->keyBy('tax_type');
                $statuses = array_map(fn ($type) => self::statusOf($summaries->get($type)), $contracted);
                $cell['state'] = $contracted === [] ? 'nocontract' : (in_array(self::PENDING, $statuses, true) ? 'pending' : 'done');
                $cell['activity'] = $report->invoices_count + $report->income_taxs_count + $report->bupots_count > 0;

                $rows = [];
                foreach ($contracted as $i => $type) {
                    $rows[] = [self::tipColor($statuses[$i]), self::TYPES[$type][0], self::statusLabel($statuses[$i])];
                }
                $rows[] = ['', 'Faktur', (string) $report->invoices_count];
                $rows[] = ['', 'PPh', (string) $report->income_taxs_count];
                $rows[] = ['', 'Bupot', (string) $report->bupots_count];
                $cell['tip'] = ['t' => TaxPeriod::label($number, $this->year()), 'r' => $rows];
            }

            $cells[] = $cell;
        }

        return $cells;
    }

    private static function tipColor(string $status): string
    {
        return match ($status) {
            self::REPORTED => 'var(--kis-ramp-b2)',
            self::PENDING => 'rgb(var(--danger-500))',
            default => 'var(--kis-ramp-n1)',
        };
    }

    /** Previous and next report of this client, across years. */
    public function neighbours(): array
    {
        $reports = $this->clientReports();
        $at = $reports->search(fn (TaxReport $report) => $report->id === $this->report->id);
        $link = fn (?TaxReport $report) => $report ? [
            'label' => TaxPeriod::label($report->period_month, $report->period_year),
            'url' => self::url($report),
        ] : null;

        return [
            'prev' => $at === false ? null : $link($reports->get($at - 1)),
            'next' => $at === false ? null : $link($reports->get($at + 1)),
        ];
    }

    /** Clients that also have a report for this period, for the client switcher. */
    public function switcher(): array
    {
        $reports = TaxReport::where('month', $this->report->month)
            ->where('year', $this->report->year)
            ->get(['id', 'client_id'])
            ->keyBy('client_id');

        return Client::whereIn('id', $reports->keys())
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn (Client $client) => [
                'id' => $client->id,
                'name' => $client->name,
                'current' => $client->id === $this->report->client_id,
                'url' => self::url($reports->get($client->id)),
            ])
            ->values()
            ->all();
    }

    public static function rupiah(float $amount): string
    {
        return 'Rp ' . number_format($amount, 0, ',', '.');
    }
}
