<?php

namespace Tests\Feature;

use App\Models\Layout;
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
            route('shows.cues', $this->show),
            route('layouts.index'),
            route('layouts.edit', Layout::query()->firstOrFail()),
            route('assets.library'),
            route('users.index'),
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
        $this->assertStringContainsString('/assets/empty.png', $rows[0]['ScoreBug']);
        $this->assertSame("We'll Be Right Back", $rows[0]['Break.brb_message']);
        $this->assertArrayHasKey('Rundown.now_racing', $rows[0]);
        $this->assertArrayHasKey('UpdatedAt', $rows[0]);
        $response->assertHeader('Cache-Control');
        $this->assertStringContainsString('no-store', (string) $response->headers->get('Cache-Control'));
    }

    public function test_clearing_a_section_publishes_a_fresh_empty_image_url(): void
    {
        $this->actingAs(User::factory()->create());

        $before = $this->getJson($this->show->dataSourceUrl('json'))->json()[0]['ScoreBug'];

        \Livewire\Livewire::test(\App\Livewire\Shows\Board::class, ['show' => $this->show])
            ->call('clearSection', 'ScoreBug');

        $after = $this->getJson($this->show->fresh()->dataSourceUrl('json'))->json()[0];

        $this->assertStringContainsString('/assets/empty.png', $after['ScoreBug']);
        $this->assertNotSame($before, $after['ScoreBug']);
        $this->assertStringContainsString('#', $after['UpdatedAt']);
    }

    public function test_the_empty_image_is_not_cacheable(): void
    {
        $response = $this->get(route('assets.empty'));

        $response->assertOk();
        $this->assertSame('image/png', $response->headers->get('Content-Type'));
        $this->assertStringContainsString('no-store', (string) $response->headers->get('Cache-Control'));
    }

    public function test_rundown_feed_carries_one_row_per_cue(): void
    {
        $rows = $this->getJson($this->show->dataSourceUrl('json', 'rundown'))->assertOk()->json();

        $this->assertCount($this->show->looks()->count(), $rows);
        $this->assertSame('', $rows[0]['Rundown.now_racing']);
        $this->assertSame("We'll Be Right Back", $rows[0]['Break.brb_message']);
    }

    public function test_the_xml_feed_is_well_formed(): void
    {
        $body = $this->get($this->show->dataSourceUrl('xml'))->assertOk()->getContent();

        $document = simplexml_load_string($body);

        $this->assertNotFalse($document);
        $this->assertSame("We'll Be Right Back", (string) $document->row->{'Break.brb_message'});
    }

    public function test_a_wrong_token_is_indistinguishable_from_a_missing_show(): void
    {
        $this->getJson(route('datasource.live.json', [
            'uuid' => $this->show->uuid,
            'token' => 'not-the-token',
        ]))->assertNotFound();
    }
}
