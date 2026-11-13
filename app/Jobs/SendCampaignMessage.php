<?php

namespace App\Jobs;

use App\Mail\CampaignMail;
use App\Models\Campaign;
use App\Models\CampaignRecipient;
use App\Support\WhatsApp;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Mail;

/** Sends one client's copy of a bulk message and records the result. Runs once: a failure is kept, not retried blindly. */
class SendCampaignMessage implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public function __construct(public int $recipientId) {}

    public function handle(): void
    {
        $recipient = CampaignRecipient::with(['campaign', 'client'])->find($this->recipientId);
        if (! $recipient || $recipient->status !== 'queued' || ! $recipient->campaign) {
            return;
        }
        $campaign = $recipient->campaign;
        $client = $recipient->client;

        // The client may have opted out or been removed between pressing Send and this copy's turn.
        if (! $client || $client->bulk_opt_out_at) {
            $recipient->update(['status' => 'skipped', 'error' => $client ? 'The client asked not to receive bulk messages.' : 'The client was deleted.']);
            $campaign->refreshStatus();

            return;
        }

        try {
            if ($campaign->channel === 'whatsapp') {
                $result = WhatsApp::send($recipient->recipient, Campaign::personalise($campaign->body, $client),
                    filled($campaign->whatsapp_template) ? ['template' => $campaign->whatsapp_template, 'values' => [Campaign::personalise('{contact}', $client)]] : []);
                $recipient->update($result['ok'] ? ['status' => 'sent', 'sent_at' => now(), 'error' => null] : ['status' => 'failed', 'error' => $result['error']]);
            } else {
                Mail::to($recipient->recipient)->send(CampaignMail::for($campaign, $client));
                $recipient->update(['status' => 'sent', 'sent_at' => now(), 'error' => null]);
            }
        } catch (\Throwable $e) {
            $recipient->update(['status' => 'failed', 'error' => mb_substr($e->getMessage(), 0, 500)]);
        }
        $campaign->refreshStatus();
    }
}
