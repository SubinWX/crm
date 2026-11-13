<?php

namespace App\Livewire\Campaigns;

use App\Jobs\SendCampaignMessage;
use App\Models\Campaign;
use App\Models\CampaignRecipient;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/** Bulk Messages: every campaign sent, with how many clients got it, and the result for each client. Super Admins only. */
#[Title('Bulk Messages')]
class Index extends Component
{
    use WithPagination;

    /** The campaign whose recipients are open. */
    #[Url(as: 'open', except: null)]
    public ?int $openId = null;

    public function booted(): void
    {
        Gate::authorize('admin');
    }

    public function show(?int $id): void
    {
        $this->openId = $id;
    }

    public function closeModal(): void
    {
        $this->openId = null;
    }

    /** Try again for the clients whose copy failed (for example after fixing the email server). */
    public function retryFailed(int $id): void
    {
        $campaign = Campaign::findOrFail($id);
        abort_unless(Campaign::channelReady($campaign->channel), 422);
        $failed = $campaign->recipients()->where('status', 'failed')->pluck('id');
        if ($failed->isEmpty()) {
            return;
        }
        CampaignRecipient::whereIn('id', $failed)->update(['status' => 'queued', 'error' => null]);
        $campaign->update(['status' => 'sending']);
        $failed->each(fn ($recipientId) => SendCampaignMessage::dispatch($recipientId));
        $this->dispatch('toast', message: 'Sending again to '.$failed->count().' '.($failed->count() === 1 ? 'client' : 'clients').'.');
    }

    public function render()
    {
        $counts = fn ($status) => fn ($q) => $q->where('status', $status);
        $open = $this->openId ? Campaign::with(['recipients' => fn ($q) => $q->orderByRaw("field(status, 'failed', 'skipped', 'queued', 'sent')")->orderBy('name')])->find($this->openId) : null;

        return view('livewire.campaigns.index', [
            'campaigns' => Campaign::with('creator')->withCount([
                'recipients as sent_count' => $counts('sent'), 'recipients as failed_count' => $counts('failed'),
                'recipients as skipped_count' => $counts('skipped'), 'recipients as queued_count' => $counts('queued'),
            ])->latest('id')->paginate(20),
            'open' => $open,
            // While anything is still going out the page refreshes itself.
            'sending' => Campaign::where('status', 'sending')->exists(),
        ]);
    }
}
