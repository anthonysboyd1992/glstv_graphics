<?php

namespace Tests\Feature;

use App\Livewire\Shows\Board;
use App\Models\Asset;
use App\Models\AssetPack;
use App\Models\LookItem;
use App\Models\Show;
use App\Models\User;
use App\Services\Assets\AssetImporter;
use Database\Seeders\DirtTrackSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class BoardTest extends TestCase
{
    use RefreshDatabase;

    protected Show $show;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DirtTrackSeeder::class);
        $this->show = Show::firstOrFail();
        $this->actingAs(User::factory()->create());
    }

    public function test_applying_a_cue_writes_its_text_and_marks_it_live(): void
    {
        $look = $this->show->looks()->firstOrFail();

        Livewire::test(Board::class, ['show' => $this->show])
            ->call('applyLook', $look->id);

        $this->show->refresh();

        $this->assertSame($look->id, $this->show->active_look_id);
        $this->assertSame('Sprints Hot Laps', $this->show->textValue('now_racing'));
    }

    public function test_stepping_moves_through_the_rundown_in_order(): void
    {
        $looks = $this->show->looks()->get();

        $component = Livewire::test(Board::class, ['show' => $this->show])
            ->call('step', 1)
            ->call('step', 1);

        $this->assertSame($looks[1]->id, $this->show->refresh()->active_look_id);

        $component->call('step', -1);

        $this->assertSame($looks[0]->id, $this->show->refresh()->active_look_id);
    }

    public function test_a_cue_only_changes_the_fields_it_names(): void
    {
        $look = $this->show->looks()->firstOrFail();

        // Something set by hand that no generated cue touches.
        $this->show->forceFill([
            'current_state' => ['sections' => [], 'text' => ['announcement' => 'Rain hold']],
        ])->save();

        Livewire::test(Board::class, ['show' => $this->show])
            ->call('applyLook', $look->id);

        $this->assertSame('Rain hold', $this->show->refresh()->textValue('announcement'));
    }

    public function test_clearing_text_in_a_cue_returns_it_to_the_template_default(): void
    {
        $look = $this->show->looks()->firstOrFail();

        $look->items()->create([
            'target_type' => LookItem::TARGET_TEXT,
            'target_key' => 'brb_message',
            'action' => LookItem::ACTION_CLEAR,
        ]);

        $this->show->forceFill([
            'current_state' => ['sections' => [], 'text' => ['brb_message' => 'Back after this']],
        ])->save();

        Livewire::test(Board::class, ['show' => $this->show])
            ->call('applyLook', $look->id);

        $this->assertSame("We'll Be Right Back", $this->show->refresh()->textValue('brb_message'));
    }

    public function test_a_manual_assignment_takes_the_show_off_the_rundown(): void
    {
        $asset = $this->storeAsset('score-bug', 1920, 180);
        $look = $this->show->looks()->firstOrFail();

        Livewire::test(Board::class, ['show' => $this->show])
            ->call('applyLook', $look->id)
            ->call('assign', 'ScoreBug', $asset->id);

        $this->show->refresh();

        $this->assertNull($this->show->active_look_id);
        $this->assertSame($asset->id, $this->show->sectionAssetId('ScoreBug'));
    }

    public function test_a_role_based_cue_resolves_through_the_shows_packs(): void
    {
        $asset = $this->storeAsset('sprint-logo', 500, 500);

        $pack = AssetPack::firstOrFail();
        $pack->items()->create(['role_key' => 'class_sprint', 'asset_id' => $asset->id]);
        $this->show->assetPacks()->sync([$pack->id => ['sort_order' => 0]]);

        $look = $this->show->looks()->firstOrFail();
        $look->items()->create([
            'target_type' => LookItem::TARGET_SECTION,
            'target_key' => 'UpperRight',
            'action' => LookItem::ACTION_SET,
            'role_key' => 'class_sprint',
        ]);

        Livewire::test(Board::class, ['show' => $this->show->fresh()])
            ->call('applyLook', $look->id);

        $this->assertSame($asset->id, $this->show->refresh()->sectionAssetId('UpperRight'));
    }

    public function test_the_grid_only_offers_assets_that_suit_the_focused_section(): void
    {
        $wide = $this->storeAsset('score-bug', 1920, 180);
        $square = $this->storeAsset('corner-mark', 500, 500);

        Livewire::test(Board::class, ['show' => $this->show])
            ->set('focusSection', 'ScoreBug')
            ->assertSee($wide->name)
            ->assertDontSee($square->name);
    }

    public function test_identical_uploads_collapse_onto_one_record(): void
    {
        Storage::fake('local');

        $importer = app(AssetImporter::class);
        $first = $importer->import(UploadedFile::fake()->image('sponsor.png', 640, 360));
        $second = $importer->import(UploadedFile::fake()->image('sponsor.png', 640, 360));

        $this->assertSame($first->id, $second->id);
        $this->assertSame(1, Asset::count());
        $this->assertSame(640, $first->width);
    }

    protected function storeAsset(string $name, int $width, int $height): Asset
    {
        Storage::fake('local');

        return app(AssetImporter::class)->import(
            UploadedFile::fake()->image("{$name}.png", $width, $height),
            $name,
        );
    }
}
