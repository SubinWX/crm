<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Follow-ups and tasks were the same idea in two places. Tasks can now belong to a lead as
 * well as a client, every existing follow-up is copied across as a task, and the separate
 * follow_ups table is removed.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            $table->unsignedBigInteger('client_id')->nullable()->change();
            $table->foreignId('lead_id')->nullable()->after('client_id')->constrained()->cascadeOnDelete();
        });

        if (Schema::hasTable('follow_ups')) {
            DB::table('follow_ups')->orderBy('id')->chunk(500, function ($rows) {
                foreach ($rows as $row) {
                    DB::table('tasks')->insert([
                        'client_id' => $row->client_id, 'lead_id' => $row->lead_id, 'title' => $row->title,
                        'assignee_id' => $row->assignee_id, 'due_date' => $row->due_date,
                        'status' => $row->completed_at ? 'done' : 'todo', 'notes' => $row->notes,
                        'created_by' => $row->created_by, 'created_at' => $row->created_at, 'updated_at' => $row->updated_at,
                    ]);
                }
            });
            Schema::drop('follow_ups');
        }
    }

    public function down(): void
    {
        Schema::table('tasks', fn (Blueprint $table) => $table->dropConstrainedForeignId('lead_id'));
    }
};
