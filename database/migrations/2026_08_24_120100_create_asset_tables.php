<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('assets', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('path');
            $table->string('original_filename')->nullable();
            // Serving URLs are keyed on the digest so an unchanged image keeps
            // a stable URL and vMix can cache it indefinitely.
            $table->string('sha256', 64)->unique();
            $table->string('extension', 12);
            $table->string('mime');
            $table->unsignedInteger('width')->nullable();
            $table->unsignedInteger('height')->nullable();
            $table->unsignedBigInteger('bytes')->default(0);
            $table->json('tags')->nullable();
            $table->timestamps();
        });

    }

    public function down(): void
    {
        Schema::dropIfExists('assets');
    }
};
