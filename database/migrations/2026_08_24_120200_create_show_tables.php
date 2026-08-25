<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shows', function (Blueprint $table) {
            $table->id();
            // Stable identifier for this broadcast. Data source URLs key on this
            // rather than the slug, so renaming a show never invalidates a vMix
            // configuration that was built ahead of time.
            $table->uuid('uuid')->unique();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('token', 64)->unique();
            $table->timestamp('scheduled_for')->nullable();
            $table->string('status')->default('draft');
            // Resolved live state, kept denormalised so the data source endpoint
            // is a single row read while vMix polls it.
            $table->json('current_state')->nullable();
            $table->unsignedBigInteger('active_look_id')->nullable();
            $table->timestamps();
        });

        // A section is one image slot in vMix. Its key becomes a field name in
        // the data source, so it must match what the vMix title expects.
        Schema::create('sections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('show_id')->constrained()->cascadeOnDelete();
            $table->string('key');
            $table->string('label');
            $table->text('description')->nullable();
            $table->unsignedInteger('width')->nullable();
            $table->unsignedInteger('height')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->unique(['show_id', 'key']);
        });

        // Shared caption groups. vMix sees each field as Group.key, e.g. Rundown.now_racing.
        Schema::create('text_groups', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->string('label');
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        // Text keys are shared across every vMix box. The key is what a title
        // binds to; live values and defaults live per broadcast.
        Schema::create('text_keys', function (Blueprint $table) {
            $table->id();
            $table->foreignId('group_id')->constrained('text_groups')->cascadeOnDelete();
            $table->string('key');
            $table->string('label');
            $table->text('description')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->unique(['group_id', 'key']);
        });

        Schema::create('show_text_defaults', function (Blueprint $table) {
            $table->id();
            $table->foreignId('show_id')->constrained()->cascadeOnDelete();
            $table->foreignId('text_key_id')->constrained()->cascadeOnDelete();
            $table->text('default_value')->nullable();
            $table->timestamps();

            $table->unique(['show_id', 'text_key_id']);
        });

        Schema::create('looks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('show_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('kind')->nullable();
            $table->text('notes')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        // One row per section the cue actually changes. A section with no row is
        // left untouched when the cue is taken.
        Schema::create('look_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('look_id')->constrained()->cascadeOnDelete();
            $table->string('section_key');
            $table->string('action')->default('set');
            $table->foreignId('asset_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();

            $table->unique(['look_id', 'section_key']);
        });

        Schema::table('shows', function (Blueprint $table) {
            $table->foreign('active_look_id')->references('id')->on('looks')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('shows', function (Blueprint $table) {
            $table->dropForeign(['active_look_id']);
        });

        Schema::dropIfExists('look_items');
        Schema::dropIfExists('looks');
        Schema::dropIfExists('show_text_defaults');
        Schema::dropIfExists('text_keys');
        Schema::dropIfExists('text_groups');
        Schema::dropIfExists('sections');
        Schema::dropIfExists('shows');
    }
};
