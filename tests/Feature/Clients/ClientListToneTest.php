<?php

namespace Tests\Feature\Clients;

use App\Livewire\Clients\ClientListClickup;
use App\Models\User;
use App\Support\Ui\Tone;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Livewire\Livewire;
use Tests\TestCase;

/** Client list badges use the design-system tones; filters show as kis chips. */
class ClientListToneTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();

        config(['broadcasting.default' => 'null']);
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        $this->actingAs(User::forceCreate([
            'name' => 'Uji Klien',
            'email' => 'uji-klien-' . uniqid() . '@example.test',
            'password' => bcrypt('rahasia'),
            'status' => 'active',
        ]));
    }

    public function test_every_badge_has_a_known_tone_with_its_swatch(): void
    {
        foreach ([ClientListClickup::STATUSES, ClientListClickup::TYPES, ClientListClickup::PKP_STATUSES] as $metas) {
            foreach ($metas as $key => $meta) {
                $this->assertContains($meta['tone'], Tone::ALL, $key);
                $this->assertSame(Tone::swatch($meta['tone']), ['color' => $meta['color'], 'bg' => $meta['bg']], $key);
            }
        }

        $this->assertSame(Tone::BLUE, ClientListClickup::STATUSES['Active']['tone']);
        $this->assertSame(Tone::OUTLINE, ClientListClickup::PKP_STATUSES['PKP']['tone']);
        $this->assertSame([Tone::NEUTRAL], array_values(array_unique(array_column(ClientListClickup::TYPES, 'tone'))));
    }

    public function test_default_filter_shows_as_chip_without_a_reset_link(): void
    {
        Livewire::test(ClientListClickup::class)
            ->assertSee('Filter aktif')
            ->assertSee('Status: Active')
            ->assertSee('kis-badge--blue', false)
            ->assertDontSee('kis-active-filters__reset', false)
            ->set('typeFilter', ['Badan'])
            ->assertSee('Tipe: Badan')
            ->assertSee('Kembalikan bawaan')
            ->call('clearFilters')
            ->assertSet('statusFilter', ['Active'])
            ->assertSet('typeFilter', []);
    }
}
