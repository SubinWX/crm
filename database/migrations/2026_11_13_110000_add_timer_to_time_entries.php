<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Start/stop timer: an entry with a start time and no end time is a timer that is still running.
     * Entries typed in by hand keep both empty.
     */
    public function up(): void
    {
        Schema::table('time_entries', function (Blueprint $table) {
            $table->dateTime('started_at')->nullable()->after('minutes');
            $table->dateTime('ended_at')->nullable()->after('started_at');
            $table->index(['user_id', 'ended_at']);
        });
    }

    public function down(): void
    {
        Schema::table('time_entries', function (Blueprint $table) {
            $table->dropIndex(['user_id', 'ended_at']);
            $table->dropColumn(['started_at', 'ended_at']);
        });
    }
};
