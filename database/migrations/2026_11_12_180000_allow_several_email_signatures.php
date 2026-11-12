<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** A person can now keep several named signatures (one per layout, brand or mailbox) instead of one. */
    public function up(): void
    {
        Schema::table('email_signatures', function (Blueprint $table) {
            $table->string('name', 120)->default('My signature')->after('user_id');
            // The foreign key needs an index of its own before the one-per-person rule can go.
            $table->index('user_id', 'email_signatures_user_id_index');
        });
        Schema::table('email_signatures', fn (Blueprint $table) => $table->dropUnique('email_signatures_user_id_unique'));
    }

    public function down(): void
    {
        Schema::table('email_signatures', fn (Blueprint $table) => $table->dropColumn('name'));
    }
};
