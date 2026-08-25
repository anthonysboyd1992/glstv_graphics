<?php

namespace Tests\Feature;

use App\Models\Show;
use App\Models\User;
use Database\Seeders\DirtTrackSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ScreensTest extends TestCase
{
    use RefreshDatabase;

    protected Show $show;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DirtTrackSeeder::class);
        $this->show = Show::firstOrFail();
    }

    public function test_every_screen_renders_for_an_authenticated_user(): void
    {
        $this->actingAs(User::factory()->create());

        $routes = [
            route('shows.index'),
            route('shows.board', $this->show),
            route('shows.rundown', $this->show),
            route('assets.library'),
            route('packs.index'),
            route('templates.index'),
        ];

        foreach ($routes as $url) {
            $this->get($url)->assertOk();
        }
    }

    public function test_screens_are_closed_to_guests(): void
    {
        $this->get(route('shows.index'))->assertRedirect(route('login'));
        $this->get(route('shows.board', $this->show))->assertRedirect(route('login'));
    }

    public function test_live_feed_returns_a_single_row_with_template_defaults(): void
    {
        $response = $this->getJson($this->show->dataSourceUrl('json'));

        $response->assertOk();

        $rows = $response->json();

        $this->assertCount(1, $rows);
        $this->assertSame('', $rows[0]['ScoreBug']);
        $this->assertSame("We'll Be Right Back", $rows[0]['brb_message']);
    }

    public function test_rundown_feed_carries_one_cumulative_row_per_cue(): void
    {
        $rows = $this->getJson($this->show->dataSourceUrl('json', 'rundown'))->assertOk()->json();

        $this->assertCount($this->show->looks()->count(), $rows);
        $this->assertSame('Sprints Hot Laps', $rows[0]['now_racing']);
        $this->assertSame('Late Models Hot Laps Next', $rows[0]['next_event']);
    }

    public function test_the_xml_feed_is_well_formed(): void
    {
        $body = $this->get($this->show->dataSourceUrl('xml'))->assertOk()->getContent();

        $document = simplexml_load_string($body);

        $this->assertNotFalse($document);
        $this->assertSame("We'll Be Right Back", (string) $document->row->brb_message);
    }

    public function test_a_wrong_token_is_indistinguishable_from_a_missing_show(): void
    {
        $this->getJson(route('datasource.live.json', [
            'uuid' => $this->show->uuid,
            'token' => 'not-the-token',
        ]))->assertNotFound();
    }
}
