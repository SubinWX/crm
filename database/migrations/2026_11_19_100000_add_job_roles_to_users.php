<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * A person's job role (Manager, Team Lead, Developer…): what they do, separate from their access level
 * (Super Admin / Admin / Staff). The list is a dropdown list like the others in Settings.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', fn (Blueprint $table) => $table->foreignId('job_role_id')->nullable()->after('role')->constrained('lookups')->nullOnDelete());

        if (! DB::table('lookups')->where('type', 'job_role')->exists()) {
            $now = now();
            foreach (['Manager', 'Team Lead', 'Developer', 'Designer', 'Tester', 'Marketing', 'Content Writer', 'Sales', 'Accounts', 'HR'] as $position => $name) {
                DB::table('lookups')->insert(['type' => 'job_role', 'name' => $name, 'sort_order' => $position + 1, 'is_active' => true, 'created_at' => $now, 'updated_at' => $now]);
            }
        }
    }

    public function down(): void
    {
        Schema::table('users', fn (Blueprint $table) => $table->dropConstrainedForeignId('job_role_id'));
        DB::table('lookups')->where('type', 'job_role')->delete();
    }
};
