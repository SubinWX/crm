<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('clients', fn (Blueprint $table) => $table->string('logo_path')->nullable()->after('website'));
        Schema::table('users', fn (Blueprint $table) => $table->string('avatar_path')->nullable()->after('alt_phone'));
    }

    public function down(): void
    {
        Schema::table('clients', fn (Blueprint $table) => $table->dropColumn('logo_path'));
        Schema::table('users', fn (Blueprint $table) => $table->dropColumn('avatar_path'));
    }
};
