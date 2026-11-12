<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Lets an expense that is not for a client be linked to something else
     * (Office, Self, Other…). The choices are editable in Settings → Dropdown values.
     */
    public function up(): void
    {
        Schema::table('expenses', function (Blueprint $table) {
            $table->foreignId('link_id')->nullable()->after('client_id')->constrained('lookups')->nullOnDelete();
        });

        foreach (['Office', 'Self', 'Other'] as $index => $name) {
            if (! DB::table('lookups')->where('type', 'expense_link')->where('name', $name)->exists()) {
                DB::table('lookups')->insert([
                    'type' => 'expense_link', 'name' => $name, 'sort_order' => $index + 1,
                    'is_active' => true, 'is_paid' => false, 'created_at' => now(), 'updated_at' => now(),
                ]);
            }
        }
    }

    public function down(): void
    {
        Schema::table('expenses', function (Blueprint $table) {
            $table->dropConstrainedForeignId('link_id');
        });

        DB::table('lookups')->where('type', 'expense_link')->delete();
    }
};
