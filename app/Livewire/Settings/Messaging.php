<?php

namespace App\Livewire\Settings;

use App\Models\MessageLog;
use App\Models\Setting;
use App\Support\MailSettings;
use App\Support\WhatsApp;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Title;
use Livewire\Component;

/** Settings → Email & WhatsApp: connect the outgoing email server and the WhatsApp API. Super Admins only. */
#[Title('Email & WhatsApp')]
class Messaging extends Component
{
    public array $mail = [];

    /** Only filled when typing a new one; the saved password is never sent back to the browser. */
    public string $mailPassword = '';

    public string $testEmail = '';

    public ?array $mailResult = null;

    public array $wa = [];

    public string $waToken = '';

    public string $testPhone = '';

    public ?array $waResult = null;

    public function boot(): void
    {
        Gate::authorize('admin');
    }

    public function mount(): void
    {
        foreach (MailSettings::FIELDS as $key) {
            $this->mail[$key] = (string) setting($key);
        }
        $this->mail['mail_enabled'] = $this->mail['mail_enabled'] === '1' ? '1' : '0';
        $this->mail['mail_encryption'] = $this->mail['mail_encryption'] ?: 'tls';
        $this->mail['mail_port'] = $this->mail['mail_port'] ?: '587';
        foreach (WhatsApp::FIELDS as $key) {
            $this->wa[$key] = WhatsApp::get($key);
        }
        // On/off switches that have never been saved start as off.
        foreach (['whatsapp_enabled', 'whatsapp_send_hosting', 'whatsapp_send_renewal', 'whatsapp_send_invoice'] as $key) {
            $this->wa[$key] = $this->wa[$key] === '1' ? '1' : '0';
        }
        $this->testEmail = (string) auth()->user()->email;
    }

    public function saveMail(): void
    {
        $on = $this->mail['mail_enabled'] === '1';
        $this->validate([
            'mail.mail_enabled' => ['required', Rule::in(['0', '1'])],
            'mail.mail_host' => [Rule::requiredIf($on), 'nullable', 'string', 'max:255', 'regex:/^[A-Za-z0-9.\-]+$/'],
            'mail.mail_port' => [Rule::requiredIf($on), 'nullable', 'integer', 'between:1,65535'],
            'mail.mail_encryption' => ['required', Rule::in(array_keys(MailSettings::ENCRYPTIONS))],
            'mail.mail_username' => ['nullable', 'string', 'max:255'],
            'mailPassword' => ['nullable', 'string', 'max:500'],
            'mail.mail_from_address' => [Rule::requiredIf($on), 'nullable', 'email', 'max:255'],
            'mail.mail_from_name' => ['nullable', 'string', 'max:100'],
            'mail.mail_reply_to' => ['nullable', 'email', 'max:255'],
        ], ['mail.mail_host.regex' => 'Enter the server name only, for example smtp.gmail.com (no https:// and no spaces).'],
            ['mail.mail_host' => 'server', 'mail.mail_port' => 'port', 'mail.mail_username' => 'username', 'mailPassword' => 'password',
                'mail.mail_from_address' => 'from address', 'mail.mail_from_name' => 'from name', 'mail.mail_reply_to' => 'reply-to address']);

        foreach (MailSettings::FIELDS as $key) {
            Setting::put($key, trim((string) $this->mail[$key]));
        }
        if ($this->mailPassword !== '') {
            MailSettings::putSecret('mail_password', $this->mailPassword);
            $this->mailPassword = '';
        }
        MailSettings::apply();
        $this->mailResult = null;

        activity('updated', 'Email server settings updated');
        $this->dispatch('toast', message: 'Email settings saved.');
    }

    public function removeMailPassword(): void
    {
        MailSettings::putSecret('mail_password', null);
        $this->dispatch('toast', message: 'Saved email password removed.');
    }

    /** Sends straight away (not through the queue) so any connection problem is shown on the page. */
    public function sendTestEmail(): void
    {
        $this->validate(['testEmail' => ['required', 'email']], [], ['testEmail' => 'email address']);
        MailSettings::apply();

        try {
            Mail::raw('This is a test email from '.app_name().". If you are reading it, the email server is connected correctly.\n\nSent ".fmt_datetime(now()).'.',
                fn ($message) => $message->to($this->testEmail)->subject('Test email from '.app_name()));
            $this->mailResult = config('mail.default') === 'log'
                ? ['ok' => false, 'message' => 'No email server is connected yet, so the test was only written to the log file. Switch on “Use this email server” and save first.']
                : ['ok' => true, 'message' => "Sent to {$this->testEmail}. Check the inbox (and the spam folder)."];
        } catch (\Throwable $e) {
            $this->mailResult = ['ok' => false, 'message' => 'The email server refused the message: '.mb_substr($e->getMessage(), 0, 400)];
        }
    }

    public function saveWhatsApp(): void
    {
        $on = $this->wa['whatsapp_enabled'] === '1';
        $meta = $this->wa['whatsapp_provider'] === 'meta';
        $toggle = ['required', Rule::in(['0', '1'])];

        $this->validate([
            'wa.whatsapp_enabled' => $toggle,
            'wa.whatsapp_provider' => ['required', Rule::in(array_keys(WhatsApp::PROVIDERS))],
            'wa.whatsapp_number' => ['nullable', 'string', 'max:25', 'regex:/^[0-9+()\-\s]+$/'],
            'wa.whatsapp_country_code' => ['required', 'regex:/^\+?[0-9]{1,4}$/'],
            'wa.whatsapp_phone_number_id' => [Rule::requiredIf($on && $meta), 'nullable', 'regex:/^[0-9]{5,30}$/'],
            'wa.whatsapp_api_version' => ['required', 'regex:/^v[0-9]{1,3}\.[0-9]{1,2}$/'],
            'wa.whatsapp_template_language' => ['required', 'regex:/^[a-z]{2}(_[A-Z]{2})?$/'],
            // Must be a public https address: tokens are never sent over plain http or to this server's own network.
            'wa.whatsapp_custom_url' => [Rule::requiredIf($on && ! $meta), 'nullable', 'url:https', 'max:500', function ($attribute, $value, $fail) {
                $host = strtolower((string) parse_url((string) $value, PHP_URL_HOST));
                if ($host === 'localhost' || str_ends_with($host, '.local') || str_ends_with($host, '.internal')
                    || (filter_var($host, FILTER_VALIDATE_IP) && ! filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE))) {
                    $fail('Enter the public address of your WhatsApp provider.');
                }
            }],
            'wa.whatsapp_custom_auth_header' => ['required', 'regex:/^[A-Za-z0-9\-]{1,60}$/'],
            'wa.whatsapp_custom_body' => ['required', 'string', 'max:2000', function ($attribute, $value, $fail) {
                if (! is_array(json_decode(strtr((string) $value, ['{phone}' => '1', '{message}' => 'x']), true))) {
                    $fail('The request body must be valid JSON.');
                } elseif (! str_contains((string) $value, '{phone}') || ! str_contains((string) $value, '{message}')) {
                    $fail('The request body must contain both {phone} and {message}.');
                }
            }],
            'waToken' => ['nullable', 'string', 'max:2000'],
            'wa.whatsapp_send_hosting' => $toggle, 'wa.whatsapp_send_renewal' => $toggle, 'wa.whatsapp_send_invoice' => $toggle,
            'wa.whatsapp_text_hosting' => ['required', 'string', 'max:900'], 'wa.whatsapp_text_renewal' => ['required', 'string', 'max:900'], 'wa.whatsapp_text_invoice' => ['required', 'string', 'max:900'],
            'wa.whatsapp_template_hosting' => ['nullable', 'regex:/^[a-z0-9_]{1,100}$/'], 'wa.whatsapp_template_renewal' => ['nullable', 'regex:/^[a-z0-9_]{1,100}$/'], 'wa.whatsapp_template_invoice' => ['nullable', 'regex:/^[a-z0-9_]{1,100}$/'],
            'wa.whatsapp_invoice_days' => ['required', 'regex:/^\s*-?\d{1,3}(\s*,\s*-?\d{1,3}){0,5}\s*$/'],
        ], [
            'wa.whatsapp_phone_number_id.regex' => 'The Phone number ID is the long number shown in WhatsApp Manager, not the phone number itself.',
            'wa.*.regex' => 'This value is not in the expected format.',
            'wa.whatsapp_template_hosting.regex' => 'Template names use lowercase letters, numbers and underscores only.',
            'wa.whatsapp_template_renewal.regex' => 'Template names use lowercase letters, numbers and underscores only.',
            'wa.whatsapp_template_invoice.regex' => 'Template names use lowercase letters, numbers and underscores only.',
            'wa.whatsapp_invoice_days.regex' => 'Enter whole numbers separated by commas, for example -3,0,7.',
        ], collect(WhatsApp::FIELDS)->mapWithKeys(fn ($k) => ["wa.{$k}" => str_replace(['whatsapp_', '_'], ['', ' '], $k)])->all() + ['waToken' => 'API token']);

        foreach (WhatsApp::FIELDS as $key) {
            Setting::put($key, trim((string) $this->wa[$key]));
        }
        if ($this->waToken !== '') {
            MailSettings::putSecret('whatsapp_token', trim($this->waToken));
            $this->waToken = '';
        }
        $this->waResult = null;

        activity('updated', 'WhatsApp settings updated');
        $this->dispatch('toast', message: 'WhatsApp settings saved.');
    }

    public function removeWhatsAppToken(): void
    {
        MailSettings::putSecret('whatsapp_token', null);
        $this->dispatch('toast', message: 'Saved API token removed.');
    }

    public function sendTestWhatsApp(): void
    {
        $this->validate(['testPhone' => ['required', 'string', 'max:25']], [], ['testPhone' => 'mobile number']);
        $to = WhatsApp::normalizePhone($this->testPhone);
        if (! $to) {
            $this->addError('testPhone', 'Enter a mobile number with its country code, for example +91 98765 43210.');

            return;
        }

        $body = 'Test message from '.(setting('company_name') ?: app_name()).'. WhatsApp reminders are connected.';
        $result = WhatsApp::send($to, $body);
        MessageLog::create([
            'event' => 'test', 'recipient' => $to, 'body' => $body, 'status' => $result['ok'] ? 'sent' : 'failed',
            'sent_at' => $result['ok'] ? now() : null, 'provider_message_id' => $result['id'], 'error' => $result['error'], 'created_by' => auth()->id(),
        ]);
        $this->waResult = $result['ok']
            ? ['ok' => true, 'message' => "Accepted by the WhatsApp service for +{$to}. It should arrive within a minute."]
            : ['ok' => false, 'message' => $result['error']];
    }

    public function render()
    {
        return view('livewire.settings.messaging', [
            'hasMailPassword' => filled(MailSettings::secret('mail_password')),
            'hasWaToken' => filled(MailSettings::secret('whatsapp_token')),
            'mailSummary' => MailSettings::summary(),
            'waMissing' => WhatsApp::missing(),
            'waLive' => WhatsApp::enabled(),
            'recent' => MessageLog::with('client')->latest()->latest('id')->limit(15)->get(),
        ]);
    }
}
