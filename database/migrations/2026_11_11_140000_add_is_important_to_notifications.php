<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('notifications', fn (Blueprint $table) => $table->boolean('is_important')->default(false)->after('read_at')->index());
    }

    public function down(): void
    {
        Schema::table('notifications', fn (Blueprint $table) => $table->dropColumn('is_important'));
    }
};
