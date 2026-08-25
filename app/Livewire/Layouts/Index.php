<?php

namespace App\Livewire\Layouts;

use App\Concerns\Toasts;
use App\Models\Layout;
use App\Services\Shows\DefaultLayout;
use App\Support\Access;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Title('Layouts')]
class Index extends Component
{
    use Toasts;

    public bool $creating = false;

    public string $name = '';

    public string $description = '';

    public string $sourceLayoutId = '';

    public function mount(): void
    {
        DefaultLayout::ensureLayouts();
    }

    /** @return Collection<int, Layout> */
    #[Computed]
    public function layouts(): Collection
    {
        return Layout::query()
            ->with(['sections', 'textGroups'])
            ->withCount('shows')
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();
    }

    public function create(): void
    {
        $this->authorize(Access::LAYOUTS_EDIT);
        $this->validate([
            'name' => ['required', 'string', 'max:120'],
            'description' => ['nullable', 'string', 'max:240'],
            'sourceLayoutId' => ['nullable', 'string'],
        ]);

        $layout = Layout::query()->create([
            'name' => $this->name,
            'description' => $this->description ?: null,
            'sort_order' => (int) Layout::query()->max('sort_order') + 1,
        ]);

        if ($this->sourceLayoutId !== '') {
            $source = Layout::query()->with(['sections', 'textGroups.textKeys'])->find($this->sourceLayoutId);

            if ($source) {
                $layout->copyStructureFrom($source);
            }
        }

        $this->reset('name', 'description', 'sourceLayoutId', 'creating');

        $this->redirect(route('layouts.edit', $layout), navigate: true);
    }

    public function duplicate(int $layoutId): void
    {
        $this->authorize(Access::LAYOUTS_EDIT);
        $layout = Layout::query()->with(['sections', 'textGroups.textKeys'])->findOrFail($layoutId);
        $copy = $layout->duplicate();

        unset($this->layouts);

        $this->toast("Duplicated as {$copy->name}.");
    }

    public function delete(int $layoutId): void
    {
        $this->authorize(Access::LAYOUTS_EDIT);
        $layout = Layout::query()->findOrFail($layoutId);

        if (Layout::query()->count() <= 1) {
            $this->toast(__('Keep at least one layout so a new broadcast has slots to copy.'), 'warning');

            return;
        }

        if ($layout->shows()->exists()) {
            $this->toast(__('Move or delete the broadcasts on this layout first. Deleting it would drop their caption fields.'), 'warning');

            return;
        }

        $name = $layout->name;
        $layout->delete();

        unset($this->layouts);

        $this->toast("Deleted {$name}.");
    }
}
