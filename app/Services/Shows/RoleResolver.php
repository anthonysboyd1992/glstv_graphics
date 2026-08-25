<?php

namespace App\Services\Shows;

use App\Models\Asset;
use App\Models\Show;
use Illuminate\Support\Collection;

/**
 * Turns a role key such as "sponsor_a" into a concrete asset by consulting the
 * show's asset packs in priority order. This indirection is what lets the same
 * show configuration run a different night by swapping packs.
 */
class RoleResolver
{
    /** @var array<int, Collection<string, Asset>> */
    protected array $cache = [];

    public function resolve(Show $show, string $roleKey): ?Asset
    {
        return $this->map($show)->get($roleKey);
    }

    /**
     * @return Collection<string, Asset>
     */
    public function map(Show $show): Collection
    {
        if (isset($this->cache[$show->id])) {
            return $this->cache[$show->id];
        }

        $resolved = collect();

        $packs = $show->relationLoaded('assetPacks')
            ? $show->assetPacks
            : $show->assetPacks()->with('items.asset')->get();

        foreach ($packs as $pack) {
            foreach ($pack->items as $item) {
                // First pack to supply a role wins, so a show-specific pack can
                // be ordered ahead of the house defaults.
                if (! $resolved->has($item->role_key) && $item->asset) {
                    $resolved->put($item->role_key, $item->asset);
                }
            }
        }

        return $this->cache[$show->id] = $resolved;
    }

    public function flush(?Show $show = null): void
    {
        if ($show) {
            unset($this->cache[$show->id]);

            return;
        }

        $this->cache = [];
    }
}
