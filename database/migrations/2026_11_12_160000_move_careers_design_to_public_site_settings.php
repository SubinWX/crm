<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * The logo, header link and button, footer and social links used to belong to the careers
     * page only. They now apply to every public page, so anything already saved is carried
     * over to the shared "site_…" settings. The careers banner settings stay where they are.
     */
    protected const SHARED = [
        'site_name', 'logo_link', 'logo_path', 'nav_url', 'button_label', 'button_url', 'button_style', 'button_shape',
        'button_color', 'button_new_tab', 'footer_copyright', 'terms_label', 'terms_url', 'privacy_label', 'privacy_url',
        'social_facebook', 'social_instagram', 'social_linkedin', 'social_x', 'social_youtube', 'social_whatsapp',
    ];

    public function up(): void
    {
        foreach (self::SHARED as $field) {
            $row = DB::table('settings')->where('key', 'careers_'.$field)->first();
            if ($row && filled($row->value) && ! DB::table('settings')->where('key', 'site_'.$field)->exists()) {
                DB::table('settings')->insert(['key' => 'site_'.$field, 'value' => $row->value, 'created_at' => now(), 'updated_at' => now()]);
            }
            DB::table('settings')->where('key', 'careers_'.$field)->delete();
        }
        // The careers header always links to the list of open positions now, so its old label setting goes.
        DB::table('settings')->where('key', 'careers_nav_label')->delete();
    }

    public function down(): void
    {
        foreach (self::SHARED as $field) {
            $row = DB::table('settings')->where('key', 'site_'.$field)->first();
            if ($row) {
                DB::table('settings')->updateOrInsert(['key' => 'careers_'.$field], ['value' => $row->value, 'updated_at' => now()]);
                DB::table('settings')->where('key', 'site_'.$field)->delete();
            }
        }
    }
};
