<?php

namespace App\Livewire\Shows;

use App\Concerns\Toasts;
use App\Models\Asset;
use App\Models\Look;
use App\Models\LookItem;
use App\Models\Show;
use App\Services\Assets\AssetScaler;
use App\Services\Shows\ShowStateManager;
use App\Support\Access;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * The cue stack as a spreadsheet: one row per cue, one column per section.
 *
 * Cues route pictures and nothing else. Text is operated live from the board,
 * because captions change on their own clock and an operator should never lose
 * one they just typed because the next race came up.
 *
 * Every cell is a picture or empty. Go Live puts this row on air as-is, so a
 * blank cell clears that section rather than holding the previous graphic.
 * Changing a cell on the live cue writes that picture through to the feed
 * immediately, so vMix does not wait for another Go Live.
 * Edits save on the spot rather than behind a form — authoring a night is
 * dozens of small changes, and a save button between each one would be the
 * slowest part of the job.
 */
#[Title('Cues')]
class Cues extends Component
{
    use Toasts;

    public Show $show;

    public int $addCount = 1;

    /** Cue whose name is being edited inline. */
    public ?int $renamingId = null;

    public string $renameValue = '';

    /** @var list<int|string> */
    public array $selected = [];

    public function mount(Show $show): void
    {
        $this->show = $show->load('sections');
    }

    /** @return Collection<int, Look> */
    #[Computed]
    public function cues(): Collection
    {
        return $this->show->looks()->with('items.asset.source')->get();
    }

    #[Computed]
    public function sectionDefs(): Collection
    {
        return $this->show->sections;
    }

    /**
     * Every cue's items indexed by section, so the table can look a cell up
     * without a query per cell.
     *
     * @return array<int, array<string, LookItem>>
     */
    #[Computed]
    public function cells(): array
    {
        return $this->cues
            ->mapWithKeys(fn (Look $cue) => [
                $cue->id => $cue->items->keyBy(fn (LookItem $item) => $item->section_key)->all(),
            ])
            ->all();
    }

    /** @return Collection<int, Asset> */
    #[Computed]
    public function assets(): Collection
    {
        return Asset::query()->originals()->orderBy('name')->get();
    }

    /**
     * @return array<string, Collection<int, Asset>>
     */
    #[Computed]
    public function assetsBySection(): array
    {
        $sorted = $this->assets
            ->sortBy(fn (Asset $asset) => $asset->name, SORT_NATURAL | SORT_FLAG_CASE)
            ->values();

        return $this->sectionDefs
            ->mapWithKeys(fn ($section) => [$section->key => $sorted])
            ->all();
    }

    public function add(): void
    {
        $this->authorize(Access::CUES_EDIT);
        $this->validate([
            'addCount' => 'required|integer|min:1|max:100',
        ]);

        $sort = (int) $this->show->looks()->max('sort_order');
        $next = $this->nextCueNumber();

        for ($i = 0; $i < $this->addCount; $i++) {
            $this->show->looks()->create([
                'name' => 'Cue '.($next + $i),
                'sort_order' => ++$sort,
            ]);
        }

        $count = $this->addCount;
        $this->addCount = 1;
        $this->refreshCues();

        $this->toast(trans_choice(':count cue added.|:count cues added.', $count, ['count' => $count]));
    }

    protected function nextCueNumber(): int
    {
        $highest = $this->show->looks()
            ->pluck('name')
            ->map(fn (string $name) => preg_match('/^Cue (\d+)$/', $name, $match) ? (int) $match[1] : 0)
            ->max();

        return (int) $highest + 1;
    }

    /**
     * Sets a cell. The value is a small tagged string rather than a pair of
     * fields because it comes straight off one dropdown.
     */
    public function setSection(int $cueId, string $sectionKey, string $value, AssetScaler $scaler, ShowStateManager $state): void
    {
        $this->authorize(Access::CUES_EDIT);

        $cue = $this->show->looks()->findOrFail($cueId);

        $this->writeCells($cue, [$sectionKey => $value], $scaler);
        $this->syncLiveCue($cue, $state);
        $this->refreshCues();
    }

    /**
     * Puts the same choice on every section of a cue, fitting each slot.
     * Shift-click or "Every section" in the picker lands here.
     */
    public function fillCue(int $cueId, string $value, AssetScaler $scaler, ShowStateManager $state): void
    {
        $this->authorize(Access::CUES_EDIT);

        $cue = $this->show->looks()->findOrFail($cueId);
        $writes = $this->sectionDefs
            ->mapWithKeys(fn ($section) => [$section->key => $value])
            ->all();

        $this->writeCells($cue, $writes, $scaler);
        $this->syncLiveCue($cue, $state);
        $this->refreshCues();
    }

    public function startRename(int $cueId): void
    {
        $this->authorize(Access::CUES_EDIT);
        $this->renamingId = $cueId;
        $this->renameValue = $this->show->looks()->findOrFail($cueId)->name;
    }

    public function rename(): void
    {
        $this->authorize(Access::CUES_EDIT);
        if (! $this->renamingId) {
            return;
        }

        $this->validate(['renameValue' => 'required|string|max:120']);

        $this->show->looks()->whereKey($this->renamingId)->update(['name' => trim($this->renameValue)]);

        $this->reset('renamingId', 'renameValue');
        $this->refreshCues();
    }

    public function cancelRename(): void
    {
        $this->reset('renamingId', 'renameValue');
    }

    /**
     * Copies a cue in directly beneath its source, since the reason to duplicate
     * is almost always the next running of the same thing.
     */
    public function duplicate(int $cueId): void
    {
        $this->authorize(Access::CUES_EDIT);
        $source = $this->show->looks()->with('items')->findOrFail($cueId);

        $this->show->looks()
            ->where('sort_order', '>', $source->sort_order)
            ->increment('sort_order');

        $copy = $this->show->looks()->create([
            'name' => $source->name.' (copy)',
            'kind' => $source->kind,
            'notes' => $source->notes,
            'sort_order' => $source->sort_order + 1,
        ]);

        foreach ($source->items as $item) {
            $copy->items()->create($item->only(['section_key', 'action', 'asset_id']));
        }

        $this->refreshCues();
        $this->startRename($copy->id);
    }

    public function move(int $cueId, int $direction): void
    {
        $this->authorize(Access::CUES_EDIT);
        $cues = $this->cues;
        $index = $cues->search(fn (Look $cue) => $cue->id === $cueId);

        if ($index === false) {
            return;
        }

        $swap = $cues->get($index + $direction);

        if (! $swap) {
            return;
        }

        $cue = $cues->get($index);

        [$cue->sort_order, $swap->sort_order] = [$swap->sort_order, $cue->sort_order];

        $cue->save();
        $swap->save();

        $this->refreshCues();
    }

    public function delete(int $cueId): void
    {
        $this->authorize(Access::CUES_EDIT);
        $this->dropCues([$cueId]);
    }

    public function deleteSelected(): void
    {
        $this->authorize(Access::CUES_EDIT);
        $this->dropCues($this->selectedIds());
    }

    public function toggleSelectAll(): void
    {
        $this->authorize(Access::CUES_EDIT);

        $selecting = ! $this->allSelected;

        $this->selected = $selecting
            ? $this->cues->pluck('id')->map(fn ($id) => (string) $id)->all()
            : [];

        unset($this->selectedCount, $this->allSelected);
    }

    #[Computed]
    public function selectedCount(): int
    {
        return count(array_intersect($this->cues->pluck('id')->map(fn ($id) => (int) $id)->all(), $this->selectedIds()));
    }

    #[Computed]
    public function allSelected(): bool
    {
        $ids = $this->cues->pluck('id')->map(fn ($id) => (int) $id)->all();

        return $ids !== [] && count($ids) === count(array_intersect($ids, $this->selectedIds()));
    }

    /**
     * Drops cues on this box only. A deleted cue must not stay on deck or
     * reported as live.
     *
     * @param  list<int>  $ids
     */
    protected function dropCues(array $ids): void
    {
        $ids = array_values(array_unique(array_map('intval', $ids)));
        $ids = $this->show->looks()->whereKey($ids)->pluck('id')->map(fn ($id) => (int) $id)->all();

        if ($ids === []) {
            return;
        }

        $this->show->looks()->whereKey($ids)->delete();

        $active = $this->show->active_look_id;
        $preview = $this->show->preview_look_id;

        $this->show->forceFill([
            'active_look_id' => $active !== null && in_array((int) $active, $ids, true) ? null : $active,
            'preview_look_id' => $preview !== null && in_array((int) $preview, $ids, true) ? null : $preview,
        ])->save();

        $this->selected = [];

        if ($this->renamingId && in_array($this->renamingId, $ids, true)) {
            $this->reset('renamingId', 'renameValue');
        }

        $this->refreshCues();

        $this->toast(trans_choice(':count cue deleted.|:count cues deleted.', count($ids), ['count' => count($ids)]));
    }

    /** @return list<int> */
    protected function selectedIds(): array
    {
        return collect($this->selected)
            ->map(fn ($id) => (int) $id)
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    protected function sizedAssetId(int $assetId, string $sectionKey, AssetScaler $scaler): int
    {
        $asset = Asset::query()->findOrFail($assetId);
        $section = $this->sectionDefs->firstWhere('key', $sectionKey);

        return $section
            ? $scaler->fitToSection($asset, $section)->id
            : $asset->id;
    }

    /**
     * @param  array<string, string>  $values
     */
    protected function writeCells(Look $cue, array $values, AssetScaler $scaler): void
    {
        foreach ($values as $sectionKey => $value) {
            $cue->items()->where('section_key', $sectionKey)->delete();

            $attributes = match (true) {
                $value === 'leave' => null,
                $value === 'clear' => ['action' => LookItem::ACTION_CLEAR],
                str_starts_with($value, 'asset:') => [
                    'action' => LookItem::ACTION_SET,
                    'asset_id' => $this->sizedAssetId((int) substr($value, 6), $sectionKey, $scaler),
                ],
                default => null,
            };

            if ($attributes !== null) {
                $cue->items()->create($attributes + ['section_key' => $sectionKey]);
            }
        }
    }

    protected function syncLiveCue(Look $cue, ShowStateManager $state): void
    {
        // The live feed is current_state, not the cue row. Re-applying keeps
        // the rundown pointer and bumps the revision so vMix picks it up.
        if ((int) $this->show->active_look_id === (int) $cue->id) {
            $state->applyLook($this->show, $cue->load('items'));
        }
    }

    protected function refreshCues(): void
    {
        unset($this->cues, $this->cells, $this->selectedCount, $this->allSelected);
    }
}
