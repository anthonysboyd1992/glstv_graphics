<?php

namespace App\Livewire\Templates;

use App\Models\AssetRole;
use App\Models\Section;
use App\Models\ShowTemplate;
use App\Models\TextKey;
use Flux\Flux;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * Templates define the shape of a broadcast: which image sections exist, which
 * text fields exist, and which roles packs can fill. Shows are built from a
 * template, so a change here is the thing that changes every future broadcast.
 *
 * Section and text keys become field names in the data source, which is why they
 * are constrained to identifiers vMix and XML can both handle.
 */
#[Title('Templates')]
class Index extends Component
{
    public ?int $templateId = null;

    /** @var array<string, string> */
    public array $section = ['key' => '', 'label' => '', 'width' => '', 'height' => '', 'description' => ''];

    /** @var array<string, string> */
    public array $textKey = ['key' => '', 'label' => '', 'default_value' => '', 'description' => ''];

    /** @var array<string, string> */
    public array $role = ['key' => '', 'label' => ''];

    public function mount(): void
    {
        $this->templateId = ShowTemplate::value('id');
    }

    /** @return Collection<int, ShowTemplate> */
    #[Computed]
    public function templates(): Collection
    {
        return ShowTemplate::orderBy('name')->get();
    }

    #[Computed]
    public function template(): ?ShowTemplate
    {
        return $this->templateId
            ? ShowTemplate::with(['sections', 'textKeys', 'roles'])->find($this->templateId)
            : null;
    }

    public function addSection(): void
    {
        $template = $this->template;

        if (! $template) {
            return;
        }

        $data = $this->validate([
            'section.key' => ['required', 'regex:/^[A-Za-z][A-Za-z0-9_]*$/', 'max:60',
                Rule::unique('sections', 'key')->where('show_template_id', $template->id)],
            'section.label' => 'required|string|max:80',
            'section.width' => 'nullable|integer|min:1|max:16384',
            'section.height' => 'nullable|integer|min:1|max:16384',
            'section.description' => 'nullable|string|max:255',
        ], [
            'section.key.regex' => __('Keys become data source field names: letters, numbers and underscores, starting with a letter.'),
        ])['section'];

        $template->sections()->create($data + ['sort_order' => (int) $template->sections()->max('sort_order') + 1]);

        $this->reset('section');
        unset($this->template);

        Flux::toast(variant: 'success', text: __('Section added.'));
    }

    public function addTextKey(): void
    {
        $template = $this->template;

        if (! $template) {
            return;
        }

        $data = $this->validate([
            'textKey.key' => ['required', 'regex:/^[A-Za-z][A-Za-z0-9_]*$/', 'max:60',
                Rule::unique('text_keys', 'key')->where('show_template_id', $template->id)],
            'textKey.label' => 'required|string|max:80',
            'textKey.default_value' => 'nullable|string|max:500',
            'textKey.description' => 'nullable|string|max:255',
        ], [
            'textKey.key.regex' => __('Keys become data source field names: letters, numbers and underscores, starting with a letter.'),
        ])['textKey'];

        $template->textKeys()->create($data + ['sort_order' => (int) $template->textKeys()->max('sort_order') + 1]);

        $this->reset('textKey');
        unset($this->template);

        Flux::toast(variant: 'success', text: __('Text key added.'));
    }

    public function addRole(): void
    {
        $template = $this->template;

        if (! $template) {
            return;
        }

        $data = $this->validate([
            'role.key' => ['required', 'regex:/^[a-z][a-z0-9_]*$/', 'max:60',
                Rule::unique('asset_roles', 'key')->where('show_template_id', $template->id)],
            'role.label' => 'required|string|max:80',
        ], [
            'role.key.regex' => __('Role keys are lowercase with underscores, such as sponsor_a.'),
        ])['role'];

        $template->roles()->create($data + ['sort_order' => (int) $template->roles()->max('sort_order') + 1]);

        $this->reset('role');
        unset($this->template);

        Flux::toast(variant: 'success', text: __('Role added.'));
    }

    public function deleteSection(Section $section): void
    {
        $section->delete();
        unset($this->template);

        Flux::toast(text: __('Section removed.'));
    }

    public function deleteTextKey(TextKey $textKey): void
    {
        $textKey->delete();
        unset($this->template);

        Flux::toast(text: __('Text key removed.'));
    }

    public function deleteRole(AssetRole $role): void
    {
        $role->delete();
        unset($this->template);

        Flux::toast(text: __('Role removed.'));
    }
}
