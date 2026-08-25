<?php

namespace App\Livewire\Assets;

use App\Concerns\Toasts;
use App\Models\Asset;
use App\Services\Assets\AssetCache;
use App\Services\Assets\AssetImporter;
use App\Support\Access;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;
use Livewire\WithPagination;
use Throwable;

#[Title('Asset library')]
class Library extends Component
{
    use Toasts;
    use WithFileUploads, WithPagination;

    /** @var array<int, TemporaryUploadedFile> */
    public array $uploads = [];

    #[Url]
    public string $search = '';

    public ?int $renamingId = null;

    public string $renameValue = '';

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    #[Computed]
    public function assets(): LengthAwarePaginator
    {
        return Asset::query()
            ->originals()
            ->when($this->search !== '', fn ($query) => $query->where(function ($query) {
                $query->where('name', 'like', '%'.$this->search.'%')
                    ->orWhere('original_filename', 'like', '%'.$this->search.'%');
            }))
            ->orderByDesc('id')
            ->paginate(24);
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
        unset($this->assets);

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
        unset($this->assets);

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

        unset($this->assets);

        $this->toast(__('Asset deleted.'));
    }
}
