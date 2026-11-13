<?php

namespace App\Support;

use Illuminate\Support\Facades\Http;

/**
 * Sends text messages (SMS) through whichever SMS service the agency has bought, set up by a
 * Super Admin in Settings → Email, WhatsApp & SMS. Any provider with a simple HTTP API works:
 *
 *  - "post": the provider is sent a JSON body, with the key in a header.
 *  - "get":  everything goes in the web address itself (common with Indian SMS gateways).
 *
 * {phone}, {message}, {sender}, {template_id} and (for "get") {token} are filled in for each message.
 * In India the sender ID and every message template must be registered on a DLT portal first.
 */
class Sms
{
    public const METHODS = ['post' => 'POST with a JSON body (key in a header)', 'get' => 'GET with everything in the address'];

    public const FIELDS = ['sms_enabled', 'sms_method', 'sms_url', 'sms_auth_header', 'sms_body', 'sms_sender_id'];

    public const DEFAULTS = [
        'sms_method' => 'post', 'sms_auth_header' => 'Authorization',
        'sms_body' => '{"to": "{phone}", "message": "{message}", "sender": "{sender}", "template_id": "{template_id}"}',
    ];

    public const PLACEHOLDERS = ['{phone}', '{message}', '{sender}', '{template_id}', '{token}'];

    public static function get(string $key): string
    {
        $value = setting($key);

        return (string) (filled($value) ? $value : (self::DEFAULTS[$key] ?? ''));
    }

    public static function method(): string
    {
        return array_key_exists(self::get('sms_method'), self::METHODS) ? self::get('sms_method') : 'post';
    }

    /** Switched on, and everything needed is filled in. */
    public static function enabled(): bool
    {
        return self::get('sms_enabled') === '1' && self::missing() === [];
    }

    /** @return string[] what still has to be filled in before text messages can go out */
    public static function missing(): array
    {
        $missing = [];
        if (blank(setting('sms_url'))) {
            $missing[] = 'API address';
        }
        if (blank(MailSettings::secret('sms_token'))) {
            $missing[] = 'API key';
        }

        return $missing;
    }

    /**
     * Send one text message now.
     *
     * @return array{ok:bool, id:?string, error:?string}
     */
    public static function send(string $to, string $body, ?string $templateId = null): array
    {
        if (self::missing() !== []) {
            return ['ok' => false, 'id' => null, 'error' => 'SMS is not fully set up: '.implode(', ', self::missing()).' missing.'];
        }
        $token = (string) MailSettings::secret('sms_token');
        $values = ['{phone}' => $to, '{message}' => $body, '{sender}' => self::get('sms_sender_id'), '{template_id}' => (string) $templateId];

        try {
            if (self::method() === 'get') {
                // Each value is encoded so a space, & or # in a message cannot change the address.
                $url = strtr((string) setting('sms_url'), array_map('rawurlencode', $values + ['{token}' => $token]));
                $response = Http::acceptJson()->timeout(20)->get($url);
            } else {
                // Escaped so quotes or line breaks in a message cannot break the JSON.
                $escape = fn (string $value) => substr(json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), 1, -1);
                $payload = json_decode(strtr(self::get('sms_body'), array_map($escape, $values)), true);
                if (! is_array($payload)) {
                    return ['ok' => false, 'id' => null, 'error' => 'The request body in the SMS settings is not valid JSON.'];
                }
                $response = Http::withHeaders([self::get('sms_auth_header') => $token])->acceptJson()->timeout(20)->post((string) setting('sms_url'), $payload);
            }

            return $response->successful()
                ? ['ok' => true, 'id' => (string) ($response->json('id') ?? $response->json('message_id') ?? $response->json('request_id') ?? '') ?: null, 'error' => null]
                : ['ok' => false, 'id' => null, 'error' => 'The provider answered '.$response->status().': '.mb_substr($response->body(), 0, 400)];
        } catch (\Throwable $e) {
            // The address can hold the key, so the error text is not passed on.
            return ['ok' => false, 'id' => null, 'error' => 'Could not reach the SMS service. Check the API address and the internet connection.'];
        }
    }
}
