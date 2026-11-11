<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // People who sign up on the public support portal. Not team members.
        Schema::create('portal_users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->string('password')->nullable();          // null for Google-only accounts
            $table->string('google_id')->nullable()->unique();
            $table->string('phone', 30)->nullable();
            $table->string('company')->nullable();
            // Optional link to a CRM client, set by the team.
            $table->foreignId('client_id')->nullable()->constrained()->nullOnDelete();
            $table->boolean('is_active')->default(true);
            // Team member who created the account (null when the person signed up themselves).
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('email_verified_at')->nullable();
            $table->timestamp('last_login_at')->nullable();
            $table->rememberToken();
            $table->timestamps();
        });

        Schema::create('portal_password_reset_tokens', function (Blueprint $table) {
            $table->string('email')->primary();
            $table->string('token');
            $table->timestamp('created_at')->nullable();
        });

        Schema::create('support_tickets', function (Blueprint $table) {
            $table->id();
            $table->string('number', 30)->unique();
            $table->foreignId('portal_user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('client_id')->nullable()->constrained()->nullOnDelete();
            $table->string('type', 20)->default('support')->index();   // support | new_work | feedback
            $table->string('subject');
            $table->string('related_work')->nullable();                 // which project / service it is about
            $table->string('priority', 20)->default('normal');
            $table->string('status', 20)->default('open')->index();
            $table->unsignedTinyInteger('rating')->nullable();          // 1–5, feedback only
            $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('last_activity_at')->nullable()->index();
            $table->timestamp('closed_at')->nullable();
            $table->timestamps();
        });

        Schema::create('support_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('support_ticket_id')->constrained()->cascadeOnDelete();
            $table->foreignId('portal_user_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->text('body');
            $table->boolean('is_internal')->default(false);             // team-only note, never shown on the portal
            $table->timestamps();
        });

        Schema::create('support_attachments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('support_message_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('path');
            $table->string('mime', 100)->nullable();
            $table->unsignedBigInteger('size')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        foreach (['support_attachments', 'support_messages', 'support_tickets', 'portal_password_reset_tokens', 'portal_users'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
