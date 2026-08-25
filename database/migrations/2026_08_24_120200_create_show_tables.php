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
            $table->foreignId('show_template_id')->constrained()->cascadeOnDelete();
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

        Schema::create('asset_pack_show', function (Blueprint $table) {
            $table->id();
            $table->foreignId('show_id')->constrained()->cascadeOnDelete();
            $table->foreignId('asset_pack_id')->constrained()->cascadeOnDelete();
            // Lower sort_order wins when two packs fill the same role.
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->unique(['show_id', 'asset_pack_id']);
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

        // One row per target the look actually changes. A target with no row is
        // left untouched when the look is applied.
        Schema::create('look_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('look_id')->constrained()->cascadeOnDelete();
            $table->string('target_type');
            $table->string('target_key');
            $table->string('action')->default('set');
            $table->foreignId('asset_id')->nullable()->constrained()->nullOnDelete();
            $table->string('role_key')->nullable();
            $table->text('text_value')->nullable();
            $table->timestamps();

            $table->unique(['look_id', 'target_type', 'target_key']);
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
        Schema::dropIfExists('asset_pack_show');
        Schema::dropIfExists('shows');
    }
};
