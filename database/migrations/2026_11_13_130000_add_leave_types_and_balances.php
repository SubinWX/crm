<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Leave types get a short code and a yearly allowance (CL, EL, RH, ML, LOP), each person gets a
 * joining date and optional custom balances, and leave can be taken in quarter days.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('lookups', function (Blueprint $table) {
            $table->string('code', 10)->nullable()->after('name');
            // Days each person gets in a year. Null = no limit (loss of pay).
            $table->decimal('yearly_days', 5, 2)->nullable()->after('is_paid');
            // yearly: all on 1 January · half_yearly: half on 1 January, half on 1 July · manual: only what a manager adds
            $table->string('accrual', 20)->default('yearly')->after('yearly_days');
            // A higher allowance once someone has completed this many years with the company.
            $table->decimal('senior_days', 5, 2)->nullable()->after('accrual');
            $table->unsignedTinyInteger('senior_after_years')->nullable()->after('senior_days');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->date('joined_on')->nullable()->after('date_of_birth');
        });

        Schema::create('leave_allocations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('leave_type_id')->constrained('lookups')->cascadeOnDelete();
            $table->unsignedSmallInteger('year');
            // Custom yearly allowance for this person. Null = follow the leave type's automatic rule.
            $table->decimal('days', 5, 2)->nullable();
            // Days added (or taken away, if negative) on top: earned leave, carry forward, corrections.
            $table->decimal('extra', 5, 2)->default(0);
            $table->string('note')->nullable();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['user_id', 'leave_type_id', 'year']);
        });

        Schema::table('leave_requests', function (Blueprint $table) {
            $table->decimal('days', 6, 2)->change(); // quarter days
        });

        $now = now();
        $rename = ['Sick Leave' => 'Medical Leave', 'Unpaid Leave' => 'Loss of Pay'];
        foreach ($rename as $old => $new) {
            if (! DB::table('lookups')->where('type', 'leave_type')->where('name', $new)->exists()) {
                DB::table('lookups')->where('type', 'leave_type')->where('name', $old)->update(['name' => $new]);
            }
        }
        // name => [code, paid, yearly days, accrual, senior days, after years, order]
        $types = [
            'Casual Leave' => ['CL', true, 12, 'yearly', 18, 2, 1],
            'Earned Leave' => ['EL', true, 0, 'manual', null, null, 2],
            'Restricted Holiday' => ['RH', true, 2, 'yearly', null, null, 3],
            'Medical Leave' => ['ML', true, 2, 'half_yearly', null, null, 4],
            'Loss of Pay' => ['LOP', false, null, 'yearly', null, null, 5],
        ];
        foreach ($types as $name => [$code, $paid, $days, $accrual, $senior, $after, $order]) {
            $values = ['code' => $code, 'is_paid' => $paid, 'yearly_days' => $days, 'accrual' => $accrual, 'senior_days' => $senior, 'senior_after_years' => $after, 'sort_order' => $order, 'updated_at' => $now];
            DB::table('lookups')->where('type', 'leave_type')->where('name', $name)->exists()
                ? DB::table('lookups')->where('type', 'leave_type')->where('name', $name)->update($values)
                : DB::table('lookups')->insert($values + ['type' => 'leave_type', 'name' => $name, 'is_active' => true, 'created_at' => $now]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('leave_allocations');
        Schema::table('users', fn (Blueprint $table) => $table->dropColumn('joined_on'));
        Schema::table('lookups', fn (Blueprint $table) => $table->dropColumn(['code', 'yearly_days', 'accrual', 'senior_days', 'senior_after_years']));
    }
};
