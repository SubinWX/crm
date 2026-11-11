<?php

namespace App\Livewire\Support;

use App\Models\Client;
use App\Models\SupportTicket;
use App\Notifications\Portal\TicketUpdateForCustomer;
use App\Support\TicketDesk;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Livewire\Component;
use Livewire\WithFileUploads;

class TicketShow extends Component
{
    use WithFileUploads;

    public SupportTicket $ticket;

    public string $reply = '';

    public bool $internal = false;

    /** Status to set when the reply is sent. */
    public string $replyStatus = 'waiting';

    public array $files = [];

    public function boot(): void
    {
        Gate::authorize('support.manage');
    }

    public function removeFile(int $index): void
    {
        unset($this->files[$index]);
        $this->files = array_values($this->files);
    }

    public function send(): void
    {
        $this->validate([
            'reply' => ['required', 'string', 'min:2', 'max:10000'],
            'replyStatus' => ['required', Rule::in(array_keys(SupportTicket::STATUSES))],
            'files' => ['array', 'max:'.TicketDesk::MAX_ATTACHMENTS],
            'files.*' => TicketDesk::ATTACHMENT_RULES,
        ], [], ['files.*' => 'attachment']);

        DB::transaction(function () {
            $message = $this->ticket->messages()->create([
                'user_id' => auth()->id(), 'body' => trim($this->reply), 'is_internal' => $this->internal,
            ]);
            TicketDesk::storeAttachments($message, $this->files);

            if (! $this->internal) {
                // First public reply takes ownership if nobody has it yet.
                if (! $this->ticket->assigned_to) {
                    $this->ticket->assigned_to = auth()->id();
                }
                $this->ticket->setStatus($this->replyStatus);
            } else {
                $this->ticket->forceFill(['last_activity_at' => now()])->save();
            }
        });

        if (! $this->internal && $this->ticket->portalUser?->is_active) {
            $this->ticket->portalUser->notify(new TicketUpdateForCustomer($this->ticket, 'replied', $this->reply));
        }

        $this->dispatch('toast', message: $this->internal ? 'Internal note added.' : 'Reply sent to the customer.');
        $this->reset(['reply', 'files', 'internal']);
    }

    public function setStatus(string $status): void
    {
        abort_unless(array_key_exists($status, SupportTicket::STATUSES), 422);
        $this->ticket->setStatus($status);
        $this->dispatch('toast', message: 'Status set to '.SupportTicket::STATUSES[$status].'.');
    }

    public function setPriority(string $priority): void
    {
        abort_unless(array_key_exists($priority, SupportTicket::PRIORITIES), 422);
        $this->ticket->update(['priority' => $priority]);
    }

    public function assign(string $userId): void
    {
        $id = $userId === '' ? null : (int) $userId;
        abort_unless($id === null || TicketDesk::team()->contains('id', $id), 422);
        $this->ticket->update(['assigned_to' => $id]);
        $this->dispatch('toast', message: $id ? 'Ticket assigned.' : 'Ticket unassigned.');
    }

    /** Link the ticket (and the customer's portal account) to a CRM client. */
    public function linkClient(string $clientId): void
    {
        $id = $clientId === '' ? null : Client::whereKey($clientId)->value('id');
        $this->ticket->update(['client_id' => $id]);
        if ($id && $this->ticket->portalUser && ! $this->ticket->portalUser->client_id) {
            $this->ticket->portalUser->update(['client_id' => $id]);
        }
    }

    public function render()
    {
        $this->ticket->load(['portalUser.client', 'client', 'assignee']);

        return view('livewire.support.ticket-show', [
            'messages' => $this->ticket->messages()->with(['user', 'portalUser', 'attachments'])->get(),
            'team' => TicketDesk::team()->sortBy('name'),
            'clients' => Client::notArchived()->orderBy('name')->get(['id', 'name']),
            'otherTickets' => SupportTicket::where('portal_user_id', $this->ticket->portal_user_id)
                ->where('id', '!=', $this->ticket->id)->latest('id')->limit(5)->get(),
        ])->title('Ticket '.$this->ticket->number);
    }
}
