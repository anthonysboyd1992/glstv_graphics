<?php

namespace App\Livewire\Packs;

use App\Models\Asset;
use App\Models\AssetPack;
use App\Models\AssetRole;
use Flux\Flux;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Attributes\Validate;
use Livewire\Component;

/**
 * Packs fill roles. A show points at one or more packs in priority order, so
 * swapping "this week's sponsors" changes every cue that references a sponsor
 * role without touching the cue stack itself.
 */
#[Title('Asset packs')]
class Index extends Component
{
    #[Validate('required|string|max:120')]
    public string $name = '';

    public ?int $selectedPackId = null;

    /** @var array<string, int|string|null> */
    public array $assignments = [];

    public function mount(): void
    {
        $this->selectedPackId = AssetPack::value('id');
        $this->loadAssignments();
    }

    /** @return Collection<int, AssetPack> */
    #[Computed]
    public function packs(): Collection
    {
        return AssetPack::withCount('items')->orderBy('name')->get();
    }

    #[Computed]
    public function pack(): ?AssetPack
    {
        return $this->selectedPackId ? AssetPack::find($this->selectedPackId) : null;
    }

    /**
     * Roles are declared per template, but packs are shared, so the editor works
     * from the distinct set of role keys across every template.
     *
     * @return Collection<int, AssetRole>
     */
    #[Computed]
    public function roles(): Collection
    {
        return AssetRole::orderBy('sort_order')->get()->unique('key')->values();
    }

    /** @return Collection<int, Asset> */
    #[Computed]
    public function assets(): Collection
    {
        return Asset::orderBy('name')->limit(500)->get();
    }

    public function selectPack(int $packId): void
    {
        $this->selectedPackId = $packId;
        $this->loadAssignments();

        unset($this->pack);
    }

    public function createPack(): void
    {
        $this->validate();

        $pack = AssetPack::create([
            'name' => $this->name,
            'slug' => Str::slug($this->name).'-'.Str::lower(Str::random(4)),
        ]);

        $this->reset('name');
        unset($this->packs);

        $this->selectPack($pack->id);

        Flux::modal('create-pack')->close();
        Flux::toast(variant: 'success', text: "Created {$pack->name}.");
    }

    public function updatedAssignments(mixed $value, string $roleKey): void
    {
        $pack = $this->pack;

        if (! $pack) {
            return;
        }

        if (blank($value)) {
            $pack->items()->where('role_key', $roleKey)->delete();
        } else {
            $pack->items()->updateOrCreate(
                ['role_key' => $roleKey],
                ['asset_id' => (int) $value],
            );
        }

        unset($this->packs);

        Flux::toast(text: __('Pack updated.'), variant: 'success');
    }

    public function deletePack(AssetPack $pack): void
    {
        $pack->delete();

        $this->selectedPackId = AssetPack::value('id');
        $this->loadAssignments();

        unset($this->packs, $this->pack);

        Flux::toast(text: __('Pack deleted.'));
    }

    protected function loadAssignments(): void
    {
        $this->assignments = $this->pack
            ? $this->pack->items()->pluck('asset_id', 'role_key')->all()
            : [];
    }
}
