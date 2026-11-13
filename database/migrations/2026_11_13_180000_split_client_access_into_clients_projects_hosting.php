<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * "View clients, hosting and files" and "Add, edit and archive clients and hosting" used to cover clients,
 * projects and hosting together. They are now three separate areas. Everyone keeps what they could do before:
 * whoever had the client option gets the matching project and hosting options switched on as well.
 */
return new class extends Migration
{
    public function up(): void
    {
        foreach (DB::table('users')->whereNotNull('permissions')->get(['id', 'permissions']) as $user) {
            $permissions = json_decode((string) $user->permissions, true);
            if (! is_array($permissions)) {
                continue;
            }
            $new = $permissions;
            if (in_array('clients.view', $permissions, true) || in_array('clients.manage', $permissions, true)) {
                array_push($new, 'projects.view', 'hosting.view');
            }
            if (in_array('clients.manage', $permissions, true)) {
                array_push($new, 'clients.view', 'projects.manage', 'hosting.manage');
            }
            $new = array_values(array_unique($new));
            if ($new !== $permissions) {
                DB::table('users')->where('id', $user->id)->update(['permissions' => json_encode($new)]);
            }
        }
    }

    public function down(): void
    {
        foreach (DB::table('users')->whereNotNull('permissions')->get(['id', 'permissions']) as $user) {
            $permissions = json_decode((string) $user->permissions, true);
            if (is_array($permissions)) {
                DB::table('users')->where('id', $user->id)->update(['permissions' => json_encode(array_values(array_diff($permissions, ['projects.view', 'projects.manage', 'hosting.view', 'hosting.manage'])))]);
            }
        }
    }
};
