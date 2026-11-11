<?php

namespace App\Livewire\Portal;

use App\Models\SupportTicket;
use App\Notifications\Portal\TicketUpdateForCustomer;
use App\Notifications\SupportTicketActivity;
use App\Support\TicketDesk;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithFileUploads;

#[Layout('components.layouts.portal')]
#[Title('New ticket')]
class TicketCreate extends Component
{
    use WithFileUploads;

    public string $type = 'support';

    public string $subject = '';

    public string $related_work = '';

    public string $priority = 'normal';

    public string $description = '';

    public string $rating = '';

    public array $files = [];

    public function boot(): void
    {
        abort_unless(Auth::guard('portal')->user()?->email_verified_at, 403);
    }

    public function mount(): void
    {
        $type = request()->query('type');
        if (is_string($type) && array_key_exists($type, SupportTicket::TYPES)) {
            $this->type = $type;
        }
    }

    public function removeFile(int $index): void
    {
        unset($this->files[$index]);
        $this->files = array_values($this->files);
    }

    public function submit()
    {
        $user = Auth::guard('portal')->user();

        $this->validate([
            'type' => ['required', Rule::in(array_keys(SupportTicket::TYPES))],
            'subject' => ['required', 'string', 'min:4', 'max:200'],
            'related_work' => ['nullable', 'string', 'max:200'],
            'priority' => ['required', Rule::in(array_keys(SupportTicket::PRIORITIES))],
            'description' => ['required', 'string', 'min:10', 'max:10000'],
            'rating' => ['nullable', 'integer', 'between:1,5'],
            'files' => ['array', 'max:'.TicketDesk::MAX_ATTACHMENTS],
            'files.*' => TicketDesk::ATTACHMENT_RULES,
        ], ['files.max' => 'You can attach up to '.TicketDesk::MAX_ATTACHMENTS.' files.'], ['files.*' => 'attachment', 'related_work' => 'project or service']);

        // Stop one account flooding the desk.
        $key = 'portal-ticket|'.$user->id;
        if (RateLimiter::tooManyAttempts($key, 10)) {
            $this->addError('subject', 'You have submitted several tickets in a short time. Please wait a little before sending another.');

            return null;
        }
        RateLimiter::hit($key, 3600);

        $ticket = DB::transaction(function () use ($user) {
            $ticket = SupportTicket::createWithNumber([
                'portal_user_id' => $user->id,
                'client_id' => $user->client_id,
                'type' => $this->type,
                'subject' => trim($this->subject),
                'related_work' => trim($this->related_work) ?: null,
                'priority' => $this->type === 'feedback' ? 'low' : $this->priority,
                'rating' => $this->type === 'feedback' && $this->rating !== '' ? (int) $this->rating : null,
            ]);
            $message = $ticket->messages()->create(['portal_user_id' => $user->id, 'body' => trim($this->description)]);
            TicketDesk::storeAttachments($message, $this->files);

            return $ticket;
        });

        Notification::send(TicketDesk::team(), new SupportTicketActivity($ticket, 'created'));
        $user->notify(new TicketUpdateForCustomer($ticket, 'received'));

        session()->flash('status', "Thank you. Your ticket number is {$ticket->number}. We have emailed you a confirmation.");

        return $this->redirectRoute('portal.tickets.show', $ticket->number);
    }

    public function render()
    {
        return view('livewire.portal.ticket-create');
    }
}
