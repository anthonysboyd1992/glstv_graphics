<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('show_templates', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->timestamps();
        });

        // A section is one image slot in vMix. Its key becomes a field name in
        // the data source, so it must match what the vMix title expects.
        Schema::create('sections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('show_template_id')->constrained()->cascadeOnDelete();
            $table->string('key');
            $table->string('label');
            $table->text('description')->nullable();
            $table->unsignedInteger('width')->nullable();
            $table->unsignedInteger('height')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->unique(['show_template_id', 'key']);
        });

        // A text key is a free-standing string field in the data source,
        // independent of any image section.
        Schema::create('text_keys', function (Blueprint $table) {
            $table->id();
            $table->foreignId('show_template_id')->constrained()->cascadeOnDelete();
            $table->string('key');
            $table->string('label');
            $table->text('description')->nullable();
            $table->text('default_value')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->unique(['show_template_id', 'key']);
        });

        // A role is a semantic slot ("home_logo") that asset packs fill in.
        // Looks can target a role instead of a specific asset, which is what
        // lets one show config run with different teams or sponsors.
        Schema::create('asset_roles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('show_template_id')->constrained()->cascadeOnDelete();
            $table->string('key');
            $table->string('label');
            $table->foreignId('section_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->unique(['show_template_id', 'key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('asset_roles');
        Schema::dropIfExists('text_keys');
        Schema::dropIfExists('sections');
        Schema::dropIfExists('show_templates');
    }
};
