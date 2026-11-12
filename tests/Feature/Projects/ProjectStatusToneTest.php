<?php

namespace Tests\Feature\Projects;

use App\Livewire\Projects\ProjectListClickup;
use App\Livewire\ProjectStatuses\StatusManager;
use App\Models\ProjectStatus;
use App\Models\User;
use App\Support\Ui\Tone;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Cache;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/** Project statuses pick a design-system tone instead of a free colour. */
class ProjectStatusToneTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();

        config(['broadcasting.default' => 'null']);
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        Cache::forget('project_list_status_map');

        $user = User::forceCreate([
            'name' => 'Uji Status',
            'email' => 'uji-status-' . uniqid() . '@example.test',
            'password' => bcrypt('rahasia'),
            'status' => 'active',
        ]);
        Role::findOrCreate('super-admin', 'web');
        $user->assignRole('super-admin');
        $this->actingAs($user);
    }

    protected function tearDown(): void
    {
        Cache::forget('project_list_status_map');
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        parent::tearDown();
    }

    private function makeStatus(array $attributes = []): ProjectStatus
    {
        return ProjectStatus::forceCreate($attributes + [
            'key' => 'uji_' . uniqid(),
            'label' => 'Uji Status',
            'color' => '#123456',
            'tone' => Tone::BLUE,
            'shape' => 'half',
            'category' => ProjectStatus::CATEGORY_ACTIVE,
            'sort_order' => 99,
            'is_system' => false,
        ]);
    }

    public function test_status_page_saves_tone_and_leaves_chart_colour(): void
    {
        $status = $this->makeStatus();

        Livewire::test(StatusManager::class)
            ->call('startEdit', $status->id)
            ->assertSet('form_tone', Tone::BLUE)
            ->set('form_tone', Tone::RED)
            ->call('save')
            ->assertHasNoErrors();

        $status->refresh();
        $this->assertSame(Tone::RED, $status->tone);
        $this->assertSame('#123456', $status->color, 'dashboard charts still read color');
    }

    public function test_status_page_rejects_unknown_tone(): void
    {
        $status = $this->makeStatus();

        Livewire::test(StatusManager::class)
            ->call('startEdit', $status->id)
            ->set('form_tone', 'ungu')
            ->call('save')
            ->assertHasErrors(['form_tone']);
    }

    public function test_new_status_suggests_tone_from_category_and_takes_colour_from_tone(): void
    {
        $label = 'Uji Menunggu ' . uniqid();

        Livewire::test(StatusManager::class)
            ->call('startCreate', ProjectStatus::CATEGORY_NOT_STARTED)
            ->assertSet('form_tone', Tone::NEUTRAL)
            ->set('form_label', $label)
            ->set('form_tone', Tone::OUTLINE)
            ->call('save')
            ->assertHasNoErrors();

        $created = ProjectStatus::where('label', $label)->firstOrFail();
        $this->assertSame(Tone::OUTLINE, $created->tone);
        $this->assertSame(Tone::swatch(Tone::OUTLINE)['color'], $created->color);
    }

    public function test_project_list_paints_statuses_from_tone_not_stored_colour(): void
    {
        $status = $this->makeStatus(['tone' => Tone::RED, 'color' => '#ff00ff']);

        $map = Livewire::test(ProjectListClickup::class)->instance()->statusMap;

        $this->assertSame(Tone::RED, $map[$status->key]['tone']);
        $this->assertSame(Tone::swatch(Tone::RED)['color'], $map[$status->key]['color']);
        $this->assertSame(Tone::swatch(Tone::RED)['bg'], $map[$status->key]['bg']);
    }

    public function test_project_list_status_editor_saves_tone(): void
    {
        $status = $this->makeStatus();

        Livewire::test(ProjectListClickup::class)
            ->call('startEditStatus', $status->id)
            ->assertSet('editTone', Tone::BLUE)
            ->set('editTone', Tone::NEUTRAL)
            ->call('saveStatusEdit')
            ->assertHasNoErrors();

        $status->refresh();
        $this->assertSame(Tone::NEUTRAL, $status->tone);
        $this->assertSame('#123456', $status->color);
    }
}
