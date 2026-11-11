<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('equipment', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->foreignId('type_id')->nullable()->constrained('lookups')->nullOnDelete();
            $table->string('brand_model')->nullable();
            $table->string('serial_number')->nullable()->index();
            // Who has it. Null means it is in stock / unassigned.
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->date('assigned_on')->nullable();
            $table->date('purchase_date')->nullable();
            $table->decimal('purchase_cost', 12, 2)->nullable();
            $table->string('condition', 20)->default('good');
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('equipment');
    }
};
