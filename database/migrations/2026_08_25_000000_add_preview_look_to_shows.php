<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Selecting a cue and putting it to air are separate acts, the same way a
     * vision mixer keeps preview and program apart. Without this an operator
     * browsing the stack during a green flag run would be changing the picture.
     */
    public function up(): void
    {
        Schema::table('shows', function (Blueprint $table) {
            $table->foreignId('preview_look_id')->nullable()->after('active_look_id');
        });
    }

    public function down(): void
    {
        Schema::table('shows', function (Blueprint $table) {
            $table->dropColumn('preview_look_id');
        });
    }
};
