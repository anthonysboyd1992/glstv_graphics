<?php

use App\Services\Shows\DefaultLayout;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Caption groups belong to a layout the way slots do. Existing Rundown /
     * Break / General keys were global; they become the Dirt Track catalog.
     */
    public function up(): void
    {
        Schema::table('text_groups', function (Blueprint $table) {
            $table->foreignId('layout_id')->nullable()->after('id')->constrained()->cascadeOnDelete();
        });

        $layout = DefaultLayout::ensureLayouts();

        DB::table('text_groups')->whereNull('layout_id')->update(['layout_id' => $layout->id]);

        Schema::table('text_groups', function (Blueprint $table) {
            $table->dropUnique(['key']);
            $table->unique(['layout_id', 'key']);
        });
    }

    public function down(): void
    {
        Schema::table('text_groups', function (Blueprint $table) {
            $table->dropUnique(['layout_id', 'key']);
            $table->dropConstrainedForeignId('layout_id');
            $table->unique('key');
        });
    }
};
