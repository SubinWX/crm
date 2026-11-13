<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The profile page: personal and emergency-contact details on each person, the company's
 * rules and policies (one list, read by everyone), and meeting notes kept about a person.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('gender', 20)->nullable()->after('date_of_birth');
            $table->string('personal_email')->nullable()->after('gender');
            $table->string('address', 500)->nullable()->after('personal_email');
            $table->string('city', 100)->nullable()->after('address');
            $table->string('state', 100)->nullable()->after('city');
            $table->string('pincode', 12)->nullable()->after('state');
            $table->string('nationality', 100)->nullable()->after('pincode');
            $table->string('blood_group', 10)->nullable()->after('nationality');
            $table->string('emergency_name')->nullable()->after('blood_group');
            $table->string('emergency_relation', 100)->nullable()->after('emergency_name');
            $table->string('emergency_phone', 30)->nullable()->after('emergency_relation');
            $table->string('emergency_alt_phone', 30)->nullable()->after('emergency_phone');
        });

        Schema::create('policies', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->text('body')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('meeting_notes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();   // the person the meeting was with
            $table->string('title');
            $table->date('held_on');
            $table->text('notes')->nullable();
            // Off = kept for managers only; the person does not see it on their profile.
            $table->boolean('shared')->default(true);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['user_id', 'held_on']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('meeting_notes');
        Schema::dropIfExists('policies');
        Schema::table('users', fn (Blueprint $table) => $table->dropColumn([
            'gender', 'personal_email', 'address', 'city', 'state', 'pincode', 'nationality', 'blood_group',
            'emergency_name', 'emergency_relation', 'emergency_phone', 'emergency_alt_phone',
        ]));
    }
};
