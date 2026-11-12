<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('job_vacancies', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->string('slug')->unique();
            $table->string('department')->nullable();
            $table->string('location')->nullable();
            $table->string('employment_type', 20)->default('full_time');
            $table->string('work_mode', 20)->default('onsite');
            $table->string('experience')->nullable();
            $table->string('salary_range')->nullable();
            $table->unsignedSmallInteger('openings')->default(1);
            $table->string('summary', 500)->nullable();
            $table->text('description');
            $table->text('requirements')->nullable();
            // draft | open | closed. Only "open" vacancies appear on the public careers page.
            $table->string('status', 20)->default('draft')->index();
            $table->date('closes_on')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('job_applications', function (Blueprint $table) {
            $table->id();
            // Null for a general application, or when the vacancy was deleted later.
            $table->foreignId('job_vacancy_id')->nullable()->constrained('job_vacancies')->nullOnDelete();
            $table->string('vacancy_title');
            $table->string('name');
            $table->string('email');
            $table->string('phone', 40);
            $table->string('location')->nullable();
            $table->decimal('experience_years', 4, 1)->nullable();
            $table->string('current_company')->nullable();
            $table->string('current_salary')->nullable();
            $table->string('expected_salary')->nullable();
            $table->string('notice_period')->nullable();
            $table->string('portfolio_url', 500)->nullable();
            $table->string('linkedin_url', 500)->nullable();
            $table->text('cover_letter')->nullable();
            $table->string('resume_path');
            $table->string('resume_name');
            $table->string('status', 20)->default('new')->index();
            $table->unsignedTinyInteger('rating')->nullable();
            $table->text('notes')->nullable();
            $table->dateTime('interview_at')->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('ip_address', 45)->nullable();
            $table->timestamps();
            $table->index('created_at');
            $table->index(['job_vacancy_id', 'email']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('job_applications');
        Schema::dropIfExists('job_vacancies');
    }
};
