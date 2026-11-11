<?php

namespace App\Livewire\Expenses;

use App\Models\Client;
use App\Models\Expense;
use App\Models\Lookup;
use App\Support\ExpenseFilters;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithFileUploads;
use Livewire\WithPagination;

#[Title('Expenses')]
class Index extends Component
{
    use WithFileUploads, WithPagination;

    /** all | recurring */
    #[Url(except: 'all')]
    public string $view = 'all';

    #[Url(except: '')]
    public string $search = '';

    #[Url(except: '')]
    public string $category = '';

    #[Url(except: '')]
    public string $client = '';

    #[Url(except: '')]
    public string $method = '';

    #[Url]
    public string $from = '';

    #[Url]
    public string $to = '';

    public bool $showForm = false;

    public ?int $editingId = null;

    public array $form = [];

    public $receipt;

    public function boot(): void
    {
        Gate::authorize('finance');
    }

    public function mount(): void
    {
        // Default to the current month.
        if ($this->from === '' && $this->to === '') {
            $this->from = today_local()->startOfMonth()->toDateString();
            $this->to = today_local()->endOfMonth()->toDateString();
        }
        $this->resetForm();
        $this->showForm = request()->boolean('add');
    }

    public function updated($property): void
    {
        if (! str_starts_with($property, 'form') && $property !== 'receipt') {
            $this->resetPage();
        }
    }

    public function setRange(string $range): void
    {
        $today = today_local();
        [$this->from, $this->to] = match ($range) {
            'today' => [$today->toDateString(), $today->toDateString()],
            'last_month' => [$today->copy()->subMonthNoOverflow()->startOfMonth()->toDateString(), $today->copy()->subMonthNoOverflow()->endOfMonth()->toDateString()],
            'year' => [$today->copy()->startOfYear()->toDateString(), $today->copy()->endOfYear()->toDateString()],
            'all' => ['', ''],
            default => [$today->copy()->startOfMonth()->toDateString(), $today->copy()->endOfMonth()->toDateString()],
        };
        $this->resetPage();
    }

    public function filters(): array
    {
        return [
            'search' => $this->search, 'category' => $this->category, 'client' => $this->client, 'method' => $this->method,
            'from' => $this->from, 'to' => $this->to, 'recurring' => $this->view === 'recurring' ? '1' : null,
        ];
    }

    protected function resetForm(): void
    {
        $this->form = [
            'expense_date' => today_local()->toDateString(), 'amount' => '', 'category_id' => '', 'payee' => '',
            'payment_method_id' => '', 'description' => '', 'client_id' => '', 'is_recurring' => false, 'recurring_frequency' => 'monthly',
        ];
        $this->editingId = null;
        $this->receipt = null;
    }

    public function create(): void
    {
        $this->resetForm();
        $this->resetValidation();
        $this->showForm = true;
    }

    public function edit(int $id): void
    {
        $expense = Expense::findOrFail($id);
        $this->resetForm();
        $this->resetValidation();
        $this->editingId = $expense->id;
        $this->form = [
            'expense_date' => $expense->expense_date->format('Y-m-d'), 'amount' => (string) (float) $expense->amount,
            'category_id' => (string) ($expense->category_id ?? ''), 'payee' => (string) $expense->payee,
            'payment_method_id' => (string) ($expense->payment_method_id ?? ''), 'description' => (string) $expense->description,
            'client_id' => (string) ($expense->client_id ?? ''), 'is_recurring' => $expense->is_recurring,
            'recurring_frequency' => $expense->recurring_frequency ?: 'monthly',
        ];
        $this->showForm = true;
    }

    public function save(): void
    {
        $this->validate([
            'form.expense_date' => ['required', 'date'],
            'form.amount' => ['required', 'numeric', 'gt:0', 'max:99999999'],
            'form.category_id' => ['required', Rule::exists('lookups', 'id')->where('type', 'expense_category')],
            'form.payee' => ['nullable', 'string', 'max:255'],
            'form.payment_method_id' => ['nullable', Rule::exists('lookups', 'id')->where('type', 'payment_method')],
            'form.description' => ['nullable', 'string', 'max:2000'],
            'form.client_id' => ['nullable', 'exists:clients,id'],
            'form.is_recurring' => ['boolean'],
            'form.recurring_frequency' => ['nullable', Rule::in(array_keys(Expense::FREQUENCIES))],
            'receipt' => ['nullable', 'file', 'max:8192', 'mimes:pdf,png,jpg,jpeg,webp'],
        ], [], [
            'form.expense_date' => 'date', 'form.amount' => 'amount', 'form.category_id' => 'category',
            'form.payment_method_id' => 'payment method', 'form.client_id' => 'client',
        ]);

        $data = collect($this->form)->map(fn ($v) => $v === '' ? null : $v)->all();
        $data['is_recurring'] = (bool) $data['is_recurring'];
        $data['recurring_frequency'] = $data['is_recurring'] ? $data['recurring_frequency'] : null;

        $expense = $this->editingId ? Expense::findOrFail($this->editingId) : new Expense(['created_by' => auth()->id()]);

        if ($this->receipt) {
            if ($expense->receipt_path) {
                Storage::disk('local')->delete($expense->receipt_path);
            }
            $data['receipt_path'] = $this->receipt->store('receipts', 'local');
            $data['receipt_name'] = $this->receipt->getClientOriginalName();
        }

        $expense->fill($data)->save();

        $this->showForm = false;
        $this->resetForm();
        $this->dispatch('toast', message: 'Expense saved.');
    }

    public function delete(int $id): void
    {
        $expense = Expense::findOrFail($id);
        if ($expense->receipt_path) {
            Storage::disk('local')->delete($expense->receipt_path);
        }
        $expense->delete();
        $this->dispatch('toast', message: 'Expense deleted.');
    }

    public function closeModal(): void
    {
        $this->showForm = false;
    }

    public function render()
    {
        $today = today_local();
        $filtered = fn () => ExpenseFilters::apply(Expense::query(), $this->filters());

        $breakdown = $filtered()->toBase()
            ->leftJoin('lookups', 'lookups.id', '=', 'expenses.category_id')
            ->select(DB::raw("coalesce(lookups.name, 'Uncategorised') as name"), DB::raw('sum(expenses.amount) as total'), DB::raw('count(*) as entries'))
            ->groupBy('name')->orderByDesc('total')->get();

        return view('livewire.expenses.index', [
            'expenses' => $filtered()->with(['category', 'method', 'client'])->latest('expense_date')->latest('id')->paginate(25),
            'filteredTotal' => (float) $filtered()->sum('amount'),
            'todayTotal' => (float) Expense::whereDate('expense_date', $today->toDateString())->sum('amount'),
            'monthTotal' => (float) Expense::whereBetween('expense_date', [$today->copy()->startOfMonth()->toDateString(), $today->copy()->endOfMonth()->toDateString()])->sum('amount'),
            'breakdown' => $breakdown,
            'daily' => $filtered()->toBase()->select('expense_date', DB::raw('sum(amount) as total'), DB::raw('count(*) as entries'))
                ->groupBy('expense_date')->orderByDesc('expense_date')->limit(31)->get(),
            'monthly' => Expense::query()->toBase()
                ->select(DB::raw("date_format(expense_date, '%Y-%m') as month"), DB::raw('sum(amount) as total'))
                ->where('expense_date', '>=', $today->copy()->subMonthsNoOverflow(5)->startOfMonth()->toDateString())
                ->groupBy('month')->orderByDesc('month')->get(),
            'recurringMonthly' => (float) Expense::where('is_recurring', true)->where('recurring_frequency', 'monthly')
                ->whereBetween('expense_date', [$today->copy()->startOfMonth()->toDateString(), $today->copy()->endOfMonth()->toDateString()])->sum('amount'),
            'categories' => Lookup::options('expense_category', $this->form['category_id'] ?? null),
            'allCategories' => Lookup::ofType('expense_category')->get(),
            'methods' => Lookup::options('payment_method', $this->form['payment_method_id'] ?? null),
            'clients' => Client::orderBy('name')->get(['id', 'name']),
        ]);
    }
}
