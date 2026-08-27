<?php

namespace Tests\Feature;

use App\Livewire\Assets\Library;
use App\Models\Asset;
use App\Models\User;
use App\Services\Assets\AssetImporter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class AssetLibraryTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAs(User::factory()->create());
        Storage::fake('local');
    }

    public function test_the_library_lists_assets_alphabetically(): void
    {
        app(AssetImporter::class)->import(UploadedFile::fake()->image('z.png', 100, 100), 'Zebra Plate');
        app(AssetImporter::class)->import(UploadedFile::fake()->image('a.png', 200, 200), 'Alpha Bug');

        $html = Livewire::test(Library::class)->html();
        $alpha = strpos($html, 'Alpha Bug');
        $zebra = strpos($html, 'Zebra Plate');

        $this->assertNotFalse($alpha);
        $this->assertNotFalse($zebra);
        $this->assertTrue($alpha < $zebra);
    }

    public function test_renaming_changes_the_label_but_not_the_address(): void
    {
        $asset = $this->storeAsset('Score Bug');
        $url = $asset->url();

        Livewire::test(Library::class)
            ->call('startRename', $asset->id)
            ->set('renameValue', 'GLSS Score Bug')
            ->call('rename');

        $asset->refresh();

        $this->assertSame('GLSS Score Bug', $asset->name);
        $this->assertSame($url, $asset->url());
    }

    public function test_a_rename_cannot_blank_the_name(): void
    {
        $asset = $this->storeAsset('Score Bug');

        Livewire::test(Library::class)
            ->call('startRename', $asset->id)
            ->set('renameValue', '')
            ->call('rename')
            ->assertHasErrors(['renameValue' => 'required']);

        $this->assertSame('Score Bug', $asset->refresh()->name);
    }

    public function test_cancelling_leaves_the_name_alone(): void
    {
        $asset = $this->storeAsset('Score Bug');

        Livewire::test(Library::class)
            ->call('startRename', $asset->id)
            ->set('renameValue', 'Something else')
            ->call('cancelRename')
            ->assertSet('renamingId', null);

        $this->assertSame('Score Bug', $asset->refresh()->name);
    }

    public function test_the_library_appends_the_next_chunk_instead_of_paging(): void
    {
        foreach (range(1, 3) as $i) {
            app(AssetImporter::class)->import(
                UploadedFile::fake()->image("g{$i}.png", 40 + $i, 40 + $i),
                sprintf('Graphic %02d', $i),
            );
        }

        Livewire::test(Library::class)
            ->set('perPage', 2)
            ->assertSee('Graphic 01')
            ->assertSee('Graphic 02')
            ->assertDontSee('Graphic 03')
            ->assertDontSee('Next')
            ->call('loadMore')
            ->assertSee('Graphic 03');
    }

    public function test_searching_starts_the_list_over(): void
    {
        foreach (range(1, 3) as $i) {
            app(AssetImporter::class)->import(
                UploadedFile::fake()->image("s{$i}.png", 60 + $i, 60 + $i),
                sprintf('Graphic %02d', $i),
            );
        }

        Livewire::test(Library::class)
            ->set('perPage', 2)
            ->call('loadMore')
            ->assertSet('perPage', 2 + Library::CHUNK)
            ->set('search', 'Graphic 03')
            ->assertSet('perPage', Library::CHUNK)
            ->assertSee('Graphic 03')
            ->assertDontSee('Graphic 01');
    }

    protected function storeAsset(string $name): Asset
    {
        return app(AssetImporter::class)->import(
            UploadedFile::fake()->image('bug.png', 1920, 180),
            $name,
        );
    }
}
