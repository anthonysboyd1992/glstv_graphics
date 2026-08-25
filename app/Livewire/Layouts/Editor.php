<?php

namespace App\Livewire\Layouts;

use App\Concerns\Toasts;
use App\Models\Layout;
use App\Models\LayoutSection;
use App\Models\TextGroup;
use App\Models\TextKey;
use App\Services\Shows\ShowStateManager;
use App\Support\Access;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Title('Layout')]
class Editor extends Component
{
    use Toasts;

    public Layout $layout;

    public string $name = '';

    public string $description = '';

    /** @var array{key: string, label: string, width: string, height: string} */
    public array $newSection = ['key' => '', 'label' => '', 'width' => '', 'height' => ''];

    /**
     * @var array<int, array{key: string, label: string, width: string, height: string}>
     */
    public array $sectionEdits = [];

    /** @var array{key: string, label: string} */
    public array $newGroup = ['key' => '', 'label' => ''];

    /**
     * @var array<int, array{key: string, label: string}>
     */
    public array $groupEdits = [];

    /**
     * @var array<int, array{key: string, label: string, description: string}>
     */
    public array $textKeyEdits = [];

    /**
     * @var array<int, array{key: string, label: string, description: string}>
     */
    public array $newFields = [];

    public function mount(Layout $layout): void
    {
        $this->layout = $layout->load(['sections', 'textGroups.textKeys', 'shows']);
        $this->name = $layout->name;
        $this->description = (string) $layout->description;
        $this->syncSectionEdits();
        $this->syncCatalogEdits();
    }

    /** @return Collection<int, LayoutSection> */
    #[Computed]
    public function sections(): Collection
    {
        return $this->layout->sections;
    }

    /** @return Collection<int, TextGroup> */
    #[Computed]
    public function textGroups(): Collection
    {
        return TextGroup::catalog($this->layout);
    }

    public function saveDetails(): void
    {
        $this->authorize(Access::LAYOUTS_EDIT);
        $this->validate([
            'name' => ['required', 'string', 'max:120'],
            'description' => ['nullable', 'string', 'max:240'],
        ]);

        $this->layout->update([
            'name' => $this->name,
            'description' => $this->description ?: null,
        ]);

        $this->toast(__('Saved :name.', ['name' => $this->layout->name]));
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
        [$width, $height] = $this->pairDimensions($this->newSection['width'], $this->newSection['height']);

        if ($width === false) {
            $this->addError('newSection.width', __('Width and height go together.'));

            return;
        }

        if ($this->layout->sections()->where('key', $key)->exists()) {
            $this->addError('newSection.key', __('That key is already taken.'));

            return;
        }

        $this->layout->sections()->create([
            'key' => $key,
            'label' => $this->newSection['label'],
            'width' => $width,
            'height' => $height,
            'sort_order' => (int) $this->layout->sections()->max('sort_order') + 1,
        ]);

        $this->reset('newSection');
        $this->reload();

        $this->toast(__('Added :key.', ['key' => $key]));
    }

    public function saveSection(int $sectionId): void
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
        [$width, $height] = $this->pairDimensions($draft['width'], $draft['height']);

        if ($width === false) {
            $this->addError("sectionEdits.{$sectionId}.width", __('Width and height go together.'));

            return;
        }

        if ($key !== $section->key && $this->layout->sections()->where('key', $key)->exists()) {
            $this->addError("sectionEdits.{$sectionId}.key", __('That key is already taken.'));

            return;
        }

        $section->update([
            'key' => $key,
            'label' => $draft['label'],
            'width' => $width,
            'height' => $height,
        ]);

        $this->reload();

        $this->toast(__('Updated :key.', ['key' => $key]));
    }

    public function deleteSection(int $sectionId): void
    {
        $this->authorize(Access::LAYOUTS_EDIT);
        $section = $this->sections->firstWhere('id', $sectionId);

        if (! $section) {
            return;
        }

        $key = $section->key;
        $section->delete();
        $this->reload();

        $this->toast(__('Removed :key.', ['key' => $key]));
    }

    public function addTextGroup(): void
    {
        $this->authorize(Access::LAYOUTS_EDIT);
        $this->validate([
            'newGroup.key' => ['required', 'regex:/^[A-Za-z][A-Za-z0-9_]*$/', 'max:60'],
            'newGroup.label' => 'required|string|max:80',
        ], [
            'newGroup.key.regex' => __('Group keys become the vMix prefix: letters, numbers and underscores, starting with a letter.'),
        ]);

        $key = $this->newGroup['key'];

        if ($this->layout->textGroups()->where('key', $key)->exists()) {
            $this->addError('newGroup.key', __('That group is already taken on this layout.'));

            return;
        }

        $this->layout->textGroups()->create([
            'key' => $key,
            'label' => $this->newGroup['label'],
            'sort_order' => (int) $this->layout->textGroups()->max('sort_order') + 1,
        ]);

        $this->reset('newGroup');
        $this->reload();

        $this->toast(__('Added group :key. Fields will publish as :key.key.', ['key' => $key]));
    }

    public function saveTextGroup(int $groupId, ShowStateManager $state): void
    {
        $this->authorize(Access::LAYOUTS_EDIT);
        $group = $this->textGroups->firstWhere('id', $groupId);

        if (! $group) {
            return;
        }

        $this->validate([
            "groupEdits.{$groupId}.key" => ['required', 'regex:/^[A-Za-z][A-Za-z0-9_]*$/', 'max:60'],
            "groupEdits.{$groupId}.label" => 'required|string|max:80',
        ], [
            "groupEdits.{$groupId}.key.regex" => __('Group keys become the vMix prefix: letters, numbers and underscores, starting with a letter.'),
        ]);

        $key = $this->groupEdits[$groupId]['key'];

        if ($key !== $group->key && $this->layout->textGroups()->where('key', $key)->exists()) {
            $this->addError("groupEdits.{$groupId}.key", __('That group is already taken on this layout.'));

            return;
        }

        $oldPrefix = $group->key;

        $group->update([
            'key' => $key,
            'label' => $this->groupEdits[$groupId]['label'],
        ]);

        if ($oldPrefix !== $key) {
            $this->rewritePublishedNames($oldPrefix.'.', $key.'.', $state);
        }

        $this->reload();

        $this->toast(__('Updated group :key.', ['key' => $key]));
    }

    public function addTextKey(int $groupId): void
    {
        $this->authorize(Access::LAYOUTS_EDIT);
        $group = $this->textGroups->firstWhere('id', $groupId);

        if (! $group) {
            return;
        }

        $this->validate([
            "newFields.{$groupId}.key" => ['required', 'regex:/^[A-Za-z][A-Za-z0-9_]*$/', 'max:60'],
            "newFields.{$groupId}.label" => 'required|string|max:80',
            "newFields.{$groupId}.description" => 'nullable|string|max:240',
        ], [
            "newFields.{$groupId}.key.regex" => __('Field keys become the name after the group: letters, numbers and underscores, starting with a letter.'),
        ]);

        $draft = $this->newFields[$groupId];
        $key = $draft['key'];

        if (TextKey::where('group_id', $group->id)->where('key', $key)->exists()) {
            $this->addError("newFields.{$groupId}.key", __('That key is already taken in this group.'));

            return;
        }

        $textKey = $group->textKeys()->create([
            'key' => $key,
            'label' => $draft['label'],
            'description' => ($draft['description'] ?? '') ?: null,
            'sort_order' => (int) $group->textKeys()->max('sort_order') + 1,
        ]);

        $this->layout->loadMissing('shows');

        foreach ($this->layout->shows as $show) {
            $show->textDefaults()->firstOrCreate(
                ['text_key_id' => $textKey->id],
                ['default_value' => ''],
            );
        }

        $this->newFields[$groupId] = ['key' => '', 'label' => '', 'description' => ''];
        $this->reload();

        $this->toast(__('Added :key.', ['key' => $textKey->fieldName()]));
    }

    public function saveTextKey(int $textKeyId, ShowStateManager $state): void
    {
        $this->authorize(Access::LAYOUTS_EDIT);
        $textKey = $this->textGroups->flatMap->textKeys->firstWhere('id', $textKeyId);

        if (! $textKey) {
            return;
        }

        $this->validate([
            "textKeyEdits.{$textKeyId}.key" => ['required', 'regex:/^[A-Za-z][A-Za-z0-9_]*$/', 'max:60'],
            "textKeyEdits.{$textKeyId}.label" => 'required|string|max:80',
            "textKeyEdits.{$textKeyId}.description" => 'nullable|string|max:240',
        ], [
            "textKeyEdits.{$textKeyId}.key.regex" => __('Field keys become the name after the group: letters, numbers and underscores, starting with a letter.'),
        ]);

        $draft = $this->textKeyEdits[$textKeyId];
        $key = $draft['key'];

        if ($key !== $textKey->key && TextKey::where('group_id', $textKey->group_id)->where('key', $key)->exists()) {
            $this->addError("textKeyEdits.{$textKeyId}.key", __('That key is already taken in this group.'));

            return;
        }

        $from = $textKey->fieldName();

        $textKey->update([
            'key' => $key,
            'label' => $draft['label'],
            'description' => ($draft['description'] ?? '') ?: null,
        ]);

        $to = $textKey->fresh(['group'])->fieldName();

        if ($from !== $to) {
            $this->rewritePublishedNames($from, $to, $state);
        }

        $this->reload();

        $this->toast(__('Updated :key.', ['key' => $to]));
    }

    public function deleteTextGroup(int $groupId, ShowStateManager $state): void
    {
        $this->authorize(Access::LAYOUTS_EDIT);
        $group = $this->textGroups->firstWhere('id', $groupId);

        if (! $group) {
            return;
        }

        foreach ($group->textKeys as $textKey) {
            $this->dropPublishedName($textKey->fieldName(), $state);
        }

        $key = $group->key;
        $group->delete();
        $this->reload();

        $this->toast(__('Removed group :key.', ['key' => $key]));
    }

    public function deleteTextKey(int $textKeyId, ShowStateManager $state): void
    {
        $this->authorize(Access::LAYOUTS_EDIT);
        $textKey = $this->textGroups->flatMap->textKeys->firstWhere('id', $textKeyId);

        if (! $textKey) {
            return;
        }

        $name = $textKey->fieldName();
        $this->dropPublishedName($name, $state);
        $textKey->delete();
        $this->reload();

        $this->toast(__('Removed :key.', ['key' => $name]));
    }

    public function moveTextGroup(int $groupId, int $direction): void
    {
        $this->authorize(Access::LAYOUTS_EDIT);
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

        $this->reload();
    }

    public function moveTextKey(int $textKeyId, int $direction): void
    {
        $this->authorize(Access::LAYOUTS_EDIT);
        $textKey = $this->textGroups->flatMap->textKeys->firstWhere('id', $textKeyId);

        if (! $textKey) {
            return;
        }

        $keys = $this->textGroups
            ->firstWhere('id', $textKey->group_id)
            ?->textKeys
            ->values() ?? collect();
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

        $this->reload();
    }

    /**
     * Caption fields live on the layout, so renaming a key immediately changes
     * the data source for every box of this type. Rewrite live values so a
     * caption already on air is not orphaned under the old name.
     */
    protected function rewritePublishedNames(string $from, string $to, ShowStateManager $state): void
    {
        $this->layout->loadMissing('shows');

        foreach ($this->layout->shows as $show) {
            $text = $show->current_state['text'] ?? [];

            foreach ($text as $name => $value) {
                if ($name === $from || str_starts_with($name, $from)) {
                    $state->renameTextField($show->fresh(), $name, $to.substr($name, strlen($from)));
                }
            }
        }
    }

    protected function dropPublishedName(string $name, ShowStateManager $state): void
    {
        $this->layout->loadMissing('shows');

        foreach ($this->layout->shows as $show) {
            $state->dropTextField($show, $name);
        }
    }

    /**
     * @return array{0: int|null|false, 1: int|null}
     */
    protected function pairDimensions(mixed $width, mixed $height): array
    {
        $width = $width === '' || $width === null ? null : (int) $width;
        $height = $height === '' || $height === null ? null : (int) $height;

        if (($width === null) !== ($height === null)) {
            return [false, null];
        }

        return [$width, $height];
    }

    protected function reload(): void
    {
        $this->layout->load(['sections', 'textGroups.textKeys', 'shows']);
        $this->syncSectionEdits();
        $this->syncCatalogEdits();
        unset($this->sections, $this->textGroups);
    }

    protected function syncSectionEdits(): void
    {
        $this->sectionEdits = $this->layout->sections
            ->mapWithKeys(fn ($section) => [$section->id => [
                'key' => $section->key,
                'label' => $section->label,
                'width' => $section->width !== null ? (string) $section->width : '',
                'height' => $section->height !== null ? (string) $section->height : '',
            ]])
            ->all();
    }

    protected function syncCatalogEdits(): void
    {
        $this->groupEdits = $this->layout->textGroups
            ->mapWithKeys(fn (TextGroup $group) => [$group->id => [
                'key' => $group->key,
                'label' => $group->label,
            ]])
            ->all();

        $this->textKeyEdits = $this->layout->textGroups
            ->flatMap->textKeys
            ->mapWithKeys(fn (TextKey $textKey) => [$textKey->id => [
                'key' => $textKey->key,
                'label' => $textKey->label,
                'description' => (string) $textKey->description,
            ]])
            ->all();

        $ids = $this->layout->textGroups->pluck('id');

        $this->newFields = $ids
            ->mapWithKeys(fn (int $id) => [$id => $this->newFields[$id] ?? [
                'key' => '',
                'label' => '',
                'description' => '',
            ]])
            ->all();
    }
}
