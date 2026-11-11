<?php

namespace App\Livewire\Support;

use App\Models\PortalUser;
use App\Models\SupportTicket;
use App\Notifications\Portal\TicketUpdateForCustomer;
use App\Support\TicketDesk;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

#[Title('Support Tickets')]
class Tickets extends Component
{
    use WithPagination;

    /** Set when shown inside a client profile: only that client's tickets. */
    #[Locked]
    public ?int $clientId = null;

    public bool $showForm = false;

    public array $form = [];

    #[Url(except: '')]
    public string $search = '';

    /** active | open | in_progress | waiting | resolved | closed | all */
    #[Url(except: 'active')]
    public string $status = 'active';

    #[Url(except: '')]
    public string $type = '';

    #[Url(except: '')]
    public string $priority = '';

    /** '' | me | unassigned | user id */
    #[Url(except: '')]
    public string $assignee = '';

    public function boot(): void
    {
        Gate::authorize('support.manage');
    }

    public function mount(?int $clientId = null): void
    {
        $this->clientId = $clientId;
        $this->resetForm();
    }

    public function updated($property): void
    {
        if (! str_starts_with($property, 'form')) {
            $this->resetPage();
        }
    }

    protected function resetForm(): void
    {
        $this->form = ['portal_user_id' => '', 'type' => 'support', 'subject' => '', 'related_work' => '', 'priority' => 'normal', 'description' => ''];
    }

    /** Log a request that reached the team by phone, email or WhatsApp as a ticket for that customer. */
    public function create(): void
    {
        $this->resetForm();
        $this->resetValidation();
        $this->showForm = true;
    }

    public function save()
    {
        $this->validate([
            'form.portal_user_id' => ['required', 'exists:portal_users,id'],
            'form.type' => ['required', Rule::in(array_keys(SupportTicket::TYPES))],
            'form.subject' => ['required', 'string', 'min:4', 'max:200'],
            'form.related_work' => ['nullable', 'string', 'max:200'],
            'form.priority' => ['required', Rule::in(array_keys(SupportTicket::PRIORITIES))],
            'form.description' => ['required', 'string', 'min:5', 'max:10000'],
        ], [], ['form.portal_user_id' => 'customer', 'form.subject' => 'subject', 'form.description' => 'details', 'form.related_work' => 'project or service']);

        $customer = PortalUser::findOrFail($this->form['portal_user_id']);
        $ticket = DB::transaction(function () use ($customer) {
            $ticket = SupportTicket::createWithNumber([
                'portal_user_id' => $customer->id,
                'client_id' => $this->clientId ?? $customer->client_id,
                'type' => $this->form['type'], 'subject' => trim($this->form['subject']),
                'related_work' => trim($this->form['related_work']) ?: null, 'priority' => $this->form['priority'],
                'assigned_to' => auth()->id(),
            ]);
            // Recorded as the customer's request, with a note saying who logged it.
            $ticket->messages()->create(['portal_user_id' => $customer->id, 'body' => trim($this->form['description'])]);
            $ticket->messages()->create(['user_id' => auth()->id(), 'is_internal' => true, 'body' => 'Logged by '.auth()->user()->name.' on behalf of the customer.']);

            return $ticket;
        });

        if ($customer->is_active) {
            $customer->notify(new TicketUpdateForCustomer($ticket, 'received'));
        }
        session()->flash('status', "Ticket {$ticket->number} created for {$customer->name}. They have been emailed the ticket number.");

        return $this->redirectRoute('support.tickets.show', $ticket);
    }

    public function closeModal(): void
    {
        $this->showForm = false;
    }

    public function render()
    {
        $tickets = SupportTicket::query()->with(['portalUser', 'client', 'assignee'])
            ->when($this->clientId, fn ($q) => $q->where('client_id', $this->clientId))
            ->when($this->status === 'active', fn ($q) => $q->active())
            ->when(array_key_exists($this->status, SupportTicket::STATUSES), fn ($q) => $q->where('status', $this->status))
            ->when($this->type, fn ($q, $v) => $q->where('type', $v))
            ->when($this->priority, fn ($q, $v) => $q->where('priority', $v))
            ->when($this->assignee === 'me', fn ($q) => $q->where('assigned_to', auth()->id()))
            ->when($this->assignee === 'unassigned', fn ($q) => $q->whereNull('assigned_to'))
            ->when(ctype_digit($this->assignee), fn ($q) => $q->where('assigned_to', $this->assignee))
            ->when($this->search, fn ($q, $s) => $q->where(fn ($q) => $q->where('number', 'like', "%{$s}%")
                ->orWhere('subject', 'like', "%{$s}%")->orWhere('related_work', 'like', "%{$s}%")
                ->orWhereHas('portalUser', fn ($q) => $q->where('name', 'like', "%{$s}%")->orWhere('email', 'like', "%{$s}%")->orWhere('company', 'like', "%{$s}%"))))
            // Urgent first, then the ones waiting longest.
            ->orderByRaw("field(priority, 'urgent', 'high', 'normal', 'low')")
            ->orderBy('last_activity_at')
            ->paginate(25);

        return view('livewire.support.tickets', [
            'tickets' => $tickets,
            'counts' => [
                'open' => SupportTicket::where('status', 'open')->count(),
                'in_progress' => SupportTicket::where('status', 'in_progress')->count(),
                'waiting' => SupportTicket::where('status', 'waiting')->count(),
                'unassigned' => SupportTicket::active()->whereNull('assigned_to')->count(),
            ],
            'team' => TicketDesk::team()->sortBy('name'),
            'embedded' => (bool) $this->clientId,
            // Inside a client profile, offer that client's portal users first.
            'customers' => $this->showForm ? PortalUser::query()->where('is_active', true)
                ->when($this->clientId, fn ($q) => $q->orderByRaw('client_id = ? desc', [$this->clientId]))
                ->orderBy('name')->get(['id', 'name', 'email', 'company', 'client_id']) : collect(),
        ]);
    }
}
