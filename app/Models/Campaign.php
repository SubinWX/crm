<?php

namespace App\Models;

use App\Casts\RichTextCast;
use App\Support\MailSettings;
use App\Support\WhatsApp;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** A bulk message: festival greetings, an offer or a renewal notice sent to a chosen group of clients by email or WhatsApp. */
class Campaign extends Model
{
    public const CHANNELS = ['email' => 'Email', 'whatsapp' => 'WhatsApp'];

    /** Words in curly brackets that are replaced for each client. */
    public const PLACEHOLDERS = ['{client}' => 'the client’s name', '{contact}' => 'the contact person (or the client’s name)', '{company}' => 'your company name'];

    protected $guarded = [];

    public function recipients(): HasMany
    {
        return $this->hasMany(CampaignRecipient::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** Whether messages on this channel can actually leave the building. */
    public static function channelReady(string $channel): bool
    {
        return $channel === 'whatsapp' ? WhatsApp::enabled() : (MailSettings::enabled() || config('mail.default') !== 'log');
    }

    /** Where a client is reached on a channel: their email address, or their mobile number with country code. Null when there is none. */
    public static function addressFor(Client $client, string $channel): ?string
    {
        if ($channel === 'whatsapp') {
            return WhatsApp::normalizePhone($client->phone1) ?? WhatsApp::normalizePhone($client->phone2);
        }
        foreach ([$client->email, $client->billing_contact_email] as $email) {
            if (filled($email) && filter_var($email, FILTER_VALIDATE_EMAIL)) {
                return $email;
            }
        }

        return null;
    }

    /** The text with {client}, {contact} and {company} filled in for one client. */
    public static function personalise(string $text, ?Client $client, bool $html = false): string
    {
        $values = [
            '{client}' => $client->name ?? 'Client name',
            '{contact}' => filled($client?->contact_name) ? $client->contact_name : ($client->name ?? 'Contact name'),
            '{company}' => setting('company_name') ?: app_name(),
        ];

        return strtr($text, $html ? array_map('e', $values) : $values);
    }

    /** Email bodies are editor text and are cleaned like the rest; WhatsApp bodies are plain text. */
    public function setBodyAttribute(?string $value): void
    {
        $this->attributes['body'] = ($this->attributes['channel'] ?? null) === 'email'
            ? (string) (new RichTextCast)->set($this, 'body', $value, [])
            : trim((string) $value);
    }

    /** Marks the campaign finished once nothing is left waiting. */
    public function refreshStatus(): void
    {
        if ($this->status === 'sending' && ! $this->recipients()->where('status', 'queued')->exists()) {
            $this->update(['status' => 'sent']);
        }
    }
}
