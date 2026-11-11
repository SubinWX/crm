<?php

namespace App\Http\Controllers;

use App\Models\Client;
use App\Models\Lead;
use App\Support\ClientFilters;
use App\Support\LeadFilters;
use Illuminate\Http\Request;

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
                $c->accountManager?->name, $c->isArchived() ? 'Archived' : (Client::STATUSES[$c->status] ?? $c->status), $c->tax_id, $c->join_date?->format('Y-m-d'), $c->notes,
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
                Lead::STAGES[$l->stage] ?? $l->stage, $l->next_action, $l->next_action_date?->format('Y-m-d'), $l->notes,
            ]);
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
