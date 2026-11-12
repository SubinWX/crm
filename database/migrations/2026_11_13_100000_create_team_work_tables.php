<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Fuller projects (description, team, estimate), time tracking, leave requests, attendance and holidays. */
    public function up(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->text('description')->nullable()->after('name');
            $table->date('start_date')->nullable()->after('status');
            // Hours quoted or planned for the work; time logged is compared against it.
            $table->decimal('estimated_hours', 8, 2)->nullable()->after('deadline');
        });

        Schema::create('project_user', function (Blueprint $table) {
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->primary(['project_id', 'user_id']);
        });

        Schema::create('time_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->date('work_date')->index();
            $table->unsignedInteger('minutes');
            $table->string('description', 500)->nullable();
            $table->boolean('is_billable')->default(true);
            $table->timestamps();
            $table->index(['user_id', 'work_date']);
        });

        Schema::create('leave_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('leave_type_id')->nullable()->constrained('lookups')->nullOnDelete();
            $table->date('from_date');
            $table->date('to_date');
            // full | first_half | second_half (half days apply to a single date only)
            $table->string('day_part', 20)->default('full');
            // Working days only: weekly offs and holidays inside the range are not counted.
            $table->decimal('days', 5, 1);
            $table->text('reason')->nullable();
            // pending | approved | rejected | cancelled
            $table->string('status', 20)->default('pending')->index();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->string('review_note', 500)->nullable();
            $table->timestamps();
            $table->index(['user_id', 'from_date']);
        });

        Schema::create('attendance_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->date('date');
            // present | half_day | wfh | absent
            $table->string('status', 20)->default('present');
            $table->time('check_in')->nullable();
            $table->time('check_out')->nullable();
            $table->string('note')->nullable();
            $table->foreignId('marked_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['user_id', 'date']);
        });

        Schema::create('holidays', function (Blueprint $table) {
            $table->id();
            $table->date('date')->unique();
            $table->string('name', 120);
            $table->timestamps();
        });

        foreach (['Casual Leave', 'Sick Leave', 'Earned Leave', 'Unpaid Leave'] as $index => $name) {
            if (! DB::table('lookups')->where('type', 'leave_type')->where('name', $name)->exists()) {
                DB::table('lookups')->insert([
                    'type' => 'leave_type', 'name' => $name, 'sort_order' => $index + 1,
                    'is_active' => true, 'is_paid' => $name !== 'Unpaid Leave', 'created_at' => now(), 'updated_at' => now(),
                ]);
            }
        }
    }

    public function down(): void
    {
        foreach (['holidays', 'attendance_records', 'leave_requests', 'time_entries', 'project_user'] as $table) {
            Schema::dropIfExists($table);
        }
        Schema::table('projects', fn (Blueprint $table) => $table->dropColumn(['description', 'start_date', 'estimated_hours']));
        DB::table('lookups')->where('type', 'leave_type')->delete();
    }
};
