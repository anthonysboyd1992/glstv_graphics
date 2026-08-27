<?php

namespace App\Livewire\Assets;

use App\Concerns\Toasts;
use App\Models\Asset;
use App\Services\Assets\AssetCache;
use App\Services\Assets\AssetImporter;
use App\Support\Access;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;
use Throwable;

#[Title('Asset library')]
class Library extends Component
{
    use Toasts;
    use WithFileUploads;

    public const CHUNK = 24;

    /** @var array<int, TemporaryUploadedFile> */
    public array $uploads = [];

    #[Url]
    public string $search = '';

    public int $perPage = self::CHUNK;

    public ?int $renamingId = null;

    public string $renameValue = '';

    public function updatedSearch(): void
    {
        $this->perPage = self::CHUNK;
        $this->refreshAssets();
    }

    public function loadMore(): void
    {
        if (! $this->hasMoreAssets) {
            return;
        }

        $this->perPage += self::CHUNK;
        $this->refreshAssets();
    }

    /**
     * Fetches one extra row so the sentinel knows whether to keep listening.
     *
     * @return Collection<int, Asset>
     */
    #[Computed]
    public function window(): Collection
    {
        return $this->assetQuery()->limit($this->perPage + 1)->get();
    }

    /** @return Collection<int, Asset> */
    #[Computed]
    public function assets(): Collection
    {
        return $this->window->take($this->perPage);
    }

    #[Computed]
    public function hasMoreAssets(): bool
    {
        return $this->window->count() > $this->perPage;
    }

    public function save(AssetImporter $importer): void
    {
        $this->authorize(Access::ASSETS_MANAGE);
        $this->validate([
            'uploads' => 'required|array|min:1',
            'uploads.*' => 'image|max:'.config('broadcast.max_upload_kb'),
        ]);

        $imported = 0;
        $failed = 0;

        foreach ($this->uploads as $upload) {
            try {
                $importer->import($upload);
                $imported++;
            } catch (Throwable $e) {
                report($e);
                $failed++;
            }
        }

        $this->reset('uploads');
        $this->refreshAssets();

        // Re-uploading a file that is already stored is expected and harmless:
        // the digest matches and the existing record is reused.
        $this->toast(
            trans_choice(':count asset stored|:count assets stored', $imported, ['count' => $imported])
                .($failed ? __(', :count failed', ['count' => $failed]) : ''),
            $failed ? 'warning' : 'success',
        );
    }

    public function startRename(Asset $asset): void
    {
        $this->authorize(Access::ASSETS_MANAGE);
        $this->renamingId = $asset->id;
        $this->renameValue = $asset->name;
    }

    /**
     * Only the label moves. URLs are content-addressed, so a rename cannot
     * invalidate anything vMix has already cached or any cue pointing here.
     */
    public function rename(): void
    {
        $this->authorize(Access::ASSETS_MANAGE);
        if (! $this->renamingId) {
            return;
        }

        $this->validate(['renameValue' => 'required|string|max:160']);

        Asset::whereKey($this->renamingId)->update(['name' => trim($this->renameValue)]);

        $this->cancelRename();
        $this->refreshAssets();

        $this->toast(__('Renamed.'));
    }

    public function cancelRename(): void
    {
        $this->reset('renamingId', 'renameValue');
    }

    public function delete(Asset $asset, AssetCache $cache): void
    {
        $this->authorize(Access::ASSETS_MANAGE);

        $asset->renditions->each(function (Asset $rendition) use ($cache): void {
            $cache->forget($rendition);
            $rendition->delete();
        });
        $cache->forget($asset);
        $asset->delete();

        $this->refreshAssets();

        $this->toast(__('Asset deleted.'));
    }

    /** @return Builder<Asset> */
    protected function assetQuery(): Builder
    {
        return Asset::query()
            ->originals()
            ->when($this->search !== '', fn ($query) => $query->where(function ($query) {
                $query->where('name', 'like', '%'.$this->search.'%')
                    ->orWhere('original_filename', 'like', '%'.$this->search.'%');
            }))
            ->orderBy('name')
            ->orderBy('id');
    }

    protected function refreshAssets(): void
    {
        unset($this->window, $this->assets, $this->hasMoreAssets);
    }
}
