<?php

namespace App\Support;

use App\Models\Setting;
use Illuminate\Support\Facades\Crypt;

/**
 * The outgoing email server, set by a Super Admin in Settings → Email & WhatsApp.
 * When it is switched on it replaces the mail settings from the .env file, so a mail
 * account bought later can be connected without touching the server.
 */
class MailSettings
{
    public const ENCRYPTIONS = ['tls' => 'TLS / STARTTLS (port 587)', 'ssl' => 'SSL (port 465)', 'none' => 'None (port 25)'];

    public const FIELDS = ['mail_enabled', 'mail_host', 'mail_port', 'mail_encryption', 'mail_username', 'mail_from_address', 'mail_from_name', 'mail_reply_to'];

    public static function enabled(): bool
    {
        return setting('mail_enabled') === '1' && filled(setting('mail_host'));
    }

    /** Passwords and API tokens are stored encrypted and are never shown again after saving. */
    public static function secret(string $key): ?string
    {
        $value = Setting::get($key);
        if (blank($value)) {
            return null;
        }
        try {
            return Crypt::decryptString($value);
        } catch (\Throwable) {
            return null; // APP_KEY changed since it was saved: it has to be typed in again
        }
    }

    public static function putSecret(string $key, ?string $value): void
    {
        Setting::put($key, filled($value) ? Crypt::encryptString($value) : '');
    }

    /**
     * Point Laravel's mailer at the saved server. Called when the app starts and before every
     * queued job, so the queue worker picks up a change without being restarted.
     */
    public static function apply(): void
    {
        try {
            if (! self::enabled()) {
                return;
            }
        } catch (\Throwable) {
            return; // settings table not migrated yet
        }

        $encryption = setting('mail_encryption', 'tls');
        $name = setting('mail_from_name') ?: Branding::name();
        $config = [
            'mail.default' => 'smtp',
            'mail.mailers.smtp.transport' => 'smtp',
            'mail.mailers.smtp.host' => setting('mail_host'),
            'mail.mailers.smtp.port' => (int) (setting('mail_port') ?: 587),
            'mail.mailers.smtp.username' => setting('mail_username') ?: null,
            'mail.mailers.smtp.password' => self::secret('mail_password'),
            // Port 465 uses implicit SSL; 587 upgrades with STARTTLS on its own when the server offers it.
            'mail.mailers.smtp.scheme' => $encryption === 'ssl' ? 'smtps' : 'smtp',
            'mail.mailers.smtp.encryption' => $encryption === 'none' ? null : $encryption,
            'mail.from.name' => $name,
        ];
        if (filled(setting('mail_from_address'))) {
            $config['mail.from.address'] = setting('mail_from_address');
        }
        if (filled(setting('mail_reply_to'))) {
            $config['mail.reply_to'] = ['address' => setting('mail_reply_to'), 'name' => $name];
        }

        if (collect($config)->map(fn ($value, $key) => config($key))->all() != $config) {
            config($config);
            // Drop any mailer built with the old settings so the next email uses the new ones.
            app('mail.manager')->forgetMailers();
        }
    }

    /** A one-line description for the settings page. */
    public static function summary(): string
    {
        if (self::enabled()) {
            return 'Sending through '.setting('mail_host').' as '.(setting('mail_from_address') ?: config('mail.from.address'));
        }

        return config('mail.default') === 'log'
            ? 'No email server connected. Emails are only written to the log file and nobody receives them.'
            : 'Using the mail settings from the server’s .env file ('.config('mail.default').').';
    }
}
