<?php

namespace Tests\Feature\Ui;

use Tests\TestCase;

class KisComponentsTest extends TestCase
{
    public function test_badge_renders_tone_dot_and_falls_back_to_neutral(): void
    {
        $this->blade('<x-kis.badge tone="red" dot>Kurang Bayar</x-kis.badge>')
            ->assertSee('kis-badge kis-badge--red kis-badge--dot', false)
            ->assertSee('Kurang Bayar');

        $this->blade('<x-kis.badge tone="purple">Lainnya</x-kis.badge>')
            ->assertSee('kis-badge--neutral', false)
            ->assertDontSee('kis-badge--purple', false);
    }

    public function test_filter_rail_is_modelable_and_renders_every_option_on_the_server(): void
    {
        $view = $this->blade(
            '<x-kis.filter-rail wire:model.live="sop" label="SOP" :all-count="10" :options="$options" />',
            ['options' => [
                ['value' => 1, 'label' => 'Laporan Keuangan', 'count' => 7],
                ['value' => 2, 'label' => 'SP2DK <1>', 'count' => 3],
            ]],
        );

        $view->assertSee('x-modelable="value"', false)
            ->assertSee('wire:model.live="sop"', false)
            ->assertSee('role="radiogroup"', false)
            ->assertSee('Laporan Keuangan')
            ->assertSee('SP2DK &lt;1&gt;', false)
            ->assertDontSee('<x-for', false);
    }

    public function test_filter_rail_multiple_uses_toggle_buttons_and_checkboxes(): void
    {
        $html = (string) $this->blade(
            '<x-kis.filter-rail wire:model.live="sopFilter" label="SOP" multiple :options="$options" />',
            ['options' => [['value' => '1', 'label' => 'Laporan Keuangan', 'count' => 7], ['value' => 'none', 'label' => 'Tanpa SOP', 'count' => 2]]],
        );

        $this->assertStringContainsString('multiple: true', $html);
        $this->assertStringContainsString('role="group"', $html);
        $this->assertStringContainsString('x-bind:aria-pressed=', $html);
        $this->assertStringNotContainsString('role="radio"', $html);
        $this->assertStringContainsString('aria-multiselectable="true"', $html);
        $this->assertStringContainsString('kis-rail__radio--check', $html);
    }

    public function test_month_picker_has_twelve_months_and_presets(): void
    {
        $view = $this->blade(
            '<x-kis.month-picker wire:model.live="period" :counts="$counts" />',
            ['counts' => [2026 => array_combine(range(1, 12), array_fill(0, 12, 1))]],
        );

        $view->assertSee('x-modelable="value"', false)
            ->assertSee('toggleMonth(12, $event)', false)
            ->assertSee('Setahun')
            ->assertSee('Shift');
    }

    public function test_filter_chip_moves_click_handler_to_the_remove_button(): void
    {
        $html = (string) $this->blade('<x-kis.filter-chip wire:click="clearPeriod" data-test="chip">Periode</x-kis.filter-chip>');

        $this->assertMatchesRegularExpression('/<span class="kis-filter-chip" data-test="chip">/', $html);
        $this->assertMatchesRegularExpression('/<button[^>]*kis-filter-chip__remove[^>]*wire:click="clearPeriod"/', $html);
        $this->assertDoesNotMatchRegularExpression('/<span[^>]*wire:click/', $html);
    }

    public function test_group_row_renders_label_count_and_aside(): void
    {
        $this->blade('<x-kis.group-row :colspan="7" label="Oktober 2026" :count="2" count-label="laporan"><x-slot name="aside">1 belum lapor</x-slot></x-kis.group-row>')
            ->assertSee('colspan="7"', false)
            ->assertSee('Oktober 2026')
            ->assertSee('2 laporan')
            ->assertSee('kis-group-row__aside', false);
    }
}
