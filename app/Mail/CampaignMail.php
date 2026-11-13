<?php

namespace App\Mail;

use App\Models\Campaign;
use App\Models\Client;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Support\Facades\URL;

/** One client's copy of a bulk email, with a link to stop receiving them. */
class CampaignMail extends Mailable
{
    public function __construct(public string $subjectLine, public string $bodyHtml, public ?Client $client = null) {}

    public static function for(Campaign $campaign, ?Client $client): self
    {
        return new self(Campaign::personalise((string) $campaign->subject, $client), Campaign::personalise($campaign->body, $client, html: true), $client);
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: $this->subjectLine);
    }

    public function content(): Content
    {
        return new Content(view: 'emails.campaign', with: [
            'body' => $this->bodyHtml,
            'company' => setting('company_name') ?: app_name(),
            'unsubscribe' => $this->client ? URL::signedRoute('bulk.unsubscribe', ['client' => $this->client->id]) : null,
        ]);
    }
}
