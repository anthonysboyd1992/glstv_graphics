<?php

namespace Tests\Feature;

use App\Livewire\Layouts\Editor;
use App\Livewire\Layouts\Index as Layouts;
use App\Livewire\Shows\Board;
use App\Livewire\Shows\Index as Shows;
use App\Models\Layout;
use App\Models\Show;
use App\Models\User;
use App\Services\Shows\DataSourceBuilder;
use App\Services\Shows\DefaultLayout;
use Database\Seeders\DirtTrackSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class LayoutTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DirtTrackSeeder::class);
        $this->actingAs(User::factory()->create());
    }

    public function test_the_layouts_page_lists_the_dirt_track_set(): void
    {
        $this->get(route('layouts.index'))
            ->assertOk()
            ->assertSee('Dirt Track')
            ->assertSee('Score Bug');
    }

    public function test_slots_can_be_added_to_a_layout(): void
    {
        $layout = Layout::query()->create(['name' => 'Studio']);

        Livewire::test(Editor::class, ['layout' => $layout])
            ->set('newSection.key', 'CameraA')
            ->set('newSection.label', 'Camera A')
            ->set('newSection.width', '1920')
            ->set('newSection.height', '1080')
            ->call('addSection');

        $this->assertTrue($layout->sections()->where('key', 'CameraA')->exists());
        $this->assertSame('1920x1080', $layout->sections()->first()->dimensionLabel());
    }

    public function test_creating_a_layout_from_dirt_track_copies_its_slots_and_groups(): void
    {
        $source = Layout::query()->where('slug', 'dirt-track')->firstOrFail();

        Livewire::test(Layouts::class)
            ->set('name', 'Studio')
            ->set('description', 'Talking-head package')
            ->set('sourceLayoutId', (string) $source->id)
            ->call('create');

        $layout = Layout::query()->where('slug', 'studio')->firstOrFail();

        $this->assertSame('Talking-head package', $layout->description);
        $this->assertEqualsCanonicalizing(
            $source->sections()->pluck('key')->all(),
            $layout->sections()->pluck('key')->all(),
        );
        $this->assertEqualsCanonicalizing(
            $source->textGroups()->pluck('key')->all(),
            $layout->textGroups()->pluck('key')->all(),
        );
        $this->assertTrue(
            $layout->textGroups()->where('key', 'Rundown')->first()?->textKeys()->where('key', 'now_racing')->exists()
        );
    }

    public function test_a_new_broadcast_copies_the_chosen_layout_not_dirt_track(): void
    {
        $layout = Layout::query()->create([
            'name' => 'Awards',
            'description' => 'Full frame and lower third only',
        ]);
        $layout->sections()->create([
            'key' => 'FullFrame',
            'label' => 'Full Frame',
            'width' => 1920,
            'height' => 1080,
            'sort_order' => 0,
        ]);
        $layout->sections()->create([
            'key' => 'LowerThird',
            'label' => 'Lower Third',
            'width' => 1920,
            'height' => 300,
            'sort_order' => 1,
        ]);

        Livewire::test(Shows::class)
            ->set('name', 'GLSTV4')
            ->set('layoutId', (string) $layout->id)
            ->call('create');

        $show = Show::query()->where('name', 'GLSTV4')->firstOrFail();

        $this->assertSame($layout->id, $show->layout_id);
        $this->assertEqualsCanonicalizing(
            ['FullFrame', 'LowerThird'],
            $show->sections()->pluck('key')->all(),
        );
        $this->assertFalse($show->sections()->where('key', 'ScoreBug')->exists());
        $this->assertTrue($show->catalogTextKeys()->isEmpty());
        $this->assertArrayNotHasKey('Rundown.now_racing', app(DataSourceBuilder::class)->row($show));
    }

    public function test_editing_a_layout_does_not_change_an_existing_broadcast(): void
    {
        $show = Show::query()->firstOrFail();
        $layout = Layout::query()->where('slug', 'dirt-track')->firstOrFail();
        $section = $layout->sections()->where('key', 'ScoreBug')->firstOrFail();

        Livewire::test(Editor::class, ['layout' => $layout])
            ->set("sectionEdits.{$section->id}.key", 'BugBar')
            ->set("sectionEdits.{$section->id}.label", 'Bug Bar')
            ->set("sectionEdits.{$section->id}.width", '1920')
            ->set("sectionEdits.{$section->id}.height", '180')
            ->call('saveSection', $section->id);

        $this->assertTrue($layout->sections()->where('key', 'BugBar')->exists());
        $this->assertTrue($show->sections()->where('key', 'ScoreBug')->exists());
        $this->assertFalse($show->sections()->where('key', 'BugBar')->exists());
    }

    public function test_the_board_can_save_its_slots_as_a_new_layout(): void
    {
        $show = Show::query()->firstOrFail();
        $show->sections()->create([
            'key' => 'Ticker',
            'label' => 'Ticker',
            'width' => 1920,
            'height' => 80,
            'sort_order' => 99,
        ]);

        Livewire::test(Board::class, ['show' => $show->fresh(['sections'])])
            ->set('newLayoutName', 'Dirt Track with ticker')
            ->call('saveAsLayout');

        $layout = Layout::query()->where('name', 'Dirt Track with ticker')->firstOrFail();

        $this->assertTrue($layout->sections()->where('key', 'Ticker')->exists());
        $this->assertTrue($layout->sections()->where('key', 'ScoreBug')->exists());
        $this->assertTrue($layout->textGroups()->where('key', 'Rundown')->exists());
    }

    public function test_caption_groups_added_to_one_layout_do_not_appear_on_another(): void
    {
        $awards = Layout::query()->create(['name' => 'Awards']);
        $show = Show::query()->firstOrFail();

        $component = Livewire::test(Editor::class, ['layout' => $awards])
            ->set('newGroup.key', 'Winner')
            ->set('newGroup.label', 'Winner')
            ->call('addTextGroup')
            ->assertHasNoErrors();

        $groupId = $awards->textGroups()->where('key', 'Winner')->value('id');

        $component
            ->set("newFields.{$groupId}.key", 'driver_name')
            ->set("newFields.{$groupId}.label", 'Driver Name')
            ->set("newFields.{$groupId}.description", 'The feature winner')
            ->call('addTextKey', $groupId)
            ->assertHasNoErrors();

        $this->assertTrue($awards->textGroups()->where('key', 'Winner')->exists());
        $this->assertTrue(
            $awards->textGroups()->where('key', 'Winner')->first()->textKeys()->where('key', 'driver_name')->exists()
        );

        Livewire::test(Board::class, ['show' => $show->fresh()])
            ->assertSee('Rundown.now_racing')
            ->assertDontSee('Winner.driver_name');
    }

    public function test_caption_keys_can_be_renamed_on_the_layout(): void
    {
        $layout = Layout::query()->where('slug', 'dirt-track')->firstOrFail();
        $show = Show::query()->firstOrFail();
        $group = $layout->textGroups()->where('key', 'Rundown')->firstOrFail();
        $textKey = $group->textKeys()->where('key', 'now_racing')->firstOrFail();

        $show->forceFill([
            'current_state' => ['sections' => [], 'text' => ['Rundown.now_racing' => 'GLSS Heat 1']],
        ])->save();

        Livewire::test(Editor::class, ['layout' => $layout])
            ->set("textKeyEdits.{$textKey->id}.key", 'on_track')
            ->set("textKeyEdits.{$textKey->id}.label", 'On Track')
            ->set("textKeyEdits.{$textKey->id}.description", 'What is racing right now')
            ->call('saveTextKey', $textKey->id)
            ->assertHasNoErrors();

        $textKey->refresh();
        $this->assertSame('on_track', $textKey->key);
        $this->assertSame('On Track', $textKey->label);
        $this->assertSame('Rundown.on_track', $textKey->fieldName());
        $this->assertSame('GLSS Heat 1', $show->fresh()->textValue('Rundown.on_track'));
        $this->assertArrayHasKey('Rundown.on_track', app(DataSourceBuilder::class)->row($show->fresh()));
        $this->assertArrayNotHasKey('Rundown.now_racing', app(DataSourceBuilder::class)->row($show->fresh()));
    }

    public function test_renaming_a_group_rewrites_every_field_prefix(): void
    {
        $layout = Layout::query()->where('slug', 'dirt-track')->firstOrFail();
        $show = Show::query()->firstOrFail();
        $group = $layout->textGroups()->where('key', 'Rundown')->firstOrFail();

        $show->forceFill([
            'current_state' => ['sections' => [], 'text' => ['Rundown.now_racing' => 'Heat 1']],
        ])->save();

        Livewire::test(Editor::class, ['layout' => $layout])
            ->set("groupEdits.{$group->id}.key", 'Race')
            ->set("groupEdits.{$group->id}.label", 'Race')
            ->call('saveTextGroup', $group->id)
            ->assertHasNoErrors();

        $this->assertTrue($layout->textGroups()->where('key', 'Race')->exists());
        $this->assertSame('Heat 1', $show->fresh()->textValue('Race.now_racing'));
        $this->assertArrayHasKey('Race.now_racing', app(DataSourceBuilder::class)->row($show->fresh()));
    }

    public function test_a_layout_in_use_cannot_be_deleted(): void
    {
        Layout::query()->create(['name' => 'Studio']);
        $layout = Layout::query()->where('slug', 'dirt-track')->firstOrFail();

        Livewire::test(Layouts::class)
            ->call('delete', $layout->id);

        $this->assertNotNull(Layout::query()->find($layout->id));
    }

    public function test_an_operator_cannot_create_a_layout(): void
    {
        $this->actingAs(User::factory()->operator()->create());

        Livewire::test(Layouts::class)
            ->set('name', 'Studio')
            ->call('create')
            ->assertForbidden();
    }

    public function test_graphics_can_create_a_layout(): void
    {
        $this->actingAs(User::factory()->graphics()->create());

        Livewire::test(Layouts::class)
            ->set('name', 'Studio')
            ->call('create');

        $this->assertNotNull(Layout::query()->where('name', 'Studio')->first());
    }

    public function test_graphics_cannot_create_a_broadcast(): void
    {
        $this->actingAs(User::factory()->graphics()->create());
        $layout = Layout::query()->where('slug', 'dirt-track')->firstOrFail();

        Livewire::test(Shows::class)
            ->set('name', 'GLSTV9')
            ->set('layoutId', (string) $layout->id)
            ->call('create')
            ->assertForbidden();

        $this->assertNull(Show::query()->where('name', 'GLSTV9')->first());
    }

    public function test_the_last_layout_cannot_be_deleted(): void
    {
        Layout::query()->where('slug', '!=', 'dirt-track')->delete();
        $layout = Layout::query()->where('slug', 'dirt-track')->firstOrFail();

        Livewire::test(Layouts::class)
            ->call('delete', $layout->id);

        $this->assertNotNull(Layout::query()->find($layout->id));
    }

    public function test_installing_a_box_without_a_layout_still_gets_dirt_track_slots(): void
    {
        $show = Show::create(['name' => 'Scratch']);
        DefaultLayout::install($show);

        $this->assertTrue($show->sections()->where('key', 'ScoreBug')->exists());
        $this->assertNotNull($show->fresh()->layout_id);
    }
}
