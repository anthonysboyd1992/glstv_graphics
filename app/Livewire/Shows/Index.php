<?php

namespace App\Livewire\Shows;

use App\Models\Show;
use App\Models\ShowTemplate;
use Flux\Flux;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Attributes\Validate;
use Livewire\Component;

#[Title('Broadcasts')]
class Index extends Component
{
    #[Validate('required|string|max:120')]
    public string $name = '';

    #[Validate('required|exists:show_templates,id')]
    public ?int $showTemplateId = null;

    #[Validate('nullable|date')]
    public ?string $scheduledFor = null;

    public function mount(): void
    {
        $this->showTemplateId = ShowTemplate::value('id');
    }

    /** @return Collection<int, Show> */
    #[Computed]
    public function shows(): Collection
    {
        return Show::with('showTemplate')
            ->withCount('looks')
            ->orderByDesc('scheduled_for')
            ->orderByDesc('id')
            ->get();
    }

    /** @return Collection<int, ShowTemplate> */
    #[Computed]
    public function templates(): Collection
    {
        return ShowTemplate::orderBy('name')->get();
    }

    public function create(): void
    {
        $this->validate();

        $show = Show::create([
            'show_template_id' => $this->showTemplateId,
            'name' => $this->name,
            'scheduled_for' => $this->scheduledFor,
        ]);

        $this->reset('name', 'scheduledFor');
        unset($this->shows);

        Flux::modal('create-show')->close();
        Flux::toast(variant: 'success', text: "Created {$show->name}.");
    }

    /**
     * Copy a broadcast for the next race night: same template, packs and cue
     * stack, fresh identifier and empty live state.
     */
    /**
     * Shows resolve by slug for routing, so actions take an id and look the
     * record up explicitly rather than relying on implicit binding.
     */
    public function duplicate(int $showId): void
    {
        $show = Show::with('assetPacks')->findOrFail($showId);

        $copy = Show::create([
            'show_template_id' => $show->show_template_id,
            'name' => $show->name.' (copy)',
            'scheduled_for' => $show->scheduled_for?->addWeek(),
        ]);

        $copy->assetPacks()->sync(
            $show->assetPacks->mapWithKeys(fn ($pack) => [$pack->id => ['sort_order' => $pack->pivot->sort_order]])->all()
        );

        foreach ($show->looks()->with('items')->get() as $look) {
            $newLook = $copy->looks()->create($look->only('name', 'kind', 'notes', 'sort_order'));

            foreach ($look->items as $item) {
                $newLook->items()->create($item->only(
                    'target_type', 'target_key', 'action', 'asset_id', 'role_key', 'text_value'
                ));
            }
        }

        unset($this->shows);

        Flux::toast(variant: 'success', text: "Duplicated as {$copy->name}.");
    }

    public function delete(int $showId): void
    {
        Show::findOrFail($showId)->delete();

        unset($this->shows);

        Flux::toast(text: 'Broadcast deleted.');
    }
}
