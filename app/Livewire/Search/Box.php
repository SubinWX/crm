<?php

namespace App\Livewire\Search;

use App\Models\Client;
use App\Models\Invoice;
use App\Models\Lead;
use App\Models\Project;
use App\Models\Quote;
use App\Models\SupportTicket;
use App\Models\Task;
use Illuminate\Support\Facades\Gate;
use Livewire\Component;

/**
 * The search box in the top bar: one box that finds clients, projects, tasks, invoices, leads,
 * quotes, support tickets and people. Each person only gets results from the areas they can open.
 */
class Box extends Component
{
    protected const PER_GROUP = 5;

    public string $q = '';

    /** @return array<string, array<int, array{title: string, sub: string, url: string}>> */
    protected function results(): array
    {
        $term = trim($this->q);
        if (mb_strlen($term) < 2) {
            return [];
        }
        $like = '%'.str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $term).'%';
        $user = auth()->user();
        $workspace = Gate::allows('workspace');
        $groups = [];

        if (Gate::allows('clients.view')) {
            $groups['Clients'] = Client::query()->visibleTo($user)
                ->where(fn ($q) => $q->where('name', 'like', $like)->orWhere('contact_name', 'like', $like)->orWhere('email', 'like', $like)->orWhere('phone1', 'like', $like)->orWhere('phone2', 'like', $like))
                ->orderByRaw('archived_at is not null')->orderBy('name')->limit(self::PER_GROUP)->get()
                ->map(fn ($c) => ['title' => $c->name, 'sub' => trim(($c->contact_name ?: '').($c->archived_at ? ' · Archived' : ''), ' ·') ?: (Client::STATUSES[$c->status] ?? ''), 'url' => route('clients.show', $c)])->all();
        }
        if ($workspace && Gate::allows('projects.view')) {
            $groups['Projects'] = Project::with('client')->where(fn ($q) => $q->where('name', 'like', $like)->orWhere('next_milestone', 'like', $like))
                ->latest('id')->limit(self::PER_GROUP)->get()
                ->map(fn ($p) => ['title' => $p->name, 'sub' => $p->client?->name ?? '', 'url' => route('projects.show', $p)])->all();
        }
        if (Gate::allows('tasks.view')) {
            $groups['Tasks'] = Task::with('client')->where('title', 'like', $like)->latest('id')->limit(self::PER_GROUP)->get()
                ->map(fn ($t) => ['title' => $t->title, 'sub' => trim(($t->client?->name ?? '').' · '.ucfirst(str_replace('_', ' ', (string) $t->status)), ' ·'), 'url' => route('tasks.index', ['search' => $t->title])])->all();
        }
        if (Gate::allows('finance')) {
            $groups['Invoices'] = Invoice::with('client')->where(fn ($q) => $q->where('number', 'like', $like)->orWhereHas('client', fn ($q) => $q->where('name', 'like', $like)))
                ->latest('id')->limit(self::PER_GROUP)->get()
                ->map(fn ($i) => ['title' => $i->number, 'sub' => trim(($i->client?->name ?? '').' · '.ucfirst(str_replace('_', ' ', (string) $i->status)), ' ·'), 'url' => route('invoices.show', $i)])->all();
        }
        if (Gate::allows('sales.manage')) {
            $groups['Leads'] = Lead::where(fn ($q) => $q->where('name', 'like', $like)->orWhere('contact_name', 'like', $like)->orWhere('email', 'like', $like)->orWhere('phone', 'like', $like))
                ->latest('id')->limit(self::PER_GROUP)->get()
                ->map(fn ($l) => ['title' => $l->name, 'sub' => (string) $l->contact_name, 'url' => route('leads.show', $l)])->all();
            $groups['Quotes'] = Quote::where(fn ($q) => $q->where('number', 'like', $like)->orWhere('title', 'like', $like)->orWhere('recipient_company', 'like', $like)->orWhere('recipient_name', 'like', $like))
                ->latest('id')->limit(self::PER_GROUP)->get()
                ->map(fn ($qu) => ['title' => $qu->number.' · '.$qu->title, 'sub' => (string) ($qu->recipient_company ?: $qu->recipient_name), 'url' => route('quotes.show', $qu)])->all();
        }
        if (Gate::allows('support.manage')) {
            $groups['Support tickets'] = SupportTicket::where(fn ($q) => $q->where('number', 'like', $like)->orWhere('subject', 'like', $like))
                ->latest('id')->limit(self::PER_GROUP)->get()
                ->map(fn ($t) => ['title' => $t->number.' · '.$t->subject, 'sub' => ucfirst(str_replace('_', ' ', (string) $t->status)), 'url' => route('support.tickets.show', $t)])->all();
        }
        // People: whoever the signed-in person may open the profile of.
        $groups['People'] = $user->managedPeople()->where(fn ($q) => $q->where('name', 'like', $like)->orWhere('email', 'like', $like))
            ->orderBy('name')->limit(self::PER_GROUP)->get()
            ->map(fn ($p) => ['title' => $p->name, 'sub' => $p->role_label, 'url' => route('profile.show', $p)])->all();

        return array_filter($groups);
    }

    public function render()
    {
        return view('livewire.search.box', ['groups' => $this->results(), 'searched' => mb_strlen(trim($this->q)) >= 2]);
    }
}
