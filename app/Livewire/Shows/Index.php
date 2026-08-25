<?php

namespace App\Livewire\Shows;

use App\Concerns\Toasts;
use App\Models\Layout;
use App\Models\Show;
use App\Services\Shows\DefaultLayout;
use App\Support\Access;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Attributes\Validate;
use Livewire\Component;

#[Title('Broadcasts')]
class Index extends Component
{
    use Toasts;

    #[Validate('required|string|max:120')]
    public string $name = '';

    public string $layoutId = '';

    public bool $creating = false;

    public function mount(): void
    {
        DefaultLayout::ensureLayouts();
        $this->layoutId = (string) (Layout::query()->orderBy('sort_order')->orderBy('id')->value('id') ?? '');
    }

    /** @return Collection<int, Show> */
    #[Computed]
    public function shows(): Collection
    {
        return Show::with('layout')->withCount('looks')
            ->orderBy('name')
            ->get();
    }

    /** @return Collection<int, Layout> */
    #[Computed]
    public function layouts(): Collection
    {
        return Layout::query()
            ->withCount(['sections', 'textGroups'])
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();
    }

    public function create(): void
    {
        $this->authorize(Access::BROADCASTS_MANAGE);
        $this->validate([
            'name' => 'required|string|max:120',
            'layoutId' => 'required|integer|exists:layouts,id',
        ]);

        $layout = Layout::query()->with('sections')->findOrFail($this->layoutId);

        $show = Show::create([
            'name' => $this->name,
            'layout_id' => $layout->id,
        ]);

        DefaultLayout::install($show, $layout);

        $this->reset('name');
        $this->layoutId = (string) (Layout::query()->orderBy('sort_order')->orderBy('id')->value('id') ?? '');
        $this->creating = false;
        unset($this->shows);

        $this->toast("Created {$show->name}.");
    }

    /**
     * Copy a box: same sections, cue stack and this box's text defaults. Text
     * keys are already shared, so they are not copied. The new box gets its own
     * identifier and empty live state.
     */
    public function duplicate(int $showId): void
    {
        $this->authorize(Access::BROADCASTS_MANAGE);
        $show = Show::with(['sections', 'textDefaults'])->findOrFail($showId);

        $copy = Show::create([
            'name' => $show->name.' (copy)',
            'layout_id' => $show->layout_id,
        ]);

        DefaultLayout::copyFrom($show, $copy);

        foreach ($show->looks()->with('items')->get() as $look) {
            $newLook = $copy->looks()->create($look->only('name', 'kind', 'notes', 'sort_order'));

            foreach ($look->items as $item) {
                $newLook->items()->create($item->only('section_key', 'action', 'asset_id'));
            }
        }

        unset($this->shows);

        $this->toast("Duplicated as {$copy->name}.");
    }

    public function delete(int $showId): void
    {
        $this->authorize(Access::BROADCASTS_MANAGE);
        Show::findOrFail($showId)->delete();

        unset($this->shows);

        $this->toast('Broadcast deleted.');
    }
}
