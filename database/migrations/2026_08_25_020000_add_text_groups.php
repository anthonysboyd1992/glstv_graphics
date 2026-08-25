<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('text_groups')) {
            return;
        }

        Schema::create('text_groups', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->string('label');
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        $now = now();

        $groupIds = [];

        foreach ([
            ['key' => 'Rundown', 'label' => 'Rundown', 'sort_order' => 0],
            ['key' => 'Break', 'label' => 'Break', 'sort_order' => 1],
            ['key' => 'General', 'label' => 'General', 'sort_order' => 2],
        ] as $group) {
            $groupIds[$group['key']] = DB::table('text_groups')->insertGetId($group + [
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        $keyGroup = [
            'now_racing' => 'Rundown',
            'next_event' => 'Rundown',
            'brb_message' => 'Break',
            'announcement' => 'General',
            'track_name' => 'General',
        ];

        Schema::table('text_keys', function (Blueprint $table) {
            $table->unsignedBigInteger('group_id')->nullable()->after('id');
        });

        foreach (DB::table('text_keys')->get() as $row) {
            $groupKey = $keyGroup[$row->key] ?? 'General';

            DB::table('text_keys')->where('id', $row->id)->update([
                'group_id' => $groupIds[$groupKey] ?? $groupIds['General'],
            ]);
        }

        Schema::table('text_keys', function (Blueprint $table) {
            $table->foreign('group_id')->references('id')->on('text_groups')->cascadeOnDelete();
        });

        Schema::table('text_keys', function (Blueprint $table) {
            $table->dropUnique(['key']);
            $table->unique(['group_id', 'key']);
        });
    }

    public function down(): void
    {
        Schema::table('text_keys', function (Blueprint $table) {
            $table->dropForeign(['group_id']);
            $table->dropColumn('group_id');
        });

        Schema::dropIfExists('text_groups');
    }
};
