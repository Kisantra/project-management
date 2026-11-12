<?php

namespace Tests\Unit\Ui;

use App\Models\ProjectStatus;
use App\Support\Ui\Tone;
use Tests\TestCase;

class ToneTest extends TestCase
{
    public function test_payment_status_uses_three_voices(): void
    {
        $this->assertSame(Tone::RED, Tone::forPaymentStatus('Kurang Bayar'));
        $this->assertSame(Tone::BLUE, Tone::forPaymentStatus('Lebih Bayar'));
        $this->assertSame(Tone::NEUTRAL, Tone::forPaymentStatus('Nihil'));
        $this->assertSame(Tone::NEUTRAL, Tone::forPaymentStatus(null));
    }

    public function test_report_and_client_status(): void
    {
        $this->assertSame(Tone::BLUE, Tone::forReportStatus('Sudah Lapor'));
        $this->assertSame(Tone::RED, Tone::forReportStatus('Belum Lapor'));
        $this->assertSame(Tone::BLUE, Tone::forClientStatus('Active'));
        $this->assertSame(Tone::NEUTRAL, Tone::forClientStatus('Inactive'));
    }

    public function test_project_status_follows_category_and_shape_not_colour(): void
    {
        $status = fn (string $category, string $shape) => new ProjectStatus(['category' => $category, 'shape' => $shape, 'color' => '#ff00ff']);

        $this->assertSame(Tone::NEUTRAL, Tone::forProjectStatus($status(ProjectStatus::CATEGORY_NOT_STARTED, 'empty')));
        $this->assertSame(Tone::BLUE, Tone::forProjectStatus($status(ProjectStatus::CATEGORY_ACTIVE, 'half')));
        $this->assertSame(Tone::OUTLINE, Tone::forProjectStatus($status(ProjectStatus::CATEGORY_ACTIVE, 'clock')));
        $this->assertSame(Tone::BLUE, Tone::forProjectStatus($status(ProjectStatus::CATEGORY_DONE, 'check')));
        $this->assertSame(Tone::NEUTRAL, Tone::forProjectStatus($status(ProjectStatus::CATEGORY_CLOSED, 'x')));
        $this->assertSame(Tone::NEUTRAL, Tone::forProjectStatus(null));
    }

    public function test_stored_project_status_tone_wins_over_category(): void
    {
        $status = new ProjectStatus(['category' => ProjectStatus::CATEGORY_CLOSED, 'shape' => 'x', 'tone' => Tone::RED]);

        $this->assertSame(Tone::RED, Tone::forProjectStatus($status));
        $this->assertSame(['color' => '#af3628', 'bg' => '#fff4f2'], Tone::swatch(Tone::RED));
        $this->assertSame('transparent', Tone::swatch(Tone::OUTLINE)['bg']);
        $this->assertSame(Tone::swatch(Tone::NEUTRAL), Tone::swatch('ungu'));
    }

    public function test_project_status_chart_is_an_ordinal_ramp_in_workflow_order(): void
    {
        $status = fn (string $key, string $category) => new ProjectStatus(['key' => $key, 'category' => $category, 'shape' => 'half']);
        $statuses = [
            $status('draft', ProjectStatus::CATEGORY_NOT_STARTED),
            $status('in_progress', ProjectStatus::CATEGORY_ACTIVE),
            $status('review', ProjectStatus::CATEGORY_ACTIVE),
            $status('completed', ProjectStatus::CATEGORY_DONE),
            $status('canceled', ProjectStatus::CATEGORY_CLOSED),
        ];

        $this->assertSame([
            'draft' => Tone::CHART_NOT_STARTED,
            'in_progress' => 'var(--kis-ramp-b1)',
            'review' => 'var(--kis-ramp-b4)',
            'completed' => 'var(--kis-ramp-b6)',
            'canceled' => Tone::CHART_CLOSED,
        ], Tone::projectStatusChart($statuses));

        $this->assertSame(['solo' => 'var(--kis-ramp-b4)'], Tone::projectStatusChart([$status('solo', ProjectStatus::CATEGORY_ACTIVE)]));
    }

    public function test_unknown_tone_falls_back_to_neutral_and_maps_to_filament_colours(): void
    {
        $this->assertSame(Tone::NEUTRAL, Tone::normalize('green'));
        $this->assertSame('primary', Tone::filamentColor(Tone::OUTLINE));
        $this->assertSame('danger', Tone::filamentColor(Tone::RED));
        $this->assertSame('gray', Tone::filamentColor(Tone::NEUTRAL));
    }
}
