<?php

namespace App\Livewire\ProjectStatuses;

use App\Models\ProjectStatus;
use App\Support\Ui\Tone;
use Filament\Notifications\Notification;
use Illuminate\Support\Str;
use Livewire\Component;
use Throwable;

class StatusManager extends Component
{
    // ─── Form state ─────────────────────────────────────
    public ?int $editingId = null;
    public ?string $editingCategory = null;
    public string $form_label = '';
    public string $form_tone = Tone::BLUE;
    public string $form_shape = 'half';

    public const SHAPES = ['empty', 'dashed', 'half', 'clock', 'check', 'x'];

    public const CATEGORY_LABELS = [
        'not_started' => 'Not Started',
        'active'      => 'Active',
        'done'        => 'Done',
        'closed'      => 'Closed',
    ];

    // ─── Listings ───────────────────────────────────────
    public function getGroupedProperty(): array
    {
        $byCategory = ProjectStatus::ordered()->get()->groupBy('category');
        $out = [];
        foreach (array_keys(self::CATEGORY_LABELS) as $cat) {
            $out[$cat] = $byCategory->get($cat, collect())->values();
        }
        return $out;
    }

    public function render()
    {
        return view('livewire.project-statuses.status-manager', [
            'grouped' => $this->grouped,
        ]);
    }

    // ─── Add ────────────────────────────────────────────
    public function startCreate(string $category): void
    {
        $this->resetForm();
        $this->editingId = null;
        $this->editingCategory = $category;
        $this->form_tone = Tone::defaultForProjectStatus($category, $this->form_shape);
        $this->dispatch('open-status-form');
    }

    public function save(): void
    {
        $this->validate([
            'form_label' => 'required|string|max:60',
            'form_tone' => 'required|in:' . implode(',', Tone::ALL),
            'form_shape' => 'required|in:' . implode(',', self::SHAPES),
            'editingCategory' => 'required|in:' . implode(',', array_keys(self::CATEGORY_LABELS)),
        ]);

        if ($this->editingId) {
            $status = ProjectStatus::find($this->editingId);
            if (!$status) {
                $this->cancel();
                return;
            }

            // System statuses can be edited (label, tone, shape) but
            // their key and category are locked. `color` is left alone: the
            // dashboard charts still read it.
            $payload = [
                'label' => $this->form_label,
                'tone'  => $this->form_tone,
                'shape' => $this->form_shape,
            ];
            if (! $status->is_system) {
                $payload['category'] = $this->editingCategory;
            }

            $status->update($payload);

            Notification::make()
                ->title("Status '{$status->label}' diperbarui")
                ->success()
                ->send();
        } else {
            $key = $this->makeUniqueKey($this->form_label);
            $sortOrder = (int) ProjectStatus::where('category', $this->editingCategory)->max('sort_order') + 1;

            $created = ProjectStatus::create([
                'key'        => $key,
                'label'      => $this->form_label,
                'color'      => Tone::swatch($this->form_tone)['color'],
                'tone'       => $this->form_tone,
                'shape'      => $this->form_shape,
                'category'   => $this->editingCategory,
                'sort_order' => $sortOrder,
                'is_system'  => false,
            ]);

            Notification::make()
                ->title("Status '{$created->label}' dibuat")
                ->success()
                ->send();
        }

        $this->cancel();
    }

    // ─── Edit ───────────────────────────────────────────
    public function startEdit(int $id): void
    {
        $status = ProjectStatus::find($id);
        if (!$status) return;

        $this->editingId = $status->id;
        $this->editingCategory = $status->category;
        $this->form_label = $status->label;
        $this->form_tone = Tone::forProjectStatus($status);
        $this->form_shape = $status->shape;

        $this->dispatch('open-status-form');
    }

    public function cancel(): void
    {
        $this->resetForm();
        $this->dispatch('close-status-form');
    }

    protected function resetForm(): void
    {
        $this->editingId = null;
        $this->editingCategory = null;
        $this->form_label = '';
        $this->form_tone = Tone::BLUE;
        $this->form_shape = 'half';
        $this->resetValidation();
    }

    // ─── Delete ─────────────────────────────────────────
    public function delete(int $id): void
    {
        $status = ProjectStatus::find($id);
        if (!$status) return;

        try {
            $status->delete();

            Notification::make()
                ->title("Status '{$status->label}' dihapus")
                ->success()
                ->send();
        } catch (Throwable $e) {
            Notification::make()
                ->title('Tidak bisa menghapus status')
                ->body($e->getMessage())
                ->danger()
                ->send();
        }
    }

    // ─── Reorder (called from SortableJS) ───────────────
    /**
     * Persist a new ordering for one category.
     * `$ids` is the array of status IDs in the new order.
     */
    public function reorder(string $category, array $ids): void
    {
        if (!array_key_exists($category, self::CATEGORY_LABELS)) return;

        $order = 1;
        foreach ($ids as $id) {
            $id = (int) $id;
            if ($id <= 0) continue;
            ProjectStatus::where('id', $id)
                ->where('category', $category) // safety: can't move statuses across categories via drag
                ->update(['sort_order' => $order++]);
        }
    }

    // ─── Helpers ────────────────────────────────────────
    /**
     * Build a slug-safe unique key from a label.
     * Appends -2, -3 etc. if needed.
     */
    protected function makeUniqueKey(string $label): string
    {
        $base = Str::slug($label, '_');
        $base = $base === '' ? 'status' : $base;
        $base = substr($base, 0, 50);

        $candidate = $base;
        $suffix = 2;
        while (ProjectStatus::where('key', $candidate)->exists()) {
            $candidate = $base . '_' . $suffix++;
        }
        return $candidate;
    }
}
