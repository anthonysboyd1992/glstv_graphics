<?php

namespace Tests\Feature;

use App\Livewire\Shows\Board;
use App\Models\Asset;
use App\Models\LookItem;
use App\Models\Show;
use App\Models\TextKey;
use App\Models\User;
use App\Services\Assets\AssetImporter;
use App\Services\Shows\DefaultLayout;
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

        Storage::fake('local');
        $this->seed(DirtTrackSeeder::class);
        $this->show = Show::firstOrFail();
        $this->actingAs(User::factory()->create());
    }

    public function test_taking_a_cue_does_not_change_live_text(): void
    {
        $look = $this->show->looks()->firstOrFail();

        $this->show->forceFill([
            'current_state' => ['sections' => [], 'text' => ['now_racing' => 'Sprints Heat 2']],
        ])->save();

        Livewire::test(Board::class, ['show' => $this->show])
            ->call('arm', $look->id)
            ->call('take');

        $this->show->refresh();

        $this->assertSame($look->id, $this->show->active_look_id);
        $this->assertSame('Sprints Heat 2', $this->show->textValue('now_racing'));
    }

    public function test_selecting_a_cue_arms_it_without_touching_air(): void
    {
        $look = $this->show->looks()->firstOrFail();

        Livewire::test(Board::class, ['show' => $this->show])
            ->call('arm', $look->id);

        $this->show->refresh();

        $this->assertSame($look->id, $this->show->preview_look_id);
        $this->assertNull($this->show->active_look_id);
        $this->assertNull($this->show->textValue('now_racing'));
    }

    public function test_stepping_moves_the_armed_cue_and_leaves_air_alone(): void
    {
        $looks = $this->show->looks()->get();

        $component = Livewire::test(Board::class, ['show' => $this->show])
            ->call('step', 1)
            ->call('step', 1);

        $this->assertSame($looks[1]->id, $this->show->refresh()->preview_look_id);
        $this->assertNull($this->show->active_look_id);

        $component->call('step', -1);

        $this->assertSame($looks[0]->id, $this->show->refresh()->preview_look_id);
    }

    public function test_take_airs_the_armed_cue_and_arms_the_next_one(): void
    {
        $looks = $this->show->looks()->get();

        Livewire::test(Board::class, ['show' => $this->show])
            ->call('arm', $looks[0]->id)
            ->call('take');

        $this->show->refresh();

        $this->assertSame($looks[0]->id, $this->show->active_look_id);
        $this->assertSame($looks[1]->id, $this->show->preview_look_id);
    }

    public function test_take_does_nothing_when_no_cue_is_armed(): void
    {
        Livewire::test(Board::class, ['show' => $this->show])
            ->call('take');

        $this->show->refresh();

        $this->assertNull($this->show->active_look_id);
        $this->assertNull($this->show->textValue('now_racing'));
    }

    public function test_taking_the_last_cue_leaves_nothing_armed(): void
    {
        $last = $this->show->looks()->get()->last();

        Livewire::test(Board::class, ['show' => $this->show])
            ->call('arm', $last->id)
            ->call('take');

        $this->show->refresh();

        $this->assertSame($last->id, $this->show->active_look_id);
        $this->assertNull($this->show->preview_look_id);
    }

    public function test_a_cue_only_changes_the_sections_it_names(): void
    {
        $look = $this->show->looks()->firstOrFail();
        $kept = $this->storeAsset('corner-mark', 500, 500);
        $incoming = $this->storeAsset('score-bug', 1920, 180);

        $look->items()->create([
            'section_key' => 'ScoreBug',
            'action' => LookItem::ACTION_SET,
            'asset_id' => $incoming->id,
        ]);

        $this->show->forceFill([
            'current_state' => [
                'sections' => ['UpperRight' => ['asset_id' => $kept->id]],
                'text' => ['announcement' => 'Rain hold'],
            ],
        ])->save();

        Livewire::test(Board::class, ['show' => $this->show])
            ->call('arm', $look->id)
            ->call('take');

        $this->show->refresh();

        $this->assertSame($incoming->id, $this->show->sectionAssetId('ScoreBug'));
        $this->assertSame($kept->id, $this->show->sectionAssetId('UpperRight'));
        $this->assertSame('Rain hold', $this->show->textValue('announcement'));
    }

    public function test_clearing_a_section_in_a_cue_empties_it(): void
    {
        $look = $this->show->looks()->firstOrFail();
        $asset = $this->storeAsset('score-bug', 1920, 180);

        $look->items()->create([
            'section_key' => 'ScoreBug',
            'action' => LookItem::ACTION_CLEAR,
        ]);

        $this->show->forceFill([
            'current_state' => ['sections' => ['ScoreBug' => ['asset_id' => $asset->id]], 'text' => []],
        ])->save();

        Livewire::test(Board::class, ['show' => $this->show])
            ->call('arm', $look->id)
            ->call('take');

        $this->assertNull($this->show->refresh()->sectionAssetId('ScoreBug'));
    }

    public function test_a_manual_assignment_takes_the_show_off_the_rundown(): void
    {
        $asset = $this->storeAsset('score-bug', 1920, 180);
        $look = $this->show->looks()->firstOrFail();

        Livewire::test(Board::class, ['show' => $this->show])
            ->call('arm', $look->id)
            ->call('take')
            ->call('assign', 'ScoreBug', $asset->id);

        $this->show->refresh();

        $this->assertNull($this->show->active_look_id);
        $this->assertSame($asset->id, $this->show->sectionAssetId('ScoreBug'));
    }

    public function test_taking_a_cue_puts_its_asset_on_air(): void
    {
        $asset = $this->storeAsset('sprint-logo', 500, 500);

        $look = $this->show->looks()->firstOrFail();
        $look->items()->create([
            'section_key' => 'UpperRight',
            'action' => LookItem::ACTION_SET,
            'asset_id' => $asset->id,
        ]);

        Livewire::test(Board::class, ['show' => $this->show->fresh()])
            ->call('arm', $look->id)
            ->call('take');

        $this->assertSame($asset->id, $this->show->refresh()->sectionAssetId('UpperRight'));
    }

    public function test_arming_a_cue_previews_its_pictures_on_deck_without_touching_air(): void
    {
        $kept = $this->storeAsset('corner-mark', 500, 500);
        $incoming = $this->storeAsset('score-bug', 1920, 180);
        $look = $this->show->looks()->firstOrFail();

        $look->items()->create([
            'section_key' => 'ScoreBug',
            'action' => LookItem::ACTION_SET,
            'asset_id' => $incoming->id,
        ]);

        $this->show->forceFill([
            'current_state' => [
                'sections' => ['UpperRight' => ['asset_id' => $kept->id]],
                'text' => [],
            ],
        ])->save();

        $slots = Livewire::test(Board::class, ['show' => $this->show->fresh()])
            ->assertSee(__('Select a cue to preview the next pictures here.'))
            ->call('arm', $look->id)
            ->assertSee(__('Pictures in this cue. Blank sections are left alone on air.'))
            ->instance()
            ->onDeckSlots;

        $this->assertSame($incoming->id, $slots['ScoreBug']['asset']?->id);
        $this->assertSame('set', $slots['ScoreBug']['change']);
        $this->assertNull($slots['UpperRight']['asset']?->id);
        $this->assertSame('leave', $slots['UpperRight']['change']);
        $this->assertNull($this->show->refresh()->sectionAssetId('ScoreBug'));
        $this->assertSame($kept->id, $this->show->sectionAssetId('UpperRight'));
    }

    public function test_switching_on_deck_cues_changes_the_previewed_picture(): void
    {
        $first = $this->storeAsset('heat-logo', 1920, 180);
        $second = $this->storeAsset('feature-logo', 1920, 180);
        $looks = $this->show->looks()->get();

        $looks[0]->items()->create([
            'section_key' => 'ScoreBug',
            'action' => LookItem::ACTION_SET,
            'asset_id' => $first->id,
        ]);

        $looks[1]->items()->create([
            'section_key' => 'ScoreBug',
            'action' => LookItem::ACTION_SET,
            'asset_id' => $second->id,
        ]);

        $component = Livewire::test(Board::class, ['show' => $this->show->fresh()])
            ->call('arm', $looks[0]->id);

        $this->assertSame($first->id, $component->instance()->onDeckSlots['ScoreBug']['asset']?->id);

        $component
            ->call('arm', $looks[1]->id)
            ->assertSee($second->name);

        $this->assertSame($second->id, $component->instance()->onDeckSlots['ScoreBug']['asset']?->id);
        $this->assertSame('set', $component->instance()->onDeckSlots['ScoreBug']['change']);
        $this->assertNull($this->show->refresh()->sectionAssetId('ScoreBug'));
    }

    public function test_a_text_field_can_be_added_renamed_and_removed_from_the_board(): void
    {
        $component = Livewire::test(Board::class, ['show' => $this->show])
            ->set('newTextKey', 'Track Conditions')
            ->call('addTextKey')
            ->assertHasNoErrors();

        $key = TextKey::where('key', 'track_conditions')->firstOrFail();
        $this->assertSame('Track Conditions', $key->label);
        $this->assertSame('Rundown.track_conditions', $key->fieldName());

        $component
            ->call('startTextRename', $key->id)
            ->set('textKeyLabel', 'Track State')
            ->call('renameTextKey');

        $key->refresh();
        $this->assertSame('Track State', $key->label);
        $this->assertSame('track_conditions', $key->key);

        $component->call('deleteTextKey', $key->id);

        $this->assertDatabaseMissing('text_keys', ['id' => $key->id]);
    }

    public function test_a_text_field_default_can_be_updated_and_fields_reordered(): void
    {
        $keys = TextKey::catalog($this->show->layout);
        $first = $keys[0];
        $second = $keys[1];

        Livewire::test(Board::class, ['show' => $this->show])
            ->assertSeeInOrder(['Rundown', 'Text', 'Routing grid'])
            ->assertSee('Rundown.now_racing')
            ->set('defaults.'.$first->id, 'Sprints Heat 1')
            ->call('saveTextDefault', $first->id)
            ->call('moveTextKey', $first->id, 1);

        $this->assertSame('Sprints Heat 1', $this->show->fresh()->defaultFor($first->key));

        $reordered = TextKey::catalog($this->show->fresh()->layout)->pluck('id');

        $this->assertSame([$second->id, $first->id], $reordered->take(2)->all());
    }

    public function test_a_section_can_be_added_on_this_show_without_touching_another(): void
    {
        $other = Show::create(['name' => 'Other Night']);
        DefaultLayout::install($other);

        Livewire::test(Board::class, ['show' => $this->show])
            ->set('newSection.key', 'Ticker')
            ->set('newSection.label', 'Ticker')
            ->set('newSection.width', '1920')
            ->set('newSection.height', '80')
            ->call('addSection')
            ->assertHasNoErrors();

        $this->assertTrue($this->show->sections()->where('key', 'Ticker')->exists());
        $this->assertFalse($other->sections()->where('key', 'Ticker')->exists());
    }

    public function test_editing_a_section_size_refits_on_air_and_cue_pictures(): void
    {
        $square = $this->storeAsset('corner-mark', 500, 500);
        $section = $this->show->sections()->where('key', 'ScoreBug')->firstOrFail();
        $look = $this->show->looks()->firstOrFail();

        Livewire::test(Board::class, ['show' => $this->show])
            ->call('assign', 'ScoreBug', $square->id);

        $look->items()->create([
            'section_key' => 'ScoreBug',
            'action' => LookItem::ACTION_SET,
            'asset_id' => $this->show->refresh()->sectionAssetId('ScoreBug'),
        ]);

        $this->show->forceFill(['active_look_id' => $look->id])->save();

        Livewire::test(Board::class, ['show' => $this->show->fresh()])
            ->set('sectionEdits.'.$section->id.'.width', '1280')
            ->set('sectionEdits.'.$section->id.'.height', '120')
            ->call('saveSection', $section->id)
            ->assertHasNoErrors();

        $section->refresh();
        $this->show->refresh();

        $this->assertSame(1280, $section->width);
        $this->assertSame(120, $section->height);

        $onAir = Asset::query()->find($this->show->sectionAssetId('ScoreBug'));
        $inCue = Asset::query()->find($look->items()->where('section_key', 'ScoreBug')->value('asset_id'));

        $this->assertSame(1280, $onAir->width);
        $this->assertSame(120, $onAir->height);
        $this->assertSame($square->id, $onAir->source_asset_id);
        $this->assertSame(1280, $inCue->width);
        $this->assertSame(120, $inCue->height);
        $this->assertSame($look->id, $this->show->active_look_id);
    }

    public function test_renaming_a_section_key_moves_live_state_and_cue_cells(): void
    {
        $asset = $this->storeAsset('score-bug', 1920, 180);
        $section = $this->show->sections()->where('key', 'ScoreBug')->firstOrFail();
        $look = $this->show->looks()->firstOrFail();

        Livewire::test(Board::class, ['show' => $this->show])
            ->call('assign', 'ScoreBug', $asset->id);

        $look->items()->create([
            'section_key' => 'ScoreBug',
            'action' => LookItem::ACTION_SET,
            'asset_id' => $asset->id,
        ]);

        Livewire::test(Board::class, ['show' => $this->show->fresh()])
            ->set('sectionEdits.'.$section->id.'.key', 'LowerBar')
            ->set('sectionEdits.'.$section->id.'.label', 'Lower Bar')
            ->call('saveSection', $section->id)
            ->assertHasNoErrors();

        $this->show->refresh();

        $this->assertSame('LowerBar', $section->fresh()->key);
        $this->assertSame($asset->id, $this->show->sectionAssetId('LowerBar'));
        $this->assertNull($this->show->sectionAssetId('ScoreBug'));
        $this->assertSame('LowerBar', $look->items()->first()->section_key);
    }

    public function test_text_keys_are_shared_but_values_and_defaults_stay_on_this_box(): void
    {
        $other = Show::where('name', 'GLSTV2')->first() ?? Show::create(['name' => 'GLSTV2']);

        if ($other->sections()->doesntExist()) {
            DefaultLayout::install($other);
        }

        $nowRacing = TextKey::query()->where('key', 'now_racing')->firstOrFail();

        Livewire::test(Board::class, ['show' => $this->show])
            ->set('newTextKey', 'Track Conditions')
            ->call('addTextKey')
            ->set('defaults.'.$nowRacing->id, 'Sprints Heat 1')
            ->call('saveTextDefault', $nowRacing->id)
            ->set('text.'.$nowRacing->id, 'GLSS Heat 1')
            ->call('saveText', $nowRacing->id)
            ->assertHasNoErrors();

        $this->assertTrue(TextKey::where('key', 'track_conditions')->exists());

        Livewire::test(Board::class, ['show' => $other->fresh()])
            ->assertSee('Track Conditions')
            ->assertSee('track_conditions')
            ->assertSet('text.'.$nowRacing->id, $other->fresh()->defaultFor('now_racing'))
            ->assertSet('defaults.'.$nowRacing->id, $other->fresh()->defaultFor('now_racing'));

        $this->assertSame('Sprints Heat 1', $this->show->fresh()->defaultFor('now_racing'));
        $this->assertSame('GLSS Heat 1', $this->show->fresh()->textValue('now_racing'));
        $this->assertNotSame('Sprints Heat 1', $other->fresh()->defaultFor('now_racing'));
        $this->assertNull($other->fresh()->textValue('now_racing'));
    }

    public function test_saving_text_from_the_board_does_not_arm_or_take_a_cue(): void
    {
        $nowRacing = TextKey::query()->where('key', 'now_racing')->firstOrFail();

        Livewire::test(Board::class, ['show' => $this->show])
            ->set('text.'.$nowRacing->id, 'GLSS Heat 1')
            ->call('saveText', $nowRacing->id);

        $this->show->refresh();

        $this->assertSame('GLSS Heat 1', $this->show->textValue('now_racing'));
        $this->assertNull($this->show->active_look_id);
        $this->assertNull($this->show->preview_look_id);
    }

    public function test_focusing_a_section_sorts_fitting_assets_first_without_hiding_the_rest(): void
    {
        $wide = $this->storeAsset('score-bug', 1920, 180);
        $square = $this->storeAsset('corner-mark', 500, 500);

        $component = Livewire::test(Board::class, ['show' => $this->show])
            ->set('focusSection', 'ScoreBug')
            ->assertSee($wide->name)
            ->assertSee($square->name);

        $names = $component->instance()->assets->pluck('name')->all();

        $this->assertLessThan(
            array_search($square->name, $names, true),
            array_search($wide->name, $names, true),
        );
    }

    public function test_an_off_ratio_asset_is_fitted_to_the_section(): void
    {
        $square = $this->storeAsset('corner-mark', 500, 500);

        Livewire::test(Board::class, ['show' => $this->show])
            ->call('assign', 'ScoreBug', $square->id);

        $assigned = Asset::query()->find($this->show->refresh()->sectionAssetId('ScoreBug'));

        $this->assertNotNull($assigned);
        $this->assertNotSame($square->id, $assigned->id);
        $this->assertSame($square->id, $assigned->source_asset_id);
        $this->assertSame(1920, $assigned->width);
        $this->assertSame(180, $assigned->height);
        $this->assertSame('corner-mark', $assigned->name);
    }

    public function test_an_exact_size_asset_is_assigned_without_copying(): void
    {
        $bug = $this->storeAsset('score-bug', 1920, 180);

        Livewire::test(Board::class, ['show' => $this->show])
            ->call('assign', 'ScoreBug', $bug->id);

        $this->assertSame($bug->id, $this->show->refresh()->sectionAssetId('ScoreBug'));
        $this->assertSame(1, Asset::count());
    }

    public function test_fitting_the_same_asset_twice_reuses_the_sized_copy(): void
    {
        $square = $this->storeAsset('corner-mark', 500, 500);

        Livewire::test(Board::class, ['show' => $this->show])
            ->call('assign', 'ScoreBug', $square->id);

        $first = $this->show->refresh()->sectionAssetId('ScoreBug');

        Livewire::test(Board::class, ['show' => $this->show])
            ->call('clearSection', 'ScoreBug')
            ->call('assign', 'ScoreBug', $square->id);

        $this->assertSame($first, $this->show->refresh()->sectionAssetId('ScoreBug'));
        $this->assertSame(2, Asset::count());
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

    public function test_graphics_cannot_assign_an_asset(): void
    {
        $this->actingAs(User::factory()->graphics()->create());
        $bug = $this->storeAsset('score-bug', 1920, 180);

        Livewire::test(Board::class, ['show' => $this->show])
            ->call('assign', 'ScoreBug', $bug->id)
            ->assertForbidden();
    }

    public function test_graphics_cannot_take_a_cue(): void
    {
        $this->actingAs(User::factory()->graphics()->create());
        $look = $this->show->looks()->firstOrFail();

        Livewire::test(Board::class, ['show' => $this->show])
            ->call('arm', $look->id)
            ->assertForbidden();
    }

    public function test_graphics_cannot_edit_live_captions(): void
    {
        $this->actingAs(User::factory()->graphics()->create());
        $nowRacing = TextKey::query()->where('key', 'now_racing')->firstOrFail();

        Livewire::test(Board::class, ['show' => $this->show])
            ->set('text.'.$nowRacing->id, 'Heat 1')
            ->call('saveText', $nowRacing->id)
            ->assertForbidden();
    }

    public function test_operator_cannot_add_a_section(): void
    {
        $this->actingAs(User::factory()->operator()->create());

        Livewire::test(Board::class, ['show' => $this->show])
            ->set('newSection.key', 'Ticker')
            ->set('newSection.label', 'Ticker')
            ->call('addSection')
            ->assertForbidden();
    }

    protected function storeAsset(string $name, int $width, int $height): Asset
    {
        return app(AssetImporter::class)->import(
            UploadedFile::fake()->image("{$name}.png", $width, $height),
            $name,
        );
    }
}
