<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('text_keys') || ! Schema::hasColumn('text_keys', 'show_id')) {
            return;
        }

        Schema::create('text_keys_shared', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->string('label');
            $table->text('description')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        $keyIds = [];

        foreach (DB::table('text_keys')->orderBy('sort_order')->orderBy('id')->get() as $row) {
            if (isset($keyIds[$row->key])) {
                continue;
            }

            $keyIds[$row->key] = DB::table('text_keys_shared')->insertGetId([
                'key' => $row->key,
                'label' => $row->label,
                'description' => $row->description,
                'sort_order' => $row->sort_order,
                'created_at' => $row->created_at,
                'updated_at' => $row->updated_at,
            ]);
        }

        if (! Schema::hasTable('show_text_defaults')) {
            Schema::create('show_text_defaults', function (Blueprint $table) {
                $table->id();
                $table->foreignId('show_id')->constrained()->cascadeOnDelete();
                $table->unsignedBigInteger('text_key_id');
                $table->text('default_value')->nullable();
                $table->timestamps();

                $table->unique(['show_id', 'text_key_id']);
            });
        }

        foreach (DB::table('text_keys')->get() as $row) {
            DB::table('show_text_defaults')->insert([
                'show_id' => $row->show_id,
                'text_key_id' => $keyIds[$row->key],
                'default_value' => $row->default_value,
                'created_at' => $row->created_at,
                'updated_at' => $row->updated_at,
            ]);
        }

        Schema::drop('text_keys');
        Schema::rename('text_keys_shared', 'text_keys');
    }

    public function down(): void
    {
        // Shared keys cannot be split back onto shows without guessing.
    }
};
