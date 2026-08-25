<?php

namespace App\Livewire\Shows;

use App\Concerns\Toasts;
use App\Models\Asset;
use App\Models\Layout;
use App\Models\Look;
use App\Models\LookItem;
use App\Models\Section;
use App\Models\Show;
use App\Models\TextGroup;
use App\Models\TextKey;
use App\Services\Assets\AssetScaler;
use App\Services\Shows\ShowStateManager;
use App\Support\Access;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Livewire\Attributes\Computed;
use Livewire\Component;

/**
 * The live control surface.
 *
 * Selecting a cue puts it on deck and Go Live puts it to air, kept apart the
 * way a vision mixer separates preview from program: browsing the stack
 * mid-race must not change the picture. Go Live then queues the following cue,
 * so a whole night can be run from that one button.
 *
 * The routing grid underneath is the escape hatch for moments that go off
 * script. It goes straight to air by design — that is the point of it — and
 * doing anything there drops the show out of the rundown so the two never
 * disagree about what is showing.
 */
class Board extends Component
{
    use Toasts;

    public Show $show;

    public string $search = '';

    /** Limits the grid to assets that suit one section. */
    public ?string $focusSection = null;

    /** @var array<string, string> */
    public array $text = [];

    public string $newTextKey = '';

    public ?int $newTextKeyGroupId = null;

    public string $newTextGroup = '';

    /** Field whose label is being edited inline. */
    public ?int $renamingTextKeyId = null;

    public string $textKeyLabel = '';

    /** @var array<string, string> */
    public array $defaults = [];

    public bool $layoutOpen = false;

    public bool $endpointsOpen = false;

    /** @var array{key: string, label: string, width: string, height: string} */
    public array $newSection = ['key' => '', 'label' => '', 'width' => '', 'height' => ''];

    /**
     * Inline drafts for existing sections, keyed by section id.
     *
     * @var array<int, array{key: string, label: string, width: string, height: string}>
     */
    public array $sectionEdits = [];

    public string $newLayoutName = '';

    public function mount(Show $show): void
    {
        $this->show = $show->load(['sections', 'layout.textGroups.textKeys', 'textDefaults.textKey.group']);
        $this->newTextKeyGroupId = $this->textGroups->first()?->id;
        $this->syncSectionEdits();
        $this->syncTextFromState();
    }

    #[Computed]
    public function sections(): Collection
    {
        return $this->show->sections;
    }

    #[Computed]
    public function textGroups(): Collection
    {
        return TextGroup::catalog($this->show->layout);
    }

    #[Computed]
    public function textKeys(): Collection
    {
        return $this->textGroups->flatMap->textKeys;
    }

    /** @return Collection<int, Look> */
    #[Computed]
    public function looks(): Collection
    {
        return $this->show->looks()->withCount('items')->with('items.asset')->get();
    }

    /**
     * Assets offered in the grid. Focusing a section sorts the ones that fit it
     * to the top rather than hiding the rest, because "wrong shape" is a warning
     * worth seeing, not a reason to make a graphic unreachable mid-race.
     *
     * @return Collection<int, Asset>
     */
    #[Computed]
    public function assets(): Collection
    {
        $assets = Asset::query()
            ->originals()
            ->when($this->search !== '', fn ($query) => $query->where('name', 'like', '%'.$this->search.'%'))
            ->orderBy('name')
            ->limit(200)
            ->get();

        $section = $this->focusSection
            ? $this->sections->firstWhere('key', $this->focusSection)
            : null;

        return $section
            ? $assets->sortByDesc(fn (Asset $asset) => $section->accepts($asset) ? 1 : 0)->values()
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
     * The on-deck cue's pictures, keyed by section. Unnamed sections stay
     * empty here so clicking a cue shows that cue, not a hold of air.
     *
     * @return array<string, array{asset: Asset|null, change: string}>
     */
    #[Computed]
    public function onDeckSlots(): array
    {
        $look = $this->show->preview_look_id
            ? Look::query()->with('items.asset')->find($this->show->preview_look_id)
            : null;
        $changes = $look?->items->keyBy('section_key') ?? collect();

        return $this->sections
            ->mapWithKeys(function ($section) use ($look, $changes) {
                if (! $look) {
                    return [$section->key => ['asset' => null, 'change' => 'idle']];
                }

                $item = $changes->get($section->key);

                if (! $item) {
                    return [$section->key => ['asset' => null, 'change' => 'leave']];
                }

                if ($item->action === LookItem::ACTION_CLEAR) {
                    return [$section->key => ['asset' => null, 'change' => 'clear']];
                }

                return [$section->key => ['asset' => $item->asset, 'change' => 'set']];
            })
            ->all();
    }

    public function assign(string $sectionKey, int $assetId, ShowStateManager $state, AssetScaler $scaler): void
    {
        $this->authorize(Access::BOARD_TAKE);

        $section = $this->sections->firstWhere('key', $sectionKey);
        $asset = Asset::query()->findOrFail($assetId);

        if ($section) {
            $asset = $scaler->fitToSection($asset, $section);
        }

        $state->setSection($this->show, $sectionKey, $asset->id);
        $this->afterStateChange();
    }

    public function clearSection(string $sectionKey, ShowStateManager $state): void
    {
        $this->authorize(Access::BOARD_TAKE);
        $state->clearSection($this->show, $sectionKey);
        $this->afterStateChange();
    }

    /** Selecting a cue only puts it on deck. Air does not move until Go Live. */
    public function arm(int $lookId, ShowStateManager $state): void
    {
        $this->authorize(Access::BOARD_TAKE);
        $state->arm($this->show, $lookId);
        $this->afterStateChange();
    }

    public function take(ShowStateManager $state): void
    {
        $this->authorize(Access::BOARD_TAKE);
        $look = $state->take($this->show);

        $this->afterStateChange();

        if ($look) {
            $this->toast(__('On air: :name', ['name' => $look->name]));
        }
    }

    public function step(int $offset, ShowStateManager $state): void
    {
        $this->authorize(Access::BOARD_TAKE);
        $state->armAtOffset($this->show, $offset);

        $this->afterStateChange();
    }

    public function resetBoard(ShowStateManager $state): void
    {
        $this->authorize(Access::BOARD_TAKE);
        $state->reset($this->show);
        $this->afterStateChange();

        $this->toast(__('Board cleared.'));
    }

    public function saveText(int $textKeyId, ShowStateManager $state): void
    {
        $this->authorize(Access::BOARD_TEXT);
        $textKey = $this->textKeys->firstWhere('id', $textKeyId);

        if (! $textKey) {
            return;
        }

        $state->setText($this->show, $textKey->fieldName(), $this->text[$textKeyId] ?? null);
        $this->show->refresh();

        $this->toast(__('Updated :key.', ['key' => $textKey->fieldName()]));
    }

    public function revertText(int $textKeyId, ShowStateManager $state): void
    {
        $this->authorize(Access::BOARD_TEXT);
        $textKey = $this->textKeys->firstWhere('id', $textKeyId);

        if (! $textKey) {
            return;
        }

        $state->clearText($this->show, $textKey->fieldName());
        $this->afterStateChange();
    }

    /**
     * Adds a field to this box's layout. Other boxes on the same overlay type
     * see it immediately; only this box's live value and default start empty.
     * The data source name is Group.key and is fixed from here.
     */
    public function addTextKey(): void
    {
        $this->authorize(Access::CATALOG_EDIT);
        $this->validate([
            'newTextKey' => 'required|string|max:120',
            'newTextKeyGroupId' => 'required|integer',
        ]);

        $group = $this->textGroups->firstWhere('id', (int) $this->newTextKeyGroupId);

        if (! $group) {
            $this->addError('newTextKeyGroupId', __('Pick a group on this layout.'));

            return;
        }

        $key = Str::snake(Str::ascii($this->newTextKey));

        if ($key === '' || TextKey::where('group_id', $group->id)->where('key', $key)->exists()) {
            $this->addError('newTextKey', __('That key is already taken in this group.'));

            return;
        }

        $textKey = TextKey::create([
            'group_id' => $group->id,
            'key' => $key,
            'label' => $this->newTextKey,
            'sort_order' => (int) TextKey::where('group_id', $group->id)->max('sort_order') + 1,
        ]);

        $this->reset('newTextKey');
        $this->refreshLayout();

        $this->toast(__('Added :key to this layout.', ['key' => $textKey->fieldName()]));
    }

    public function addTextGroup(): void
    {
        $this->authorize(Access::CATALOG_EDIT);
        $this->validate(['newTextGroup' => 'required|string|max:80']);

        $layout = $this->show->layout;

        if (! $layout) {
            $this->addError('newTextGroup', __('This box has no layout to attach a group to.'));

            return;
        }

        $label = trim($this->newTextGroup);
        $key = preg_match('/^[A-Za-z][A-Za-z0-9_]*$/', $label) === 1
            ? $label
            : Str::studly(Str::ascii($label));

        if ($key === '' || $layout->textGroups()->where('key', $key)->exists()) {
            $this->addError('newTextGroup', __('That group is already taken on this layout.'));

            return;
        }

        $group = $layout->textGroups()->create([
            'key' => $key,
            'label' => $label,
            'sort_order' => (int) $layout->textGroups()->max('sort_order') + 1,
        ]);

        $this->reset('newTextGroup');
        $this->newTextKeyGroupId = $group->id;
        $this->refreshLayout();

        $this->toast(__('Added group :key to this layout. Fields will publish as :key.key.', ['key' => $key]));
    }

    public function startTextRename(int $textKeyId): void
    {
        $this->authorize(Access::CATALOG_EDIT);
        $this->renamingTextKeyId = $textKeyId;
        $this->textKeyLabel = $this->textKeys->firstWhere('id', $textKeyId)?->label ?? '';
    }

    public function renameTextKey(): void
    {
        $this->authorize(Access::CATALOG_EDIT);
        if (! $this->renamingTextKeyId) {
            return;
        }

        $this->validate(['textKeyLabel' => 'required|string|max:120']);

        TextKey::whereKey($this->renamingTextKeyId)->update(['label' => trim($this->textKeyLabel)]);

        $this->reset('renamingTextKeyId', 'textKeyLabel');
        $this->refreshLayout();
    }

    public function cancelTextRename(): void
    {
        $this->reset('renamingTextKeyId', 'textKeyLabel');
    }

    public function saveTextDefault(int $textKeyId): void
    {
        $this->authorize(Access::BOARD_TEXT);
        $textKey = $this->textKeys->firstWhere('id', $textKeyId);

        if (! $textKey) {
            return;
        }

        $this->show->textDefaults()->updateOrCreate(
            ['text_key_id' => $textKey->id],
            ['default_value' => $this->defaults[$textKeyId] ?? ''],
        );

        $this->show->unsetRelation('textDefaults');
        $this->reloadLayout();
    }

    public function moveTextKey(int $textKeyId, int $direction): void
    {
        $this->authorize(Access::CATALOG_EDIT);
        $textKey = $this->textKeys->firstWhere('id', $textKeyId);

        if (! $textKey) {
            return;
        }

        $keys = $this->textKeys->where('group_id', $textKey->group_id)->values();
        $index = $keys->search(fn (TextKey $key) => $key->id === $textKeyId);

        if ($index === false) {
            return;
        }

        $swap = $keys->get($index + $direction);

        if (! $swap) {
            return;
        }

        $key = $keys->get($index);

        [$key->sort_order, $swap->sort_order] = [$swap->sort_order, $key->sort_order];

        $key->save();
        $swap->save();

        $this->reloadLayout();
    }

    public function moveTextGroup(int $groupId, int $direction): void
    {
        $this->authorize(Access::CATALOG_EDIT);
        $groups = $this->textGroups->values();
        $index = $groups->search(fn (TextGroup $group) => $group->id === $groupId);

        if ($index === false) {
            return;
        }

        $swap = $groups->get($index + $direction);

        if (! $swap) {
            return;
        }

        $group = $groups->get($index);

        [$group->sort_order, $swap->sort_order] = [$swap->sort_order, $group->sort_order];

        $group->save();
        $swap->save();

        $this->reloadLayout();
    }

    public function deleteTextGroup(int $groupId): void
    {
        $this->authorize(Access::CATALOG_EDIT);
        $group = $this->textGroups->firstWhere('id', $groupId);

        if (! $group) {
            return;
        }

        $group->delete();

        $this->refreshLayout();
        $this->newTextKeyGroupId = $this->textGroups->first()?->id;

        $this->toast(__('Removed group :key from this layout.', ['key' => $group->key]));
    }

    public function deleteTextKey(int $textKeyId): void
    {
        $this->authorize(Access::CATALOG_EDIT);
        $textKey = $this->textKeys->firstWhere('id', $textKeyId);

        if (! $textKey) {
            return;
        }

        $textKey->delete();

        $this->refreshLayout();

        $this->toast(__('Removed :key from this layout.', ['key' => $textKey->key]));
    }

    public function addSection(): void
    {
        $this->authorize(Access::LAYOUTS_EDIT);
        $this->validate([
            'newSection.key' => ['required', 'regex:/^[A-Za-z][A-Za-z0-9_]*$/', 'max:60'],
            'newSection.label' => 'required|string|max:80',
            'newSection.width' => 'nullable|integer|min:1|max:16384',
            'newSection.height' => 'nullable|integer|min:1|max:16384',
        ], [
            'newSection.key.regex' => __('Keys become data source field names: letters, numbers and underscores, starting with a letter.'),
        ]);

        $key = $this->newSection['key'];
        $width = $this->newSection['width'] !== '' ? (int) $this->newSection['width'] : null;
        $height = $this->newSection['height'] !== '' ? (int) $this->newSection['height'] : null;

        if (($width === null) !== ($height === null)) {
            $this->addError('newSection.width', __('Width and height go together.'));

            return;
        }

        if ($this->show->sections()->where('key', $key)->exists()) {
            $this->addError('newSection.key', __('That key is already taken.'));

            return;
        }

        $this->show->sections()->create([
            'key' => $key,
            'label' => $this->newSection['label'],
            'width' => $width,
            'height' => $height,
            'sort_order' => (int) $this->show->sections()->max('sort_order') + 1,
        ]);

        $this->reset('newSection');
        $this->refreshLayout();

        $this->toast(__('Added :key.', ['key' => $key]));
    }

    public function saveSection(int $sectionId, ShowStateManager $state, AssetScaler $scaler): void
    {
        $this->authorize(Access::LAYOUTS_EDIT);

        $section = $this->sections->firstWhere('id', $sectionId);

        if (! $section) {
            return;
        }

        $this->validate([
            "sectionEdits.{$sectionId}.key" => ['required', 'regex:/^[A-Za-z][A-Za-z0-9_]*$/', 'max:60'],
            "sectionEdits.{$sectionId}.label" => 'required|string|max:80',
            "sectionEdits.{$sectionId}.width" => 'nullable|integer|min:1|max:16384',
            "sectionEdits.{$sectionId}.height" => 'nullable|integer|min:1|max:16384',
        ], [
            "sectionEdits.{$sectionId}.key.regex" => __('Keys become data source field names: letters, numbers and underscores, starting with a letter.'),
        ]);

        $draft = $this->sectionEdits[$sectionId];
        $key = $draft['key'];
        $width = $draft['width'] === '' || $draft['width'] === null ? null : (int) $draft['width'];
        $height = $draft['height'] === '' || $draft['height'] === null ? null : (int) $draft['height'];

        if (($width === null) !== ($height === null)) {
            $this->addError("sectionEdits.{$sectionId}.width", __('Width and height go together.'));

            return;
        }

        if ($key !== $section->key && $this->show->sections()->where('key', $key)->exists()) {
            $this->addError("sectionEdits.{$sectionId}.key", __('That key is already taken.'));

            return;
        }

        $oldKey = $section->key;
        $sizeChanged = $section->width !== $width || $section->height !== $height;

        $section->update([
            'key' => $key,
            'label' => $draft['label'],
            'width' => $width,
            'height' => $height,
        ]);

        if ($key !== $oldKey) {
            $state->renameSectionKey($this->show, $oldKey, $key);

            LookItem::query()
                ->where('section_key', $oldKey)
                ->whereHas('look', fn ($query) => $query->where('show_id', $this->show->id))
                ->update(['section_key' => $key]);

            if ($this->focusSection === $oldKey) {
                $this->focusSection = $key;
            }
        }

        $fitted = $sizeChanged
            ? $this->correctSectionPictures($section->fresh(), $state, $scaler)
            : 0;

        $this->refreshLayout();

        $message = __('Updated :key.', ['key' => $key]);

        if ($fitted) {
            $message .= ' '.trans_choice('Fitted :count picture.|Fitted :count pictures.', $fitted, ['count' => $fitted]);
        }

        $this->toast($message);
    }

    public function deleteSection(int $sectionId, ShowStateManager $state): void
    {
        $this->authorize(Access::LAYOUTS_EDIT);
        $section = $this->sections->firstWhere('id', $sectionId);

        if (! $section) {
            return;
        }

        LookItem::where('section_key', $section->key)
            ->whereHas('look', fn ($query) => $query->where('show_id', $this->show->id))
            ->delete();

        $state->dropSectionKey($this->show, $section->key);

        $section->delete();

        if ($this->focusSection === $section->key) {
            $this->focusSection = null;
        }

        $this->refreshLayout();

        $this->toast(__('Removed :key.', ['key' => $section->key]));
    }

    public function saveAsLayout(): void
    {
        $this->authorize(Access::LAYOUTS_EDIT);
        $this->validate([
            'newLayoutName' => ['required', 'string', 'max:120'],
        ]);

        $layout = Layout::fromShow($this->show, $this->newLayoutName);
        $this->reset('newLayoutName');

        $this->toast(__('Saved layout :name.', ['name' => $layout->name]));
    }

    protected function refreshLayout(): void
    {
        $this->reloadLayout();
        $this->syncTextFromState();
    }

    protected function reloadLayout(): void
    {
        $this->show->load(['sections', 'layout.textGroups.textKeys', 'textDefaults.textKey.group']);
        $this->syncSectionEdits();

        unset($this->textKeys, $this->textGroups, $this->sections, $this->onAir, $this->onDeckSlots, $this->looks);
    }

    /**
     * Refit every picture currently using this section: on air and in cues.
     * Always scales from the original file so a size change does not compound.
     */
    protected function correctSectionPictures(Section $section, ShowStateManager $state, AssetScaler $scaler): int
    {
        $this->show->refresh();
        $changed = 0;

        $currentId = $this->show->sectionAssetId($section->key);
        $nextId = $this->correctedAssetId($currentId, $section, $scaler);

        if ($nextId !== $currentId) {
            $state->putSectionAsset($this->show, $section->key, $nextId);
            $changed++;
        }

        $items = LookItem::query()
            ->where('section_key', $section->key)
            ->where('action', LookItem::ACTION_SET)
            ->whereNotNull('asset_id')
            ->whereHas('look', fn ($query) => $query->where('show_id', $this->show->id))
            ->get();

        foreach ($items as $item) {
            $fittedId = $this->correctedAssetId($item->asset_id, $section, $scaler);

            if ($fittedId !== $item->asset_id) {
                $item->update(['asset_id' => $fittedId]);
                $changed++;
            }
        }

        return $changed;
    }

    protected function correctedAssetId(?int $assetId, Section $section, AssetScaler $scaler): ?int
    {
        if ($assetId === null) {
            return null;
        }

        $asset = Asset::query()->find($assetId);

        return $asset ? $scaler->forSection($asset, $section)->id : $assetId;
    }

    protected function syncSectionEdits(): void
    {
        $this->sectionEdits = $this->show->sections
            ->mapWithKeys(fn (Section $section) => [
                $section->id => [
                    'key' => $section->key,
                    'label' => $section->label,
                    'width' => $section->width !== null ? (string) $section->width : '',
                    'height' => $section->height !== null ? (string) $section->height : '',
                ],
            ])
            ->all();
    }

    protected function afterStateChange(): void
    {
        $this->show->refresh();
        $this->syncTextFromState();

        unset($this->onAir, $this->onDeckSlots, $this->looks);
    }

    protected function syncTextFromState(): void
    {
        $defaults = $this->show->textDefaultMap();

        $this->text = $this->textKeys
            ->mapWithKeys(fn (TextKey $key) => [
                $key->id => (string) ($this->show->textValueFor($key) ?? $defaults[$key->fieldName()] ?? ''),
            ])
            ->all();

        $this->defaults = $this->textKeys
            ->mapWithKeys(fn (TextKey $key) => [
                $key->id => (string) ($defaults[$key->fieldName()] ?? ''),
            ])
            ->all();
    }
}
