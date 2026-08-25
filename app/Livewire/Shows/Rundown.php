<?php

namespace App\Livewire\Shows;

use App\Models\AssetPack;
use App\Models\RaceClass;
use App\Models\Show;
use App\Services\Shows\RundownGenerator;
use Flux\Flux;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * Builds the night's cue stack from the race program.
 *
 * A dirt track program is regular enough to describe in a table: which classes
 * run, and how many of each phase. Everything else follows from that, which is
 * the difference between a couple of minutes of prep and an hour of it.
 */
#[Title('Build rundown')]
class Rundown extends Component
{
    public Show $show;

    /** @var array<int, array{include: bool, hot_laps: bool, heats: int, dash: bool, b_mains: int, feature: bool}> */
    public array $program = [];

    public string $order = 'phase';

    public ?string $classLogoSection = null;

    public bool $replace = true;

    /** @var array<int, int> */
    public array $packIds = [];

    public function mount(Show $show): void
    {
        $this->show = $show->load('showTemplate.sections');
        $this->packIds = $show->assetPacks->pluck('id')->all();

        foreach ($this->classes as $class) {
            $this->program[$class->id] = [
                'include' => false,
                'hot_laps' => true,
                'heats' => 2,
                'dash' => false,
                'b_mains' => 0,
                'feature' => true,
            ];
        }
    }

    /** @return Collection<int, RaceClass> */
    #[Computed]
    public function classes(): Collection
    {
        return RaceClass::orderBy('sort_order')->get();
    }

    /** @return Collection<int, AssetPack> */
    #[Computed]
    public function packs(): Collection
    {
        return AssetPack::orderBy('name')->get();
    }

    #[Computed]
    public function looks(): Collection
    {
        return $this->show->looks()->withCount('items')->get();
    }

    /**
     * Rough count of what will be generated, so the operator can sanity check
     * the program before replacing an existing stack.
     */
    #[Computed]
    public function projectedCount(): int
    {
        return collect($this->program)
            ->filter(fn (array $line) => $line['include'] ?? false)
            ->sum(fn (array $line) => ($line['hot_laps'] ? 1 : 0)
                + max(0, (int) $line['heats'])
                + ($line['dash'] ? 1 : 0)
                + max(0, (int) $line['b_mains'])
                + ($line['feature'] ? 1 : 0));
    }

    public function savePacks(): void
    {
        $this->show->assetPacks()->sync(
            collect($this->packIds)
                ->values()
                ->mapWithKeys(fn (int $id, int $index) => [$id => ['sort_order' => $index]])
                ->all()
        );

        $this->show->refresh();

        Flux::toast(variant: 'success', text: __('Packs updated.'));
    }

    public function generate(RundownGenerator $generator): void
    {
        $lines = collect($this->program)
            ->filter(fn (array $line) => $line['include'] ?? false)
            ->map(fn (array $line, int $classId) => [
                'class_id' => $classId,
                'hot_laps' => (bool) $line['hot_laps'],
                'heats' => (int) $line['heats'],
                'dash' => (bool) $line['dash'],
                'b_mains' => (int) $line['b_mains'],
                'feature' => (bool) $line['feature'],
            ])
            ->values()
            ->all();

        if ($lines === []) {
            Flux::toast(variant: 'warning', text: __('Pick at least one class to run.'));

            return;
        }

        $looks = $generator->generate($this->show, $lines, [
            'order' => $this->order,
            'replace' => $this->replace,
            'class_logo_section' => $this->classLogoSection ?: null,
        ]);

        $this->show->refresh();
        unset($this->looks);

        Flux::toast(
            variant: 'success',
            text: trans_choice(':count cue built|:count cues built', $looks->count(), ['count' => $looks->count()]),
        );
    }

    public function clearRundown(): void
    {
        $this->show->looks()->delete();
        $this->show->forceFill(['active_look_id' => null])->save();

        unset($this->looks);

        Flux::toast(text: __('Rundown cleared.'));
    }
}
