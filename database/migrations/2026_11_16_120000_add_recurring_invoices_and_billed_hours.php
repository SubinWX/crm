<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Recurring invoices (a template that raises an invoice every month, quarter or year) and billing
 * of tracked hours (time entries remember which invoice they were charged on).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('recurring_invoices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('frequency', 20)->default('monthly');   // monthly | quarterly | yearly
            $table->date('next_date');                             // the date of the next invoice
            $table->date('end_date')->nullable();                  // no invoices after this day
            $table->unsignedInteger('due_days')->default(15);
            $table->decimal('tax_rate', 5, 2)->default(0);
            $table->text('notes')->nullable();
            $table->boolean('active')->default(true);
            $table->date('last_generated_on')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['active', 'next_date']);
        });

        Schema::create('recurring_invoice_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('recurring_invoice_id')->constrained()->cascadeOnDelete();
            $table->string('description', 500);
            $table->string('hsn_sac', 10)->nullable();
            $table->decimal('quantity', 10, 2)->default(1);
            $table->decimal('unit_price', 12, 2)->default(0);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::table('invoices', fn (Blueprint $table) => $table->foreignId('recurring_invoice_id')->nullable()->after('quote_id')->constrained()->nullOnDelete());
        // Deleting the invoice frees the hours to be billed again.
        Schema::table('time_entries', fn (Blueprint $table) => $table->foreignId('invoice_id')->nullable()->constrained()->nullOnDelete());
        Schema::table('projects', fn (Blueprint $table) => $table->decimal('hourly_rate', 10, 2)->nullable()->after('estimated_hours'));

        if (Schema::hasTable('roadmap_items')) {
            DB::table('roadmap_items')->whereIn('title', ['Recurring invoices', 'Bill tracked hours', 'Reports'])
                ->update(['status' => 'done', 'happened_on' => '2026-11-16', 'updated_at' => now()]);
        }
    }

    public function down(): void
    {
        Schema::table('projects', fn (Blueprint $table) => $table->dropColumn('hourly_rate'));
        Schema::table('time_entries', fn (Blueprint $table) => $table->dropConstrainedForeignId('invoice_id'));
        Schema::table('invoices', fn (Blueprint $table) => $table->dropConstrainedForeignId('recurring_invoice_id'));
        Schema::dropIfExists('recurring_invoice_items');
        Schema::dropIfExists('recurring_invoices');
    }
};
