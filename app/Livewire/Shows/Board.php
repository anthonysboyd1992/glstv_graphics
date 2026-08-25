<?php

namespace App\Livewire\Shows;

use App\Models\Asset;
use App\Models\Look;
use App\Models\Show;
use App\Services\Shows\RoleResolver;
use App\Services\Shows\ShowStateManager;
use Flux\Flux;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Component;

/**
 * The live control surface.
 *
 * The rundown is the intended way to run a night: one click advances every
 * section and text field together. The routing grid underneath is the escape
 * hatch for the moments that go off script, and doing anything there drops the
 * show out of the rundown so the two never disagree about what is on air.
 */
class Board extends Component
{
    public Show $show;

    public string $search = '';

    /** Limits the grid to assets that suit one section. */
    public ?string $focusSection = null;

    /** @var array<string, string> */
    public array $text = [];

    public function mount(Show $show): void
    {
        $this->show = $show->load(['showTemplate.sections', 'showTemplate.textKeys']);
        $this->syncTextFromState();
    }

    #[Computed]
    public function sections(): Collection
    {
        return $this->show->showTemplate->sections;
    }

    #[Computed]
    public function textKeys(): Collection
    {
        return $this->show->showTemplate->textKeys;
    }

    /** @return Collection<int, Look> */
    #[Computed]
    public function looks(): Collection
    {
        return $this->show->looks()->withCount('items')->get();
    }

    /**
     * Assets offered in the grid. Filtered to the focused section when one is
     * chosen, because a 1920x180 score bug and a 500x500 corner mark share no
     * usable images and showing both only makes the grid harder to read.
     *
     * @return Collection<int, Asset>
     */
    #[Computed]
    public function assets(): Collection
    {
        $assets = Asset::query()
            ->when($this->search !== '', fn ($query) => $query->where('name', 'like', '%'.$this->search.'%'))
            ->orderBy('name')
            ->limit(200)
            ->get();

        $section = $this->focusSection
            ? $this->sections->firstWhere('key', $this->focusSection)
            : null;

        return $section
            ? $assets->filter(fn (Asset $asset) => $section->accepts($asset))->values()
            : $assets;
    }

    /**
     * What is on air right now, keyed by section.
     *
     * @return array<string, Asset|null>
     */
    #[Computed]
    public function onAir(): array
    {
        $ids = collect($this->show->current_state['sections'] ?? [])->pluck('asset_id')->filter()->unique();
        $assets = $ids->isEmpty() ? collect() : Asset::whereIn('id', $ids)->get()->keyBy('id');

        return $this->sections
            ->mapWithKeys(fn ($section) => [
                $section->key => $assets->get($this->show->sectionAssetId($section->key)),
            ])
            ->all();
    }

    /**
     * Roles the active packs currently fill, shown so an operator can see what a
     * role-based cue will actually resolve to before firing it.
     */
    #[Computed]
    public function roleMap(RoleResolver $roles): Collection
    {
        return $roles->map($this->show);
    }

    public function assign(string $sectionKey, int $assetId, ShowStateManager $state): void
    {
        $state->setSection($this->show, $sectionKey, $assetId);
        $this->afterStateChange();
    }

    public function clearSection(string $sectionKey, ShowStateManager $state): void
    {
        $state->clearSection($this->show, $sectionKey);
        $this->afterStateChange();
    }

    public function applyLook(int $lookId, ShowStateManager $state): void
    {
        $look = $this->show->looks()->with('items')->findOrFail($lookId);

        $state->applyLook($this->show, $look);
        $this->afterStateChange();

        Flux::toast(text: $look->name, variant: 'success');
    }

    public function step(int $offset, ShowStateManager $state): void
    {
        $look = $state->applyLookAtOffset($this->show, $offset);

        $this->afterStateChange();

        if ($look) {
            Flux::toast(text: $look->name, variant: 'success');
        }
    }

    public function resetBoard(ShowStateManager $state): void
    {
        $state->reset($this->show);
        $this->afterStateChange();

        Flux::toast(text: __('Board cleared.'));
    }

    public function saveText(string $key, ShowStateManager $state): void
    {
        $state->setText($this->show, $key, $this->text[$key] ?? null);
        $this->show->refresh();

        Flux::toast(text: __('Updated :key.', ['key' => $key]), variant: 'success');
    }

    protected function afterStateChange(): void
    {
        $this->show->refresh();
        $this->syncTextFromState();

        unset($this->onAir);
    }

    protected function syncTextFromState(): void
    {
        $this->text = $this->show->showTemplate->textKeys
            ->mapWithKeys(fn ($key) => [
                $key->key => (string) ($this->show->textValue($key->key) ?? $key->default_value ?? ''),
            ])
            ->all();
    }
}
