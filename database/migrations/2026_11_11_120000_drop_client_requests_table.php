<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

/**
 * "Client Requests" duplicated the support tickets, so it was removed. Requests are now
 * support tickets (Support → Support Tickets, and the Support tab on a client profile).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('client_requests');
    }

    public function down(): void
    {
        // Intentionally empty: the feature no longer exists.
    }
};
