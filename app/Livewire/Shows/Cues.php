<?php

namespace App\Livewire\Shows;

use App\Concerns\Toasts;
use App\Models\Asset;
use App\Models\Look;
use App\Models\LookItem;
use App\Models\Show;
use App\Services\Assets\AssetScaler;
use App\Support\Access;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Attributes\Validate;
use Livewire\Component;

/**
 * The cue stack as a spreadsheet: one row per cue, one column per section.
 *
 * Cues route pictures and nothing else. Text is operated live from the board,
 * because captions change on their own clock and an operator should never lose
 * one they just typed because the next race came up.
 *
 * Every cell is three-state, which is the part that carries the weight. Blank
 * means leave whatever is on air alone, so a cue that only changes the lower
 * third is a row with one filled cell. That is also why edits save on the spot
 * rather than behind a form — authoring a night is dozens of small changes, and
 * a save button between each one would be the slowest part of the job.
 */
#[Title('Cues')]
class Cues extends Component
{
    use Toasts;

    public Show $show;

    #[Validate('required|string|max:120')]
    public string $newName = '';

    /** Cue whose name is being edited inline. */
    public ?int $renamingId = null;

    public string $renameValue = '';

    public function mount(Show $show): void
    {
        $this->show = $show->load('sections');
    }

    /** @return Collection<int, Look> */
    #[Computed]
    public function cues(): Collection
    {
        return $this->show->looks()->with('items.asset')->get();
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
     * Assets per column with the ones that fit that section first. Everything
     * stays reachable; a mismatch is flagged rather than hidden.
     *
     * @return array<string, Collection<int, Asset>>
     */
    #[Computed]
    public function assetsBySection(): array
    {
        return $this->sectionDefs
            ->mapWithKeys(fn ($section) => [
                $section->key => $this->assets
                    ->sortByDesc(fn (Asset $asset) => $section->accepts($asset) ? 1 : 0)
                    ->values(),
            ])
            ->all();
    }

    public function add(): void
    {
        $this->authorize(Access::CUES_EDIT);
        $this->validateOnly('newName');

        $this->show->looks()->create([
            'name' => $this->newName,
            'sort_order' => (int) $this->show->looks()->max('sort_order') + 1,
        ]);

        $this->reset('newName');
        $this->refreshCues();
    }

    /**
     * Sets a cell. The value is a small tagged string rather than a pair of
     * fields because it comes straight off one dropdown.
     */
    public function setSection(int $cueId, string $sectionKey, string $value, AssetScaler $scaler): void
    {
        $this->authorize(Access::CUES_EDIT);

        $cue = $this->show->looks()->findOrFail($cueId);

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
        $this->show->looks()->whereKey($cueId)->delete();

        // A deleted cue must not stay on deck or reported as live.
        $this->show->forceFill([
            'active_look_id' => $this->show->active_look_id === $cueId ? null : $this->show->active_look_id,
            'preview_look_id' => $this->show->preview_look_id === $cueId ? null : $this->show->preview_look_id,
        ])->save();

        $this->refreshCues();

        $this->toast(__('Cue deleted.'));
    }

    protected function sizedAssetId(int $assetId, string $sectionKey, AssetScaler $scaler): int
    {
        $asset = Asset::query()->findOrFail($assetId);
        $section = $this->sectionDefs->firstWhere('key', $sectionKey);

        return $section
            ? $scaler->fitToSection($asset, $section)->id
            : $asset->id;
    }

    protected function refreshCues(): void
    {
        unset($this->cues, $this->cells);
    }
}
