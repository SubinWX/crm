<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** The company's bank accounts, kept so Admins and Super Admins can copy the details to send to a client. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bank_accounts', function (Blueprint $table) {
            $table->id();
            $table->string('label');                       // what you call it, e.g. "Main current account"
            $table->string('account_name');                // the name the account is held in
            $table->string('bank_name');
            $table->text('account_number');                // stored encrypted
            $table->string('ifsc', 20)->nullable();
            $table->string('branch')->nullable();
            $table->string('account_type', 20)->nullable(); // current | savings | other
            $table->string('swift', 20)->nullable();
            $table->string('upi_id')->nullable();
            $table->string('notes', 500)->nullable();
            $table->boolean('is_default')->default(false);
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bank_accounts');
    }
};
