<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Messages sent to clients outside email (WhatsApp reminders), one row per message. */
    public function up(): void
    {
        Schema::create('message_logs', function (Blueprint $table) {
            $table->id();
            $table->string('channel', 20)->default('whatsapp');
            // hosting_due | renewal_due | invoice_due | manual | test
            $table->string('event', 40);
            $table->string('subject_type', 40)->nullable();
            $table->unsignedBigInteger('subject_id')->nullable();
            // The reminder date this message belongs to; with the columns above it stops the same reminder going twice.
            $table->date('scheduled_date')->nullable();
            $table->foreignId('client_id')->nullable()->constrained()->nullOnDelete();
            $table->string('recipient', 30);
            $table->text('body');
            $table->json('parameters')->nullable();
            // queued | sent | failed | skipped
            $table->string('status', 20)->default('queued')->index();
            $table->string('provider_message_id')->nullable();
            $table->text('error')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['channel', 'event', 'subject_type', 'subject_id', 'scheduled_date'], 'message_logs_once');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('message_logs');
    }
};
