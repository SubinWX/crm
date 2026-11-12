<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Departments for job vacancies become a dropdown list, editable in Settings → Dropdown values.
     * Departments already typed on vacancies are added to the list so nothing is lost.
     */
    public function up(): void
    {
        $existing = DB::table('job_vacancies')->whereNotNull('department')->distinct()->pluck('department')->map(fn ($d) => trim($d))->filter();
        $names = collect(['Development', 'Design', 'Digital Marketing', 'SEO', 'Content', 'Sales', 'Accounts', 'HR & Admin'])
            ->merge($existing)->unique(fn ($n) => mb_strtolower($n))->values();

        foreach ($names as $index => $name) {
            if (! DB::table('lookups')->where('type', 'job_department')->where('name', $name)->exists()) {
                DB::table('lookups')->insert([
                    'type' => 'job_department', 'name' => $name, 'sort_order' => $index + 1,
                    'is_active' => true, 'is_paid' => false, 'created_at' => now(), 'updated_at' => now(),
                ]);
            }
        }
    }

    public function down(): void
    {
        DB::table('lookups')->where('type', 'job_department')->delete();
    }
};
