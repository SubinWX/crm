<?php

namespace App\Support;

use App\Jobs\SendWhatsAppMessage;
use App\Models\Client;
use App\Models\MessageLog;
use Illuminate\Support\Facades\Http;

/**
 * Sends WhatsApp messages through whichever service the agency has bought, set up by a
 * Super Admin in Settings → Email & WhatsApp:
 *
 *  - "meta":   the official WhatsApp Business Cloud API (Meta). Reminders must use message
 *              templates approved in WhatsApp Manager; the values are passed in a fixed order.
 *  - "custom": any provider with a simple HTTP API. The address, the authorisation header and
 *              the JSON body are typed in, with {phone} and {message} filled in for each message.
 */
class WhatsApp
{
    public const PROVIDERS = ['meta' => 'WhatsApp Business Cloud API (Meta, official)', 'custom' => 'Another provider (custom HTTP API)'];

    /** Reminder kinds, with the wording used when the provider accepts free text. */
    public const KINDS = [
        'hosting' => ['Hosting renewal reminders', 'Hello {client}, your hosting for {item} is due for renewal on {date}. Amount: {amount}. Please contact us to renew. - {company}'],
        'renewal' => ['Domain, maintenance and retainer renewals', 'Hello {client}, {item} is due for renewal on {date}. Amount: {amount}. Please contact us to renew. - {company}'],
        'invoice' => ['Invoice payment reminders', 'Hello {client}, this is a reminder that invoice {item} for {amount} is due on {date}. Please ignore this if you have already paid. - {company}'],
    ];

    public const FIELDS = [
        'whatsapp_enabled', 'whatsapp_provider', 'whatsapp_number', 'whatsapp_country_code',
        'whatsapp_phone_number_id', 'whatsapp_api_version', 'whatsapp_template_language',
        'whatsapp_custom_url', 'whatsapp_custom_auth_header', 'whatsapp_custom_body',
        'whatsapp_send_hosting', 'whatsapp_send_renewal', 'whatsapp_send_invoice',
        'whatsapp_text_hosting', 'whatsapp_text_renewal', 'whatsapp_text_invoice',
        'whatsapp_template_hosting', 'whatsapp_template_renewal', 'whatsapp_template_invoice',
        'whatsapp_invoice_days',
    ];

    public const DEFAULTS = [
        'whatsapp_provider' => 'meta', 'whatsapp_country_code' => '91', 'whatsapp_api_version' => 'v21.0',
        'whatsapp_template_language' => 'en', 'whatsapp_custom_auth_header' => 'Authorization',
        'whatsapp_custom_body' => '{"to": "{phone}", "message": "{message}"}',
        // Days relative to the invoice due date: negative = before, 0 = on the day, positive = after (overdue).
        'whatsapp_invoice_days' => '-3,0,7',
    ];

    public static function get(string $key): string
    {
        $value = setting($key);

        return (string) (filled($value) ? $value : (self::DEFAULTS[$key] ?? (str_starts_with($key, 'whatsapp_text_') ? self::KINDS[substr($key, 14)][1] : '')));
    }

    public static function provider(): string
    {
        return array_key_exists(self::get('whatsapp_provider'), self::PROVIDERS) ? self::get('whatsapp_provider') : 'meta';
    }

    /** Switched on, and everything the chosen provider needs is filled in. */
    public static function enabled(): bool
    {
        return self::get('whatsapp_enabled') === '1' && self::missing() === [];
    }

    /** @return string[] what still has to be filled in before messages can go out */
    public static function missing(): array
    {
        $missing = [];
        if (blank(MailSettings::secret('whatsapp_token'))) {
            $missing[] = 'API token';
        }
        if (self::provider() === 'meta' && blank(setting('whatsapp_phone_number_id'))) {
            $missing[] = 'Phone number ID';
        }
        if (self::provider() === 'custom' && blank(setting('whatsapp_custom_url'))) {
            $missing[] = 'API address';
        }

        return $missing;
    }

    public static function sends(string $kind): bool
    {
        return self::enabled() && self::get("whatsapp_send_{$kind}") === '1';
    }

    /** @return int[] day offsets from the due date on which an unpaid invoice is chased, earliest first */
    public static function invoiceDays(): array
    {
        $days = collect(explode(',', self::get('whatsapp_invoice_days')))->map(fn ($d) => trim($d))
            ->filter(fn ($d) => preg_match('/^-?\d{1,3}$/', $d))->map(fn ($d) => (int) $d)->unique()->sort()->values()->all();

        return $days ?: [0];
    }

    /** Digits only with the country code, e.g. "98765 43210" becomes "919876543210". Null when it cannot be a mobile number. */
    public static function normalizePhone(?string $phone): ?string
    {
        $digits = preg_replace('/\D+/', '', (string) $phone);
        if (str_starts_with(trim((string) $phone), '+') || strlen($digits) > 10) {
            $digits = ltrim($digits, '0');
        } else {
            $digits = preg_replace('/\D+/', '', self::get('whatsapp_country_code')).ltrim($digits, '0');
        }

        return strlen($digits) >= 10 && strlen($digits) <= 15 ? $digits : null;
    }

    /** The message for one reminder, with the client's details filled in. @param array{client:string,item:string,date:string,amount:string} $values */
    public static function compose(string $kind, array $values): string
    {
        $values += ['company' => setting('company_name') ?: app_name()];

        return trim(preg_replace_callback('/\{(client|item|date|amount|company)\}/', fn ($m) => (string) ($values[$m[1]] ?? ''), self::get("whatsapp_text_{$kind}")));
    }

    /**
     * Record a message and hand it to the queue. For automatic reminders the unique key
     * (event, record, reminder date) means a second attempt to queue the same one does nothing.
     */
    public static function queue(string $event, string $kind, ?Client $client, ?string $phone, array $values, ?string $subjectType = null, ?int $subjectId = null, ?string $scheduled = null, ?string $body = null): ?MessageLog
    {
        $to = self::normalizePhone($phone);
        $attributes = [
            'channel' => 'whatsapp', 'event' => $event, 'subject_type' => $subjectType, 'subject_id' => $subjectId, 'scheduled_date' => $scheduled,
        ];
        $data = [
            'client_id' => $client?->id, 'recipient' => $to ?? mb_substr((string) $phone, 0, 30),
            'body' => $body ?? self::compose($kind, $values),
            'parameters' => json_encode(['kind' => $kind, 'values' => array_values(array_intersect_key($values, array_flip(['client', 'item', 'date', 'amount'])))]),
            'status' => $to ? 'queued' : 'skipped', 'error' => $to ? null : 'No valid mobile number on the client record.',
            'created_by' => auth('web')->id(), 'created_at' => now(), 'updated_at' => now(),
        ];

        if ($scheduled !== null) {
            if (! MessageLog::query()->insertOrIgnore($attributes + $data)) {
                return null; // already queued for this reminder date
            }
            $log = MessageLog::where($attributes)->first();
        } else {
            $log = MessageLog::create($attributes + ['parameters' => json_decode($data['parameters'], true)] + $data);
        }

        if ($log && $log->status === 'queued') {
            SendWhatsAppMessage::dispatch($log->id);
        }

        return $log;
    }

    /**
     * Send one message now.
     *
     * @param  array{kind?:string, values?:string[]}  $parameters
     * @return array{ok:bool, id:?string, error:?string}
     */
    public static function send(string $to, string $body, array $parameters = []): array
    {
        $token = MailSettings::secret('whatsapp_token');
        if (self::missing() !== []) {
            return ['ok' => false, 'id' => null, 'error' => 'WhatsApp is not fully set up: '.implode(', ', self::missing()).' missing.'];
        }

        try {
            if (self::provider() === 'meta') {
                $template = filled($parameters['kind'] ?? null) ? self::get('whatsapp_template_'.$parameters['kind']) : '';
                $payload = ['messaging_product' => 'whatsapp', 'recipient_type' => 'individual', 'to' => $to];
                $payload += $template !== ''
                    ? ['type' => 'template', 'template' => [
                        'name' => $template, 'language' => ['code' => self::get('whatsapp_template_language')],
                        'components' => [['type' => 'body', 'parameters' => array_map(fn ($v) => ['type' => 'text', 'text' => (string) $v], $parameters['values'] ?? [])]],
                    ]]
                    : ['type' => 'text', 'text' => ['preview_url' => false, 'body' => $body]];

                $response = Http::withToken($token)->acceptJson()->timeout(20)
                    ->post('https://graph.facebook.com/'.self::get('whatsapp_api_version').'/'.setting('whatsapp_phone_number_id').'/messages', $payload);

                return $response->successful()
                    ? ['ok' => true, 'id' => $response->json('messages.0.id'), 'error' => null]
                    : ['ok' => false, 'id' => null, 'error' => mb_substr((string) ($response->json('error.message') ?? $response->body()), 0, 500)];
            }

            // Custom provider: fill {phone} and {message} into the JSON body, escaped so quotes in a message cannot break it.
            $escape = fn (string $value) => substr(json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), 1, -1);
            $json = strtr(self::get('whatsapp_custom_body'), ['{phone}' => $escape($to), '{message}' => $escape($body)]);
            $payload = json_decode($json, true);
            if (! is_array($payload)) {
                return ['ok' => false, 'id' => null, 'error' => 'The request body in the WhatsApp settings is not valid JSON.'];
            }
            $response = Http::withHeaders([self::get('whatsapp_custom_auth_header') => $token])->acceptJson()->timeout(20)
                ->post(setting('whatsapp_custom_url'), $payload);

            return $response->successful()
                ? ['ok' => true, 'id' => (string) ($response->json('id') ?? $response->json('message_id') ?? $response->json('messages.0.id') ?? '') ?: null, 'error' => null]
                : ['ok' => false, 'id' => null, 'error' => 'The provider answered '.$response->status().': '.mb_substr($response->body(), 0, 400)];
        } catch (\Throwable $e) {
            return ['ok' => false, 'id' => null, 'error' => 'Could not reach the WhatsApp service: '.mb_substr($e->getMessage(), 0, 300)];
        }
    }
}
