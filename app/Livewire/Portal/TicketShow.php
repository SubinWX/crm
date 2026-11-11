<?php

namespace App\Livewire\Portal;

use App\Models\SupportTicket;
use App\Notifications\SupportTicketActivity;
use App\Support\TicketDesk;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Livewire\WithFileUploads;

#[Layout('components.layouts.portal')]
class TicketShow extends Component
{
    use WithFileUploads;

    #[Locked]
    public int $ticketId;

    public string $reply = '';

    public array $files = [];

    public function mount(string $number): void
    {
        // Looked up within the signed-in customer's own tickets, so other people's numbers simply 404.
        $this->ticketId = SupportTicket::where('number', $number)
            ->where('portal_user_id', Auth::guard('portal')->id())->firstOrFail()->id;
    }

    protected function ticket(): SupportTicket
    {
        abort_unless(Auth::guard('portal')->user()?->email_verified_at, 403);

        return SupportTicket::where('portal_user_id', Auth::guard('portal')->id())->findOrFail($this->ticketId);
    }

    public function removeFile(int $index): void
    {
        unset($this->files[$index]);
        $this->files = array_values($this->files);
    }

    public function send(): void
    {
        $ticket = $this->ticket();
        abort_if($ticket->status === 'closed', 422, 'This ticket is closed.');

        $this->validate([
            'reply' => ['required', 'string', 'min:2', 'max:10000'],
            'files' => ['array', 'max:'.TicketDesk::MAX_ATTACHMENTS],
            'files.*' => TicketDesk::ATTACHMENT_RULES,
        ], [], ['files.*' => 'attachment']);

        DB::transaction(function () use ($ticket) {
            $message = $ticket->messages()->create(['portal_user_id' => Auth::guard('portal')->id(), 'body' => trim($this->reply)]);
            TicketDesk::storeAttachments($message, $this->files);
            // A reply from the customer puts the ticket back in the team's queue.
            $ticket->setStatus($ticket->status === 'in_progress' ? 'in_progress' : 'open');
        });

        Notification::send(TicketDesk::recipientsFor($ticket), new SupportTicketActivity($ticket, 'customer_replied'));

        $this->reset(['reply', 'files']);
        $this->dispatch('toast', message: 'Reply sent.');
    }

    public function markResolved(): void
    {
        $ticket = $this->ticket();
        if ($ticket->isActive()) {
            $ticket->setStatus('resolved');
            $this->dispatch('toast', message: 'Ticket marked as resolved. Reply any time to reopen it.');
        }
    }

    public function render()
    {
        $ticket = $this->ticket();

        return view('livewire.portal.ticket-show', [
            'ticket' => $ticket,
            'messages' => $ticket->messages()->where('is_internal', false)->with(['user', 'portalUser', 'attachments'])->get(),
        ])->title($ticket->number);
    }
}
