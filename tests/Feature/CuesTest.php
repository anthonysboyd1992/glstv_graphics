<?php

namespace Tests\Feature;

use App\Livewire\Shows\Cues;
use App\Models\Asset;
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

class CuesTest extends TestCase
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

    public function test_a_new_cue_starts_with_every_cell_blank(): void
    {
        Livewire::test(Cues::class, ['show' => $this->show])
            ->set('addCount', 1)
            ->call('add');

        $cue = $this->show->looks()->where('name', 'Cue 1')->firstOrFail();

        $this->assertSame(0, $cue->items()->count());
    }

    public function test_adding_a_count_appends_that_many_blank_cues(): void
    {
        $after = (int) $this->show->looks()->max('sort_order');

        Livewire::test(Cues::class, ['show' => $this->show])
            ->set('addCount', 3)
            ->call('add')
            ->assertSet('addCount', 1);

        $added = $this->show->looks()->where('sort_order', '>', $after)->orderBy('sort_order')->get();

        $this->assertSame(['Cue 1', 'Cue 2', 'Cue 3'], $added->pluck('name')->all());
        $this->assertSame([$after + 1, $after + 2, $after + 3], $added->pluck('sort_order')->all());
        $this->assertSame([0, 0, 0], $added->map(fn ($cue) => $cue->items()->count())->all());
    }

    public function test_cue_numbers_continue_from_the_highest_cue_n(): void
    {
        $this->show->looks()->create(['name' => 'Cue 4', 'sort_order' => 99]);

        Livewire::test(Cues::class, ['show' => $this->show])
            ->set('addCount', 2)
            ->call('add');

        $this->assertNotNull($this->show->looks()->where('name', 'Cue 5')->first());
        $this->assertNotNull($this->show->looks()->where('name', 'Cue 6')->first());
        $this->assertNull($this->show->looks()->where('name', 'Cue 1')->first());
    }

    public function test_a_count_outside_the_allowed_range_creates_nothing(): void
    {
        $before = $this->show->looks()->count();

        Livewire::test(Cues::class, ['show' => $this->show])
            ->set('addCount', 0)
            ->call('add')
            ->assertHasErrors('addCount');

        Livewire::test(Cues::class, ['show' => $this->show])
            ->set('addCount', 101)
            ->call('add')
            ->assertHasErrors('addCount');

        $this->assertSame($before, $this->show->looks()->count());
    }

    public function test_choosing_an_asset_fills_one_cell_and_leaves_the_rest_blank(): void
    {
        $asset = $this->storeAsset('Score Bug', 1920, 180);
        $cue = $this->show->looks()->create(['name' => 'GLSS Heat 1 extra', 'sort_order' => 99]);

        Livewire::test(Cues::class, ['show' => $this->show])
            ->call('setSection', $cue->id, 'ScoreBug', 'asset:'.$asset->id);

        $items = $cue->items()->get();

        $this->assertCount(1, $items);
        $this->assertSame('ScoreBug', $items[0]->section_key);
        $this->assertSame($asset->id, $items[0]->asset_id);
        $this->assertSame(LookItem::ACTION_SET, $items[0]->action);
    }

    public function test_the_asset_picker_lists_graphics_alphabetically(): void
    {
        $this->storeAsset('Zebra Plate', 100, 100);
        $this->storeAsset('Alpha Bug', 200, 200);
        $cue = $this->show->looks()->create(['name' => 'GLSS Heat 1 extra', 'sort_order' => 99]);

        $names = Livewire::test(Cues::class, ['show' => $this->show])
            ->instance()
            ->assetsBySection['ScoreBug']
            ->pluck('name')
            ->all();

        $this->assertSame(['Alpha Bug', 'Zebra Plate'], array_values(array_intersect($names, ['Alpha Bug', 'Zebra Plate'])));
        $this->assertTrue(array_search('Alpha Bug', $names, true) < array_search('Zebra Plate', $names, true));
        $this->assertNotNull($cue->id);
    }

    public function test_the_grid_shows_the_picture_for_a_filled_cell(): void
    {
        $asset = $this->storeAsset('Score Bug', 1920, 180);
        $cue = $this->show->looks()->create(['name' => 'GLSS Heat 1 extra', 'sort_order' => 99]);
        $cue->items()->create([
            'section_key' => 'ScoreBug',
            'action' => LookItem::ACTION_SET,
            'asset_id' => $asset->id,
        ]);

        Livewire::test(Cues::class, ['show' => $this->show])
            ->assertSeeHtml($asset->publicPath());
    }

    public function test_the_asset_picker_renders_outside_the_scrolling_table(): void
    {
        Livewire::test(Cues::class, ['show' => $this->show])
            ->assertSeeHtml('popover')
            ->assertSee(__('Assets'));
    }

    public function test_the_grid_previews_the_original_when_the_cell_stores_a_fitted_copy(): void
    {
        $asset = $this->storeAsset('Corner Mark', 500, 500);
        $cue = $this->show->looks()->create(['name' => 'GLSS Heat 1 extra', 'sort_order' => 99]);

        Livewire::test(Cues::class, ['show' => $this->show])
            ->call('setSection', $cue->id, 'ScoreBug', 'asset:'.$asset->id)
            ->assertSeeHtml($asset->publicPath());

        $fitted = Asset::query()->findOrFail($cue->items()->value('asset_id'));

        $this->assertNotSame($asset->id, $fitted->id);
        $this->assertSame($asset->id, $fitted->source_asset_id);
    }

    public function test_choosing_an_off_size_asset_stores_a_fitted_copy(): void
    {
        $asset = $this->storeAsset('Corner Mark', 500, 500);
        $cue = $this->show->looks()->create(['name' => 'GLSS Heat 1 extra', 'sort_order' => 99]);

        Livewire::test(Cues::class, ['show' => $this->show])
            ->call('setSection', $cue->id, 'ScoreBug', 'asset:'.$asset->id);

        $item = $cue->items()->firstOrFail();
        $fitted = Asset::query()->findOrFail($item->asset_id);

        $this->assertNotSame($asset->id, $fitted->id);
        $this->assertSame($asset->id, $fitted->source_asset_id);
        $this->assertSame(1920, $fitted->width);
        $this->assertSame(180, $fitted->height);
    }

    public function test_setting_a_cell_back_to_blank_removes_it(): void
    {
        $asset = $this->storeAsset('Score Bug', 1920, 180);
        $cue = $this->show->looks()->create(['name' => 'GLSS Heat 1 extra', 'sort_order' => 99]);

        Livewire::test(Cues::class, ['show' => $this->show])
            ->call('setSection', $cue->id, 'ScoreBug', 'asset:'.$asset->id)
            ->call('setSection', $cue->id, 'ScoreBug', 'leave');

        $this->assertSame(0, $cue->items()->count());
    }

    public function test_clear_is_distinct_from_blank(): void
    {
        $cue = $this->show->looks()->create(['name' => 'GLSS Heat 1 extra', 'sort_order' => 99]);

        Livewire::test(Cues::class, ['show' => $this->show])
            ->call('setSection', $cue->id, 'LowerThird', 'clear');

        $this->assertSame(LookItem::ACTION_CLEAR, $cue->items()->firstOrFail()->action);
        $this->assertSame('LowerThird', $cue->items()->firstOrFail()->section_key);
    }

    public function test_changing_a_logo_on_the_live_cue_updates_the_feed(): void
    {
        $asset = $this->storeAsset('Score Bug', 1920, 180);
        $cue = $this->show->looks()->firstOrFail();

        app(\App\Services\Shows\ShowStateManager::class)->applyLook($this->show, $cue);
        $this->show->refresh();

        $before = $this->getJson($this->show->dataSourceUrl('json'))->json()[0]['ScoreBug'];
        $this->assertStringContainsString('/assets/empty.png', $before);

        Livewire::test(Cues::class, ['show' => $this->show])
            ->call('setSection', $cue->id, 'ScoreBug', 'asset:'.$asset->id);

        $this->show->refresh();
        $row = $this->getJson($this->show->dataSourceUrl('json'))->json()[0];
        $item = $cue->items()->where('section_key', 'ScoreBug')->firstOrFail();

        $this->assertSame($cue->id, $this->show->active_look_id);
        $this->assertSame($item->asset_id, $this->show->sectionAssetId('ScoreBug'));
        $this->assertStringNotContainsString('/assets/empty.png', $row['ScoreBug']);
        $this->assertNotSame($before, $row['ScoreBug']);
    }

    public function test_clearing_a_logo_on_the_live_cue_publishes_empty(): void
    {
        $asset = $this->storeAsset('Score Bug', 1920, 180);
        $cue = $this->show->looks()->firstOrFail();
        $cue->items()->create([
            'section_key' => 'ScoreBug',
            'action' => LookItem::ACTION_SET,
            'asset_id' => $asset->id,
        ]);

        app(\App\Services\Shows\ShowStateManager::class)->applyLook($this->show, $cue->load('items'));
        $this->show->refresh();

        $this->assertStringNotContainsString(
            '/assets/empty.png',
            $this->getJson($this->show->dataSourceUrl('json'))->json()[0]['ScoreBug'],
        );

        Livewire::test(Cues::class, ['show' => $this->show])
            ->call('setSection', $cue->id, 'ScoreBug', 'leave');

        $this->show->refresh();
        $row = $this->getJson($this->show->dataSourceUrl('json'))->json()[0];

        $this->assertSame($cue->id, $this->show->active_look_id);
        $this->assertNull($this->show->sectionAssetId('ScoreBug'));
        $this->assertStringContainsString('/assets/empty.png', $row['ScoreBug']);
    }

    public function test_changing_a_logo_on_a_cue_that_is_not_live_leaves_the_feed_alone(): void
    {
        $cues = $this->show->looks()->orderBy('sort_order')->get();
        $asset = $this->storeAsset('Score Bug', 1920, 180);

        app(\App\Services\Shows\ShowStateManager::class)->applyLook($this->show, $cues[0]);
        $this->show->refresh();

        $beforeState = $this->show->current_state;
        $beforeUrl = $this->getJson($this->show->dataSourceUrl('json'))->json()[0]['ScoreBug'];

        Livewire::test(Cues::class, ['show' => $this->show])
            ->call('setSection', $cues[1]->id, 'ScoreBug', 'asset:'.$asset->id);

        $this->show->refresh();

        $this->assertSame($cues[0]->id, $this->show->active_look_id);
        $this->assertSame($beforeState, $this->show->current_state);
        $this->assertSame($beforeUrl, $this->getJson($this->show->dataSourceUrl('json'))->json()[0]['ScoreBug']);
    }

    public function test_a_duplicate_copies_the_cells_and_lands_beneath_its_source(): void
    {
        $source = $this->show->looks()->orderBy('sort_order')->firstOrFail();
        $asset = $this->storeAsset('Score Bug', 1920, 180);
        $source->items()->create([
            'section_key' => 'ScoreBug',
            'action' => LookItem::ACTION_SET,
            'asset_id' => $asset->id,
        ]);

        Livewire::test(Cues::class, ['show' => $this->show])
            ->call('duplicate', $source->id);

        $copy = $this->show->looks()->where('name', $source->name.' (copy)')->firstOrFail();

        $this->assertSame(1, $copy->items()->count());
        $this->assertSame('ScoreBug', $copy->items()->first()->section_key);
        $this->assertSame($source->sort_order + 1, $copy->sort_order);

        $orders = $this->show->looks()->orderBy('sort_order')->pluck('sort_order');
        $this->assertSame($orders->unique()->count(), $orders->count());
    }

    public function test_moving_a_cue_swaps_it_with_its_neighbour(): void
    {
        $cues = $this->show->looks()->orderBy('sort_order')->get();

        Livewire::test(Cues::class, ['show' => $this->show])
            ->call('move', $cues[1]->id, -1);

        $reordered = $this->show->looks()->orderBy('sort_order')->pluck('id');

        $this->assertSame($cues[1]->id, $reordered[0]);
        $this->assertSame($cues[0]->id, $reordered[1]);
    }

    public function test_renaming_a_cue_keeps_its_cells(): void
    {
        $cue = $this->show->looks()->orderBy('sort_order')->firstOrFail();
        $asset = $this->storeAsset('Score Bug', 1920, 180);
        $cue->items()->create([
            'section_key' => 'ScoreBug',
            'action' => LookItem::ACTION_SET,
            'asset_id' => $asset->id,
        ]);

        Livewire::test(Cues::class, ['show' => $this->show])
            ->call('startRename', $cue->id)
            ->set('renameValue', 'GLSS Heat 3')
            ->call('rename');

        $this->assertSame('GLSS Heat 3', $cue->refresh()->name);
        $this->assertSame(1, $cue->items()->count());
    }

    public function test_deleting_the_live_cue_takes_the_show_off_air(): void
    {
        $cue = $this->show->looks()->firstOrFail();
        $this->show->forceFill(['active_look_id' => $cue->id, 'preview_look_id' => $cue->id])->save();

        Livewire::test(Cues::class, ['show' => $this->show])
            ->call('delete', $cue->id);

        $this->show->refresh();

        $this->assertNull($this->show->active_look_id);
        $this->assertNull($this->show->preview_look_id);
        $this->assertNull($this->show->looks()->find($cue->id));
    }

    public function test_bulk_delete_removes_only_the_selected_cues(): void
    {
        $cues = $this->show->looks()->orderBy('sort_order')->get();
        $keep = $cues->skip(2)->pluck('id');

        Livewire::test(Cues::class, ['show' => $this->show])
            ->set('selected', [$cues[0]->id, $cues[1]->id])
            ->call('deleteSelected')
            ->assertSet('selected', []);

        $this->assertSame($keep->all(), $this->show->looks()->orderBy('sort_order')->pluck('id')->all());
    }

    public function test_bulk_delete_of_live_and_on_deck_cues_clears_the_board(): void
    {
        $cues = $this->show->looks()->orderBy('sort_order')->get();
        $this->show->forceFill([
            'active_look_id' => $cues[0]->id,
            'preview_look_id' => $cues[1]->id,
        ])->save();

        Livewire::test(Cues::class, ['show' => $this->show])
            ->set('selected', [$cues[0]->id, $cues[1]->id])
            ->call('deleteSelected');

        $this->show->refresh();

        $this->assertNull($this->show->active_look_id);
        $this->assertNull($this->show->preview_look_id);
        $this->assertNull($this->show->looks()->find($cues[0]->id));
        $this->assertNull($this->show->looks()->find($cues[1]->id));
        $this->assertNotNull($this->show->looks()->find($cues[2]->id));
    }

    public function test_select_all_toggles_every_cue_on_this_box(): void
    {
        $ids = $this->show->looks()->orderBy('sort_order')->pluck('id')->map(fn ($id) => (string) $id)->all();

        Livewire::test(Cues::class, ['show' => $this->show])
            ->call('toggleSelectAll')
            ->assertSet('selected', $ids)
            ->call('toggleSelectAll')
            ->assertSet('selected', []);
    }

    public function test_bulk_delete_ignores_cues_on_another_box(): void
    {
        $other = Show::query()->whereKeyNot($this->show->id)->firstOrFail();
        $foreign = $other->looks()->firstOrFail();
        $local = $this->show->looks()->firstOrFail();

        Livewire::test(Cues::class, ['show' => $this->show])
            ->set('selected', [$local->id, $foreign->id])
            ->call('deleteSelected');

        $this->assertNull($this->show->looks()->find($local->id));
        $this->assertNotNull($other->looks()->find($foreign->id));
    }

    protected function storeAsset(string $name, int $width, int $height): Asset
    {
        return app(AssetImporter::class)->import(
            UploadedFile::fake()->image('art.png', $width, $height),
            $name,
        );
    }
}
