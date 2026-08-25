<?php

namespace App\Livewire\Assets;

use App\Models\Asset;
use App\Services\Assets\AssetCache;
use App\Services\Assets\AssetImporter;
use Flux\Flux;
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
    use WithFileUploads, WithPagination;

    /** @var array<int, TemporaryUploadedFile> */
    public array $uploads = [];

    #[Url]
    public string $search = '';

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    #[Computed]
    public function assets(): LengthAwarePaginator
    {
        return Asset::query()
            ->when($this->search !== '', fn ($query) => $query
                ->where('name', 'like', '%'.$this->search.'%')
                ->orWhere('original_filename', 'like', '%'.$this->search.'%'))
            ->orderByDesc('id')
            ->paginate(24);
    }

    public function save(AssetImporter $importer): void
    {
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
        Flux::toast(
            variant: $failed ? 'warning' : 'success',
            text: trans_choice(':count asset stored|:count assets stored', $imported, ['count' => $imported])
                .($failed ? __(', :count failed', ['count' => $failed]) : ''),
        );
    }

    public function delete(Asset $asset, AssetCache $cache): void
    {
        $cache->forget($asset);
        $asset->delete();

        unset($this->assets);

        Flux::toast(text: __('Asset deleted.'));
    }
}
