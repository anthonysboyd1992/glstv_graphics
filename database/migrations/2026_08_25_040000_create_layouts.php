<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A layout is a named set of image slots. New broadcasts copy one; live
     * boxes keep their own sections so editing a layout never changes air.
     */
    public function up(): void
    {
        Schema::create('layouts', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('layout_sections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('layout_id')->constrained()->cascadeOnDelete();
            $table->string('key');
            $table->string('label');
            $table->text('description')->nullable();
            $table->unsignedInteger('width')->nullable();
            $table->unsignedInteger('height')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->unique(['layout_id', 'key']);
        });

        Schema::table('shows', function (Blueprint $table) {
            $table->foreignId('layout_id')->nullable()->constrained()->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('shows', function (Blueprint $table) {
            $table->dropConstrainedForeignId('layout_id');
        });

        Schema::dropIfExists('layout_sections');
        Schema::dropIfExists('layouts');
    }
};
