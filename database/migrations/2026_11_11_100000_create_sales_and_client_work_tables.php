<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Leads, follow-ups, quotes & proposals, client contacts, projects and renewals.
 * Only adds tables and nullable columns, so existing clients, hosting, invoices and payments are untouched.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('client_contacts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('role', 20)->default('other');   // primary | billing | approvals | daily | other
            $table->string('email')->nullable();
            $table->string('phone', 30)->nullable();
            $table->boolean('is_primary')->default(false);
            $table->string('notes', 500)->nullable();
            $table->timestamps();
        });

        Schema::create('leads', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('contact_name')->nullable();
            $table->string('email')->nullable()->index();
            $table->string('phone', 30)->nullable();
            $table->string('website')->nullable();
            $table->string('location')->nullable();
            $table->foreignId('acquisition_source_id')->nullable()->constrained('lookups')->nullOnDelete();
            $table->decimal('estimated_value', 12, 2)->nullable();
            $table->foreignId('owner_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('stage', 20)->default('new')->index();   // new | contacted | proposal_sent | won | lost
            $table->string('next_action')->nullable();
            $table->date('next_action_date')->nullable()->index();
            $table->text('notes')->nullable();
            $table->string('lost_reason')->nullable();
            // Set when the lead is converted; the lead row is kept so its history stays reachable.
            $table->foreignId('client_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamp('converted_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('lead_work_type', function (Blueprint $table) {
            $table->foreignId('lead_id')->constrained()->cascadeOnDelete();
            $table->foreignId('lookup_id')->constrained('lookups')->cascadeOnDelete();
            $table->primary(['lead_id', 'lookup_id']);
        });

        // Manually logged calls, emails, WhatsApp chats, meetings and notes.
        Schema::create('interactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lead_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('client_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('type', 20);
            $table->text('summary');
            $table->timestamp('happened_at')->index();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('follow_ups', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lead_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('client_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->date('due_date')->index();
            $table->foreignId('assignee_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('completed_at')->nullable();
            $table->string('notes', 1000)->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('quote_templates', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('category')->nullable();
            $table->string('description', 500)->nullable();
            $table->unsignedSmallInteger('validity_days')->default(30);
            $table->timestamp('archived_at')->nullable();
            $table->timestamps();
        });

        Schema::create('quote_template_sections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('quote_template_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->text('body')->nullable();
            $table->boolean('is_hidden')->default(false);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('quote_template_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('quote_template_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('description', 1000)->nullable();
            $table->decimal('quantity', 10, 2)->default(1);
            $table->decimal('unit_price', 12, 2)->default(0);
            $table->string('billing_frequency', 20)->default('one_time');
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('quotes', function (Blueprint $table) {
            $table->id();
            $table->string('number', 40)->unique();
            $table->foreignId('client_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('lead_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('contact_id')->nullable()->constrained('client_contacts')->nullOnDelete();
            // Recipient details are copied onto the quote so later contact edits do not change it.
            $table->string('recipient_name')->nullable();
            $table->string('recipient_email')->nullable();
            $table->string('recipient_company')->nullable();
            $table->text('recipient_address')->nullable();
            $table->string('title');
            $table->string('currency', 3)->default('INR');
            $table->date('issue_date');
            $table->date('expiry_date')->nullable();
            $table->date('follow_up_date')->nullable()->index();
            $table->string('status', 20)->default('draft')->index();   // draft | sent | accepted | declined (expired is derived)
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('responded_at')->nullable();
            $table->decimal('discount_percent', 5, 2)->default(0);
            $table->decimal('tax_rate', 5, 2)->default(0);
            $table->unsignedInteger('revision')->default(1);
            $table->string('template_names', 500)->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('quote_sections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('quote_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->text('body')->nullable();
            $table->boolean('is_hidden')->default(false);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('quote_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('quote_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('description', 1000)->nullable();
            $table->decimal('quantity', 10, 2)->default(1);
            $table->decimal('unit_price', 12, 2)->default(0);
            $table->string('billing_frequency', 20)->default('one_time');
            $table->decimal('amount', 12, 2)->default(0);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        // One row per issued version. The stored PDF and snapshot never change afterwards.
        Schema::create('quote_revisions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('quote_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('revision');
            $table->string('pdf_path');
            $table->json('snapshot');
            $table->timestamp('issued_at');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['quote_id', 'revision']);
        });

        // Reference attachments on templates (source proposal, portfolio PDFs) and on quotes.
        Schema::create('quote_files', function (Blueprint $table) {
            $table->id();
            $table->string('owner_type', 20);   // template | quote
            $table->unsignedBigInteger('owner_id');
            $table->string('name');
            $table->string('path');
            $table->string('mime', 100)->nullable();
            $table->unsignedBigInteger('size')->default(0);
            $table->timestamps();
            $table->index(['owner_type', 'owner_id']);
        });

        Schema::table('invoices', function (Blueprint $table) {
            $table->foreignId('quote_id')->nullable()->after('client_id')->constrained()->nullOnDelete();
            $table->unsignedInteger('quote_revision')->nullable()->after('quote_id');
            $table->foreignId('contact_id')->nullable()->after('quote_revision')->constrained('client_contacts')->nullOnDelete();
            $table->string('recipient_name')->nullable()->after('contact_id');
            $table->string('recipient_email')->nullable()->after('recipient_name');
        });

        Schema::table('invoice_items', function (Blueprint $table) {
            $table->foreignId('quote_item_id')->nullable()->after('invoice_id')->constrained()->nullOnDelete();
        });

        Schema::create('projects', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('kind', 20)->default('project');   // project | service (ongoing)
            $table->foreignId('owner_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status', 20)->default('planned')->index();
            $table->date('deadline')->nullable();
            $table->string('next_milestone')->nullable();
            $table->date('next_milestone_date')->nullable();
            $table->foreignId('quote_id')->nullable()->constrained()->nullOnDelete();
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('renewals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained()->cascadeOnDelete();
            $table->string('type', 20)->index();   // domain | maintenance | retainer | other
            $table->string('name');
            $table->string('provider')->nullable();
            $table->date('renewal_date')->nullable()->index();
            $table->decimal('amount', 12, 2)->default(0);
            $table->string('frequency', 20)->default('yearly');
            $table->string('status', 20)->default('active')->index();
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('renewal_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('renewal_id')->constrained()->cascadeOnDelete();
            $table->date('due_date')->nullable();
            $table->date('paid_on');
            $table->decimal('amount', 12, 2)->default(0);
            $table->date('next_date')->nullable();
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        // Give every existing client a primary contact built from the details already on the client record.
        $now = now();
        DB::table('clients')->whereNotNull('contact_name')->orWhereNotNull('email')->orderBy('id')->chunk(200, function ($clients) use ($now) {
            foreach ($clients as $client) {
                DB::table('client_contacts')->insert([
                    'client_id' => $client->id, 'name' => $client->contact_name ?: $client->name, 'role' => 'primary',
                    'email' => $client->email, 'phone' => $client->phone1, 'is_primary' => true,
                    'created_at' => $now, 'updated_at' => $now,
                ]);
                if ($client->billing_contact_name || $client->billing_contact_email) {
                    DB::table('client_contacts')->insert([
                        'client_id' => $client->id, 'name' => $client->billing_contact_name ?: 'Billing', 'role' => 'billing',
                        'email' => $client->billing_contact_email, 'phone' => $client->billing_contact_phone, 'is_primary' => false,
                        'created_at' => $now, 'updated_at' => $now,
                    ]);
                }
            }
        });
    }

    public function down(): void
    {
        Schema::table('invoice_items', fn (Blueprint $table) => $table->dropConstrainedForeignId('quote_item_id'));
        Schema::table('invoices', function (Blueprint $table) {
            $table->dropConstrainedForeignId('quote_id');
            $table->dropConstrainedForeignId('contact_id');
            $table->dropColumn(['quote_revision', 'recipient_name', 'recipient_email']);
        });
        foreach ([
            'renewal_logs', 'renewals', 'projects', 'quote_files', 'quote_revisions', 'quote_items', 'quote_sections',
            'quotes', 'quote_template_items', 'quote_template_sections', 'quote_templates', 'follow_ups', 'interactions',
            'lead_work_type', 'leads', 'client_contacts',
        ] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
