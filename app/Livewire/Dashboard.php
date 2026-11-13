<?php

namespace App\Livewire;

use App\Livewire\Activity\Index as ActivityIndex;
use App\Models\ActivityLog;
use App\Models\Client;
use App\Models\Equipment;
use App\Models\Expense;
use App\Models\HostingRecord;
use App\Models\Invoice;
use App\Models\JobApplication;
use App\Models\JobVacancy;
use App\Models\Lead;
use App\Models\Quote;
use App\Models\Task;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Title('Dashboard')]
class Dashboard extends Component
{
    public function render()
    {
        // Sub Admins get a small dashboard limited to the clients they added.
        if (auth()->user()->isSubAdmin()) {
            $mine = fn () => Client::query()->visibleTo(auth()->user());

            return view('livewire.dashboard-sub', [
                'total' => $mine()->count(),
                'thisMonth' => $mine()->where('created_at', '>=', today_local()->startOfMonth()->utc())->count(),
                'recent' => $mine()->latest()->latest('id')->limit(8)->get(),
                'equipment' => Equipment::where('user_id', auth()->id())->count(),
            ]);
        }

        $today = today_local();
        $todayStr = $today->toDateString();
        $weekEnd = $today->copy()->addDays(7)->toDateString();
        $twoMonths = $today->copy()->addMonthsNoOverflow(2)->toDateString();
        $monthStart = $today->copy()->startOfMonth();
        $monthEnd = $today->copy()->endOfMonth();
        // created_at is stored in UTC; convert the local month boundaries.
        $monthStartUtc = $monthStart->copy()->utc();

        $canClients = Gate::allows('clients.view');
        $canFinance = Gate::allows('finance');
        $canHosting = Gate::allows('hosting.view');
        $data = ['canClients' => $canClients, 'canHosting' => $canHosting, 'canFinance' => $canFinance, 'canAdmin' => Gate::allows('admin'), 'today' => $today];

        if ($canClients) {
            $data['clientsThisMonth'] = Client::where('created_at', '>=', $monthStartUtc)->count();
            $data['activeClients'] = Client::notArchived()->where('status', 'active')->count();
            $data['recentClients'] = Client::with(['accountManager', 'creator'])->latest()->latest('id')->limit(6)->get();

            // Clients added per month, last six months, in the business time zone.
            $months = collect(range(5, 0))->map(fn ($i) => $today->copy()->startOfMonth()->subMonthsNoOverflow($i));
            $created = Client::where('created_at', '>=', $months->first()->copy()->utc())->pluck('created_at')
                ->countBy(fn ($d) => Carbon::parse($d)->timezone(crm_tz())->format('Y-m'));
            $data['monthlyClients'] = $months->map(fn ($m) => ['label' => $m->format('M Y'), 'count' => $created[$m->format('Y-m')] ?? 0]);

        }

        if ($canHosting) {
            $hosting = fn () => HostingRecord::query()->remindable()->with(['client', 'serverProvider'])->orderBy('next_payment_date');
            $data['hostingOverdue'] = $hosting()->where('next_payment_date', '<', $todayStr)->get();
            $data['hostingWeek'] = $hosting()->whereBetween('next_payment_date', [$todayStr, $weekEnd])->get();
            $data['hostingTwoMonths'] = $hosting()->where('next_payment_date', '>', $weekEnd)->where('next_payment_date', '<=', $twoMonths)->get();
            $data['hostingNext'] = $hosting()->limit(6)->get();
        }

        if ($canFinance) {
            $open = Invoice::open();
            $data['outstanding'] = (float) (clone $open)->sum('total') - (float) (clone $open)->sum('amount_paid');
            $data['openInvoices'] = (clone $open)->count();
            $data['overdueInvoices'] = Invoice::overdue()->count();
            $data['scheduledInvoices'] = Invoice::with('client')->where('status', 'draft')->whereNotNull('planned_send_date')
                ->orderBy('planned_send_date')->limit(8)->get();
            $data['monthExpenses'] = (float) Expense::whereBetween('expense_date', [$monthStart->toDateString(), $monthEnd->toDateString()])->sum('amount');
        }

        $data['tasksThisMonth'] = Task::with(['client', 'lead', 'assignees'])->open()
            ->whereBetween('due_date', [$todayStr, $monthEnd->toDateString()])->orderBy('due_date')->limit(8)->get();
        $data['tasksOverdue'] = Task::with(['client', 'lead', 'assignees'])->open()->where('due_date', '<', $todayStr)->orderBy('due_date')->limit(8)->get();

        $data['canSales'] = Gate::allows('sales.manage');
        if ($data['canSales']) {
            $data['quotesAwaiting'] = Quote::with(['client', 'lead'])->awaitingResponse()->orderBy('expiry_date')->limit(6)->get();
            $data['quotesAwaitingCount'] = Quote::awaitingResponse()->count();
            $data['openLeads'] = Lead::open()->count();
        }
        if ($data['canAdmin']) {
            $data['recentActivity'] = ActivityLog::with('user')->whereIn('action', ActivityIndex::AUTH_ACTIONS)
                ->orWhere('subject_type', 'user')->latest('created_at')->latest('id')->limit(8)->get();
        }

        if ($data['canCareers'] = Gate::allows('careers.manage')) {
            $counts = JobApplication::query()->toBase()->selectRaw('status, count(*) as total')->groupBy('status')->pluck('total', 'status');
            $total = (int) $counts->sum();
            // Slices in pipeline order, each with where it starts on the circle (as a percentage).
            $offset = 0;
            $data['careers'] = [
                'total' => $total,
                'slices' => collect(JobApplication::STATUSES)->map(function ($label, $status) use ($counts, $total, &$offset) {
                    $count = (int) ($counts[$status] ?? 0);
                    $slice = ['status' => $status, 'label' => $label, 'count' => $count, 'percent' => $total ? round($count / $total * 100, 1) : 0, 'offset' => $offset, 'color' => JobApplication::STATUS_COLORS[$status]];
                    $offset += $slice['percent'];

                    return $slice;
                })->values(),
                'openVacancies' => JobVacancy::accepting()->count(),
                'thisMonth' => JobApplication::where('created_at', '>=', $monthStartUtc)->count(),
            ];
        }

        return view('livewire.dashboard', $data);
    }
}
