<?php

namespace App\Livewire\Work;

use App\Models\Invoice;
use App\Models\Project;
use App\Models\TimeEntry;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * "Bill hours" on a project page: turns the billable hours logged on the project that have not been
 * invoiced yet into a draft invoice, at an hourly rate. The hours are then marked as billed so they
 * cannot be charged twice. Finance only.
 */
class BillHours extends Component
{
    #[Locked]
    public int $projectId;

    public bool $showForm = false;

    public string $from = '';

    public string $to = '';

    public string $rate = '';

    /** total = one line for all the hours; person = one line per person */
    public string $lines = 'total';

    public function mount(int $projectId): void
    {
        $this->projectId = $projectId;
    }

    public function booted(): void
    {
        Gate::authorize('finance');
    }

    protected function project(): Project
    {
        return Project::with('client')->findOrFail($this->projectId);
    }

    /** Billable, finished, not yet on an invoice, inside the chosen dates. */
    protected function entries()
    {
        return TimeEntry::query()->finished()->where('project_id', $this->projectId)->where('is_billable', true)->whereNull('invoice_id')->where('minutes', '>', 0)
            ->when(preg_match('/^\d{4}-\d{2}-\d{2}$/', $this->from), fn ($q) => $q->where('work_date', '>=', $this->from))
            ->when(preg_match('/^\d{4}-\d{2}-\d{2}$/', $this->to), fn ($q) => $q->where('work_date', '<=', $this->to));
    }

    public function open(): void
    {
        $this->resetValidation();
        $project = $this->project();
        $this->rate = $project->hourly_rate !== null ? (string) (float) $project->hourly_rate : '';
        $this->from = (string) $this->entries()->min('work_date');
        $this->from = $this->from ? substr($this->from, 0, 10) : '';
        $this->to = today_local()->toDateString();
        $this->showForm = true;
    }

    public function closeModal(): void
    {
        $this->showForm = false;
    }

    public function createInvoice()
    {
        $this->validate([
            'rate' => ['required', 'numeric', 'gt:0', 'max:9999999'],
            'from' => ['nullable', 'date'], 'to' => ['nullable', 'date', ...($this->from !== '' ? ['after_or_equal:from'] : [])],
            'lines' => ['required', 'in:total,person'],
        ], [], ['rate' => 'hourly rate', 'from' => 'first day', 'to' => 'last day']);

        $project = $this->project();
        $invoice = DB::transaction(function () use ($project) {
            $entries = $this->entries()->with('user')->lockForUpdate()->get();
            if ($entries->isEmpty()) {
                return null;
            }
            $today = today_local();
            $invoice = Invoice::create([
                'number' => Invoice::nextNumber(), 'client_id' => $project->client_id,
                'invoice_date' => $today->toDateString(), 'planned_send_date' => $today->toDateString(),
                'due_date' => $today->copy()->addDays((int) setting('invoice_due_days', 15))->toDateString(),
                'currency' => setting('currency', 'INR'), 'tax_rate' => (float) setting('invoice_default_tax_rate', 0),
                'status' => 'draft', 'created_by' => auth()->id(),
            ]);
            $groups = $this->lines === 'person' ? $entries->groupBy(fn ($e) => $e->user?->name ?? 'Team') : collect(['' => $entries]);
            $position = 0;
            foreach ($groups as $name => $group) {
                // Hours to two decimals, so 1h 20m is billed as 1.33.
                $hours = round($group->sum('minutes') / 60, 2);
                $period = fmt_date($group->min('work_date')).($group->min('work_date') != $group->max('work_date') ? ' to '.fmt_date($group->max('work_date')) : '');
                $invoice->items()->create([
                    'description' => mb_substr($project->name.': '.($name !== '' ? $name.', ' : '').'hours worked '.$period, 0, 500),
                    'hsn_sac' => setting('invoice_default_hsn') ?: null, 'quantity' => $hours, 'unit_price' => (float) $this->rate,
                    'amount' => round($hours * (float) $this->rate, 2), 'sort_order' => $position++,
                ]);
            }
            $invoice->recalculateTotals();
            TimeEntry::whereIn('id', $entries->pluck('id'))->update(['invoice_id' => $invoice->id]);
            // Remember the rate for next time.
            $project->update(['hourly_rate' => (float) $this->rate]);

            return $invoice;
        });

        if (! $invoice) {
            $this->addError('from', 'There are no unbilled billable hours in these dates.');

            return null;
        }
        session()->flash('status', 'Draft invoice '.$invoice->number.' created from the tracked hours. Check it and send it.');

        return $this->redirect(route('invoices.edit', $invoice));
    }

    public function render()
    {
        $unbilled = (int) TimeEntry::query()->finished()->where('project_id', $this->projectId)->where('is_billable', true)->whereNull('invoice_id')->sum('minutes');
        $byPerson = $this->showForm
            ? $this->entries()->toBase()->join('users', 'users.id', '=', 'time_entries.user_id')
                ->selectRaw('users.name as name, sum(time_entries.minutes) as minutes')->groupBy('users.id', 'users.name')->orderByDesc('minutes')->get()
            : collect();
        $minutes = (int) $byPerson->sum('minutes');

        return view('livewire.work.bill-hours', [
            'unbilled' => $unbilled,
            'billed' => (int) TimeEntry::where('project_id', $this->projectId)->whereNotNull('invoice_id')->sum('minutes'),
            'byPerson' => $byPerson, 'minutes' => $minutes,
            'amount' => round(round($minutes / 60, 2) * (float) ($this->rate ?: 0), 2),
        ]);
    }
}
