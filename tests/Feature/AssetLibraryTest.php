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

    protected function storeAsset(string $name): Asset
    {
        return app(AssetImporter::class)->import(
            UploadedFile::fake()->image('bug.png', 1920, 180),
            $name,
        );
    }
}
