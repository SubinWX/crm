<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** Bulk messages: one email or WhatsApp message sent to a chosen group of clients, with a result per client. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('campaigns', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('channel', 20);                    // email | whatsapp
            $table->string('subject')->nullable();            // email only
            $table->text('body');
            $table->string('whatsapp_template')->nullable();  // Meta: the approved template the message is sent with
            $table->string('status', 20)->default('sending'); // sending | sent
            $table->unsignedInteger('total')->default(0);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('campaign_recipients', function (Blueprint $table) {
            $table->id();
            $table->foreignId('campaign_id')->constrained()->cascadeOnDelete();
            $table->foreignId('client_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name');
            $table->string('recipient')->nullable();          // the email address or mobile number used
            $table->string('status', 20)->default('queued');  // queued | sent | failed | skipped
            $table->text('error')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();
            $table->unique(['campaign_id', 'client_id']);
            $table->index(['campaign_id', 'status']);
        });

        // A client who asked not to receive offers and greetings. Reminders about their own invoices and renewals still go out.
        Schema::table('clients', fn (Blueprint $table) => $table->timestamp('bulk_opt_out_at')->nullable()->after('preferred_channel'));

        if (Schema::hasTable('roadmap_items')) {
            DB::table('roadmap_items')->whereIn('title', ['Bulk email and WhatsApp', 'Phone-friendly layout', 'Global search'])
                ->update(['status' => 'done', 'happened_on' => '2026-11-13', 'updated_at' => now()]);
        }
    }

    public function down(): void
    {
        Schema::table('clients', fn (Blueprint $table) => $table->dropColumn('bulk_opt_out_at'));
        Schema::dropIfExists('campaign_recipients');
        Schema::dropIfExists('campaigns');
    }
};
