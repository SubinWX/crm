<?php

namespace App\Jobs;

use App\Models\MessageLog;
use App\Support\WhatsApp;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/** Sends one recorded WhatsApp message and writes the result back to its log row. Runs once: a failure is kept, not retried blindly. */
class SendWhatsAppMessage implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public function __construct(public int $messageLogId) {}

    public function handle(): void
    {
        $log = MessageLog::find($this->messageLogId);
        if (! $log || $log->status !== 'queued') {
            return;
        }

        $result = WhatsApp::send($log->recipient, $log->body, $log->parameters ?? []);

        $log->update($result['ok']
            ? ['status' => 'sent', 'sent_at' => now(), 'provider_message_id' => $result['id'], 'error' => null]
            : ['status' => 'failed', 'error' => $result['error']]);
    }
}
