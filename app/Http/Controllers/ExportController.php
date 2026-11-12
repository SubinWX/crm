<?php

namespace App\Http\Controllers;

use App\Models\Client;
use App\Models\Invoice;
use App\Models\JobApplication;
use App\Models\Lead;
use App\Models\TimeEntry;
use App\Models\User;
use App\Support\ApplicationFilters;
use App\Support\AttendanceSheet;
use App\Support\ClientFilters;
use App\Support\Gst;
use App\Support\GstReport;
use App\Support\LeadFilters;
use App\Support\TimeFilters;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

/** CSV exports of the filtered Clients and Leads lists. Routes carry the permission checks. */
class ExportController extends Controller
{
    public function clients(Request $request)
    {
        $query = ClientFilters::apply(
            Client::query()->with(['industry', 'workTypes', 'accountManager', 'acquisitionSource', 'referrer']),
            $request->only(['search', 'status', 'industry', 'workType', 'source', 'manager', 'archived']),
            $request->user()
        )->orderBy('name');

        activity('exported', 'Clients exported to CSV');

        return $this->csv('clients', ['Name', 'Primary contact', 'Email', 'Phone 1', 'Phone 2', 'Website', 'Location', 'Billing address', 'Industry', 'Work types', 'Client By', 'Referred By', 'Account manager', 'Status', 'Tax ID / GSTIN', 'Join date', 'Notes'],
            $query, fn (Client $c) => [
                $c->name, $c->contact_name, $c->email, $c->phone1, $c->phone2, $c->website, $c->location, $c->billing_address,
                $c->industry?->name, $c->workTypes->pluck('name')->implode('; '), $c->acquisitionSource?->name, $c->referrer?->name,
                $c->accountManager?->name, $c->isArchived() ? 'Archived' : (Client::STATUSES[$c->status] ?? $c->status), $c->tax_id, $c->join_date?->format('Y-m-d'), plain_text($c->notes),
            ]);
    }

    public function leads(Request $request)
    {
        $query = LeadFilters::apply(Lead::query()->with(['owner', 'source', 'services']), $request->only(['search', 'stage', 'owner', 'source']))->orderBy('name');

        activity('exported', 'Leads exported to CSV');

        return $this->csv('leads', ['Name', 'Contact name', 'Email', 'Phone', 'Website', 'Location', 'Source', 'Services requested', 'Estimated value', 'Owner', 'Stage', 'Next action', 'Next action due', 'Notes'],
            $query, fn (Lead $l) => [
                $l->name, $l->contact_name, $l->email, $l->phone, $l->website, $l->location, $l->source?->name, $l->services->pluck('name')->implode('; '),
                $l->estimated_value !== null ? number_format((float) $l->estimated_value, 2, '.', '') : null, $l->owner?->name,
                Lead::STAGES[$l->stage] ?? $l->stage, $l->next_action, $l->next_action_date?->format('Y-m-d'), plain_text($l->notes),
            ]);
    }

    public function applications(Request $request)
    {
        $query = ApplicationFilters::sort(
            ApplicationFilters::apply(JobApplication::query(), $request->only(['search', 'vacancy', 'status', 'from', 'to'])),
            $request->string('sort')->toString()
        );

        activity('exported', 'Job applications exported to CSV');

        return $this->csv('job-applications', ['Applied on', 'Name', 'Email', 'Phone', 'Applied for', 'Location', 'Experience (years)', 'Current company',
            'Current salary', 'Expected salary', 'Notice period', 'LinkedIn', 'Portfolio', 'Status', 'Rating', 'Interview', 'Notes', 'Message'],
            $query, fn (JobApplication $a) => [
                $a->created_at->timezone(crm_tz())->format('Y-m-d H:i'), $a->name, $a->email, $a->phone, $a->vacancy_title, $a->location,
                $a->experience_years !== null ? (float) $a->experience_years : null, $a->current_company, $a->current_salary, $a->expected_salary,
                $a->notice_period, $a->linkedin_url, $a->portfolio_url, JobApplication::STATUSES[$a->status] ?? $a->status, $a->rating,
                $a->interview_at?->timezone(crm_tz())->format('Y-m-d H:i'), $a->notes, $a->cover_letter,
            ]);
    }

    /** GST Summary downloads for one month: the invoice register, or the HSN/SAC summary. */
    public function gst(Request $request)
    {
        $month = (string) $request->query('month');
        abort_unless(GstReport::validMonth($month), 404);
        $report = new GstReport($month);
        $number = fn ($value) => number_format((float) $value, 2, '.', '');
        $hsn = $request->query('type') === 'hsn';
        $rows = $hsn
            ? $report->byHsn()->map(fn ($h) => [$h['hsn'], $h['rate'], $h['quantity'], $number($h['taxable']), $number($h['cgst']), $number($h['sgst']), $number($h['igst']), $number($h['tax'])])
            : $report->invoices->map(fn (Invoice $i) => [
                $i->invoice_date->format('d-m-Y'), $i->number, $i->client->name, $i->client_gstin, filled($i->client_gstin) ? 'B2B' : 'B2C',
                $i->place_of_supply ? $i->place_of_supply.'-'.(Gst::STATES[$i->place_of_supply] ?? '') : null, (float) $i->tax_rate,
                $number($i->taxable_value), $number($i->cgst_amount), $number($i->sgst_amount), $number($i->igst_amount), $number($i->tax_amount), $number($i->total),
                $i->items->pluck('hsn_sac')->filter()->unique()->implode('; '),
            ]);

        activity('exported', 'GST '.($hsn ? 'HSN summary' : 'invoice register')." for {$month} exported to CSV");

        return response()->streamDownload(function () use ($rows, $hsn) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, $hsn
                ? ['HSN/SAC', 'Rate %', 'Quantity', 'Taxable value', 'CGST', 'SGST', 'IGST', 'Total tax']
                : ['Invoice date', 'Invoice number', 'Client', 'Client GSTIN', 'Type', 'Place of supply', 'Rate %', 'Taxable value', 'CGST', 'SGST', 'IGST', 'Total tax', 'Invoice value', 'HSN/SAC']);
            foreach ($rows as $row) {
                // Stop spreadsheet apps treating text as a formula.
                fputcsv($out, array_map(fn ($v) => is_string($v) && preg_match('/^[=+\-@\t\r]/', $v) ? "'".$v : $v, $row));
            }
            fclose($out);
        }, 'gst-'.($hsn ? 'hsn-summary' : 'invoice-register').'-'.$month.'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /**
     * Time entries as a spreadsheet: the Overview's date range and filters, or one week (?week=).
     * Everyone can export their own time; people with "team.manage" can export anyone's or everyone's.
     */
    public function timesheet(Request $request)
    {
        $manager = $request->user()->can('team.manage');
        $person = (string) $request->query('person');
        $userId = $manager ? ($person === 'all' ? null : (ctype_digit($person) ? (int) $person : $request->user()->id)) : $request->user()->id;

        $filters = $request->only(['from', 'to', 'task', 'client', 'project']);
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $request->query('week'))) {
            $start = Carbon::parse($request->query('week'))->startOfWeek(Carbon::MONDAY);
            $filters = ['from' => $start->toDateString(), 'to' => $start->copy()->addDays(6)->toDateString()] + $filters;
        }
        $query = TimeFilters::apply(TimeEntry::query()->finished()->with(['user', 'project.client']), $filters, $userId)->orderBy('work_date')->orderBy('id');

        activity('exported', 'Time entries exported to CSV');

        return $this->csv('time-entries', ['Date', 'Person', 'Client', 'Project', 'Task', 'Start', 'End', 'Duration', 'Hours', 'Billable'], $query, fn (TimeEntry $e) => [
            $e->work_date->format('Y-m-d'), $e->user?->name, $e->project?->client?->name, $e->project?->name, $e->description,
            $e->started_at?->timezone(crm_tz())->format('H:i'), $e->ended_at?->timezone(crm_tz())->format('H:i'),
            TimeEntry::clock($e->seconds()), number_format($e->minutes / 60, 2, '.', ''), $e->is_billable ? 'yes' : 'no',
        ]);
    }

    /** The team's attendance sheet for one month: one row per person, one column per day, then totals. */
    public function attendance(Request $request)
    {
        $month = (string) $request->query('month');
        abort_unless(AttendanceSheet::validMonth($month), 404);
        $users = User::active()->team()->orderBy('name')->get();
        $sheet = new AttendanceSheet($month, $users);

        activity('exported', "Attendance sheet for {$month} exported to CSV");

        return response()->streamDownload(function () use ($sheet, $users) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, ['Person', ...$sheet->days->map(fn ($d) => $d->format('d D'))->all(), 'Present', 'Absent', 'Leave', 'Not marked', 'Working days']);
            foreach ($users as $user) {
                $t = $sheet->totals[$user->id];
                fputcsv($out, [$user->name, ...$sheet->days->map(fn ($d) => $sheet->cells[$user->id][$d->toDateString()]['code'])->all(), $t['present'], $t['absent'], $t['leave'], $t['unmarked'], $t['working']]);
            }
            fputcsv($out, []);
            fputcsv($out, ['P = present, HD = half day, WFH = work from home, A = absent, L = leave, HL = half-day leave, H = holiday, W = weekly off, ? = not marked']);
            fclose($out);
        }, 'attendance-'.$month.'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    protected function csv(string $name, array $headings, $query, callable $row)
    {
        return response()->streamDownload(function () use ($headings, $query, $row) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, $headings);
            $query->chunk(500, function ($records) use ($out, $row) {
                foreach ($records as $record) {
                    // Stop spreadsheet apps treating text as a formula.
                    fputcsv($out, array_map(fn ($v) => is_string($v) && preg_match('/^[=+\-@\t\r]/', $v) ? "'".$v : $v, $row($record)));
                }
            });
            fclose($out);
        }, $name.'-'.today_local()->format('Y-m-d').'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}
