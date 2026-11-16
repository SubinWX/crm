<?php

namespace App\Livewire\Finance;

use App\Models\SoftwareSubscription;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

/** Finance → Subscriptions: the tools and services the company pays for, what they cost and when they renew. */
#[Title('Subscriptions')]
class Subscriptions extends Component
{
    #[Url(except: '')]
    public string $search = '';

    /** active | cancelled | all */
    #[Url(except: 'active')]
    public string $status = 'active';

    public bool $showForm = false;

    public ?int $editingId = null;

    public array $form = [];

    /** Checked on every request to this page, not only when it is first opened. */
    public function booted(): void
    {
        Gate::authorize('finance');
    }

    public function edit(?int $id = null): void
    {
        $this->resetValidation();
        $item = $id ? SoftwareSubscription::findOrFail($id) : null;
        $this->editingId = $item?->id;
        $this->form = [
            'name' => $item->name ?? '', 'category' => $item->category ?? '', 'cost' => $item ? (string) (float) $item->cost : '',
            'billing_cycle' => $item->billing_cycle ?? 'monthly', 'renews_on' => $item?->renews_on?->format('Y-m-d') ?? '',
            'owner_id' => (string) ($item->owner_id ?? ''), 'seats' => (string) ($item->seats ?? ''), 'status' => $item->status ?? 'active',
            'url' => $item->url ?? '', 'notes' => $item->notes ?? '',
        ];
        $this->showForm = true;
    }

    public function save(): void
    {
        $this->validate([
            'form.name' => ['required', 'string', 'max:255'],
            'form.category' => ['nullable', 'string', 'max:60'],
            'form.cost' => ['required', 'numeric', 'min:0', 'max:99999999'],
            'form.billing_cycle' => ['required', Rule::in(array_keys(SoftwareSubscription::CYCLES))],
            'form.renews_on' => ['nullable', 'date'],
            'form.owner_id' => ['nullable', 'exists:users,id'],
            'form.seats' => ['nullable', 'integer', 'min:1', 'max:100000'],
            'form.status' => ['required', Rule::in(array_keys(SoftwareSubscription::STATUSES))],
            'form.url' => ['nullable', 'url', 'max:255'],
            'form.notes' => ['nullable', 'string', 'max:2000'],
        ], [], collect($this->form)->keys()->mapWithKeys(fn ($k) => ["form.{$k}" => str_replace(['_id', '_'], ['', ' '], $k)])->all());

        $data = collect($this->form)->map(fn ($v) => $v === '' ? null : $v)->all();
        $this->editingId ? SoftwareSubscription::findOrFail($this->editingId)->update($data) : SoftwareSubscription::create($data);

        $this->closeModal();
        $this->dispatch('toast', message: 'Subscription saved.');
    }

    /** The payment was made: the renewal date moves on by one billing period. */
    public function renewed(int $id): void
    {
        $item = SoftwareSubscription::findOrFail($id);
        if ($item->renews_on) {
            $item->update(['renews_on' => $item->renews_on->copy()->addMonthsNoOverflow(SoftwareSubscription::MONTHS[$item->billing_cycle] ?? 1)]);
            $this->dispatch('toast', message: $item->name.' now renews on '.fmt_date($item->renews_on).'.');
        }
    }

    public function delete(int $id): void
    {
        SoftwareSubscription::findOrFail($id)->delete();
        $this->dispatch('toast', message: 'Subscription deleted.');
    }

    public function closeModal(): void
    {
        $this->showForm = false;
        $this->editingId = null;
    }

    public function render()
    {
        $active = SoftwareSubscription::where('status', 'active')->get();
        $monthly = $active->sum(fn ($s) => $s->monthlyCost());
        $soon = today_local()->addDays(30)->toDateString();

        return view('livewire.finance.subscriptions', [
            'items' => SoftwareSubscription::with('owner')
                ->when(in_array($this->status, ['active', 'cancelled'], true), fn ($q) => $q->where('status', $this->status))
                ->when($this->search, fn ($q, $s) => $q->where(fn ($q) => $q->where('name', 'like', "%{$s}%")->orWhere('category', 'like', "%{$s}%")))
                ->orderByRaw('renews_on is null')->orderBy('renews_on')->orderBy('name')->get(),
            'monthly' => $monthly, 'yearly' => $monthly * 12, 'activeCount' => $active->count(),
            'renewingSoon' => $active->filter(fn ($s) => $s->renews_on && $s->renews_on->toDateString() <= $soon)->count(),
            'users' => $this->showForm ? User::active()->orderBy('name')->get(['id', 'name']) : collect(),
            'today' => today_local(),
        ]);
    }
}
