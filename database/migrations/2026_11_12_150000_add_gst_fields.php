<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** GST: state on clients, HSN/SAC on invoice lines, and the place of supply and tax split on invoices. */
    public function up(): void
    {
        Schema::table('clients', fn (Blueprint $table) => $table->string('state_code', 2)->nullable()->after('tax_id'));
        Schema::table('invoice_items', fn (Blueprint $table) => $table->string('hsn_sac', 10)->nullable()->after('description'));
        Schema::table('invoices', function (Blueprint $table) {
            // Kept on the invoice so a later change of the client's or company's details never rewrites an issued invoice.
            $table->string('supplier_state', 2)->nullable()->after('currency');
            $table->string('place_of_supply', 2)->nullable()->after('supplier_state');
            $table->string('client_gstin', 20)->nullable()->after('place_of_supply');
            $table->decimal('cgst_amount', 12, 2)->default(0)->after('tax_amount');
            $table->decimal('sgst_amount', 12, 2)->default(0)->after('cgst_amount');
            $table->decimal('igst_amount', 12, 2)->default(0)->after('sgst_amount');
        });
    }

    public function down(): void
    {
        Schema::table('invoices', fn (Blueprint $table) => $table->dropColumn(['supplier_state', 'place_of_supply', 'client_gstin', 'cgst_amount', 'sgst_amount', 'igst_amount']));
        Schema::table('invoice_items', fn (Blueprint $table) => $table->dropColumn('hsn_sac'));
        Schema::table('clients', fn (Blueprint $table) => $table->dropColumn('state_code'));
    }
};
