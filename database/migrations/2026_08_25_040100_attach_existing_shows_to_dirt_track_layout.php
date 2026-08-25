<?php

use App\Models\Layout;
use App\Models\Show;
use App\Services\Shows\DefaultLayout;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Existing boxes already have slots copied on them. Point those at the
     * starter layout so the broadcast card can name the type.
     */
    public function up(): void
    {
        if (! Show::query()->exists()) {
            return;
        }

        $layout = DefaultLayout::ensureLayouts();

        Show::query()->whereNull('layout_id')->update(['layout_id' => $layout->id]);
    }

    public function down(): void
    {
        Show::query()->update(['layout_id' => null]);
        Layout::query()->where('slug', 'dirt-track')->delete();
    }
};
