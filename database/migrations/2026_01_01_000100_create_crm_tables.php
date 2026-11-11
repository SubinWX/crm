<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('settings', function (Blueprint $table) {
            $table->string('key')->primary();
            $table->text('value')->nullable();
            $table->timestamps();
        });

        // Configurable dropdown values (work types, industries, server types, ...).
        Schema::create('lookups', function (Blueprint $table) {
            $table->id();
            $table->string('type', 40)->index();
            $table->string('name');
            $table->boolean('is_active')->default(true);
            // Only meaningful for server types: marks arrangements the agency bills for.
            $table->boolean('is_paid')->default(false);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
            $table->unique(['type', 'name']);
        });

        Schema::create('clients', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('contact_name')->nullable();
            $table->string('email')->nullable();
            $table->string('phone1', 30)->nullable();
            $table->string('phone2', 30)->nullable();
            $table->string('website')->nullable();
            $table->text('billing_address')->nullable();
            $table->string('location')->nullable();
            $table->foreignId('industry_id')->nullable()->constrained('lookups')->nullOnDelete();
            $table->date('join_date')->nullable();
            $table->string('status', 20)->default('active')->index();
            $table->foreignId('account_manager_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('notes')->nullable();
            $table->foreignId('acquisition_source_id')->nullable()->constrained('lookups')->nullOnDelete();
            $table->foreignId('referrer_id')->nullable()->constrained('lookups')->nullOnDelete();
            $table->string('billing_contact_name')->nullable();
            $table->string('billing_contact_email')->nullable();
            $table->string('billing_contact_phone', 30)->nullable();
            $table->string('tax_id', 40)->nullable();
            $table->string('preferred_channel', 20)->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('archived_at')->nullable()->index();
            $table->timestamps();
            $table->index('name');
        });

        Schema::create('client_work_type', function (Blueprint $table) {
            $table->foreignId('client_id')->constrained()->cascadeOnDelete();
            $table->foreignId('lookup_id')->constrained('lookups')->cascadeOnDelete();
            $table->primary(['client_id', 'lookup_id']);
        });

        Schema::create('client_files', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained()->cascadeOnDelete();
            $table->string('category', 20)->default('other');
            $table->string('name');
            $table->string('path');
            $table->string('mime', 100)->nullable();
            $table->unsignedBigInteger('size')->default(0);
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('hosting_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained()->cascadeOnDelete();
            $table->string('domain');
            $table->foreignId('server_type_id')->nullable()->constrained('lookups')->nullOnDelete();
            $table->foreignId('server_provider_id')->nullable()->constrained('lookups')->nullOnDelete();
            $table->date('billing_start')->nullable();
            $table->date('billing_end')->nullable();
            $table->date('next_payment_date')->nullable()->index();
            $table->decimal('renewal_amount', 12, 2)->default(0);
            $table->string('billing_frequency', 20)->default('yearly');
            $table->string('status', 20)->default('active')->index();
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('hosting_renewals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('hosting_record_id')->constrained()->cascadeOnDelete();
            $table->date('period_start')->nullable();
            $table->date('period_end')->nullable();
            $table->date('payment_due_date')->nullable();
            $table->date('paid_on');
            $table->decimal('amount', 12, 2)->default(0);
            $table->date('next_payment_date')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('invoices', function (Blueprint $table) {
            $table->id();
            $table->string('number', 40)->unique();
            $table->foreignId('client_id')->constrained()->restrictOnDelete();
            $table->date('invoice_date');
            $table->date('planned_send_date')->nullable()->index();
            $table->timestamp('sent_at')->nullable();
            $table->date('due_date')->nullable();
            $table->string('currency', 3)->default('INR');
            $table->decimal('subtotal', 12, 2)->default(0);
            $table->decimal('tax_rate', 5, 2)->default(0);
            $table->decimal('tax_amount', 12, 2)->default(0);
            $table->decimal('discount', 12, 2)->default(0);
            $table->decimal('total', 12, 2)->default(0);
            $table->decimal('amount_paid', 12, 2)->default(0);
            // draft | sent | partially_paid | paid | void. "Overdue" is derived from due_date.
            $table->string('status', 20)->default('draft')->index();
            $table->text('notes')->nullable();
            $table->string('uploaded_pdf_path')->nullable();
            $table->string('uploaded_pdf_name')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('invoice_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('invoice_id')->constrained()->cascadeOnDelete();
            $table->string('description', 500);
            $table->decimal('quantity', 10, 2)->default(1);
            $table->decimal('unit_price', 12, 2)->default(0);
            $table->decimal('amount', 12, 2)->default(0);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained()->restrictOnDelete();
            $table->foreignId('payment_method_id')->nullable()->constrained('lookups')->nullOnDelete();
            $table->decimal('amount', 12, 2);
            $table->date('payment_date')->index();
            // "Payment To": the Super Admin who received the money. Kept after deactivation.
            $table->foreignId('received_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->string('reference')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('payment_allocations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payment_id')->constrained()->cascadeOnDelete();
            $table->foreignId('invoice_id')->constrained()->cascadeOnDelete();
            $table->decimal('amount', 12, 2);
            $table->timestamps();
            $table->unique(['payment_id', 'invoice_id']);
        });

        Schema::create('expenses', function (Blueprint $table) {
            $table->id();
            $table->date('expense_date')->index();
            $table->decimal('amount', 12, 2);
            $table->foreignId('category_id')->nullable()->constrained('lookups')->nullOnDelete();
            $table->string('payee')->nullable();
            $table->foreignId('payment_method_id')->nullable()->constrained('lookups')->nullOnDelete();
            $table->text('description')->nullable();
            $table->string('receipt_path')->nullable();
            $table->string('receipt_name')->nullable();
            $table->foreignId('client_id')->nullable()->constrained()->nullOnDelete();
            $table->boolean('is_recurring')->default(false)->index();
            $table->string('recurring_frequency', 20)->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('posters', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->string('platform', 30)->index();
            $table->foreignId('client_id')->nullable()->constrained()->nullOnDelete();
            $table->string('campaign')->nullable();
            $table->text('caption')->nullable();
            $table->string('tags', 500)->nullable();
            $table->string('image_path');
            $table->string('thumb_path')->nullable();
            $table->string('original_name');
            $table->string('mime', 100);
            $table->unsignedBigInteger('size')->default(0);
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('tasks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->foreignId('assignee_id')->nullable()->constrained('users')->nullOnDelete();
            $table->date('due_date')->nullable()->index();
            $table->string('status', 20)->default('todo')->index();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('activity_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('action', 40)->index();
            $table->string('subject_type')->nullable();
            $table->unsignedBigInteger('subject_id')->nullable();
            $table->unsignedBigInteger('client_id')->nullable()->index();
            $table->string('description', 500);
            $table->json('properties')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->timestamp('created_at')->nullable()->index();
            $table->index(['subject_type', 'subject_id']);
        });

        // One row per (event, record, scheduled date, recipient, channel). The unique
        // index is what makes scheduler reruns and queue retries safe.
        Schema::create('reminder_logs', function (Blueprint $table) {
            $table->id();
            $table->string('event', 40);
            $table->string('subject_type', 40);
            $table->unsignedBigInteger('subject_id');
            $table->date('scheduled_date');
            $table->foreignId('recipient_id')->constrained('users')->cascadeOnDelete();
            $table->string('channel', 20);
            $table->timestamp('dispatched_at')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamps();
            $table->unique(
                ['event', 'subject_type', 'subject_id', 'scheduled_date', 'recipient_id', 'channel'],
                'reminder_logs_unique'
            );
        });
    }

    public function down(): void
    {
        foreach ([
            'reminder_logs', 'activity_logs', 'tasks', 'posters', 'expenses', 'payment_allocations',
            'payments', 'invoice_items', 'invoices', 'hosting_renewals', 'hosting_records',
            'client_files', 'client_work_type', 'clients', 'lookups', 'settings',
        ] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
