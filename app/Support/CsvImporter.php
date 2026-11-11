<?php

namespace App\Support;

use App\Models\Client;
use App\Models\Lead;
use App\Models\Lookup;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Reads a CSV, maps its columns onto lead or client fields, flags duplicates and invalid
 * rows, and imports the rest. Used by the import wizard for preview and for the real run.
 */
class CsvImporter
{
    public const MAX_ROWS = 5000;

    /** field => [label, header aliases used for auto-mapping] */
    public const FIELDS = [
        'leads' => [
            'name' => ['Company / lead name', ['name', 'company', 'lead', 'business', 'organisation', 'organization']],
            'contact_name' => ['Contact name', ['contact', 'contact name', 'person', 'full name']],
            'email' => ['Email', ['email', 'e-mail', 'email address']],
            'phone' => ['Phone', ['phone', 'mobile', 'phone number', 'contact number', 'whatsapp']],
            'website' => ['Website', ['website', 'url', 'site']],
            'location' => ['Location', ['location', 'city', 'address']],
            'source' => ['Acquisition source', ['source', 'acquisition source', 'client by', 'lead source']],
            'estimated_value' => ['Estimated value', ['value', 'estimated value', 'budget', 'amount']],
            'stage' => ['Stage', ['stage', 'status']],
            'notes' => ['Notes', ['notes', 'note', 'comments', 'remarks']],
        ],
        'clients' => [
            'name' => ['Client / company name', ['name', 'company', 'client', 'business', 'organisation', 'organization']],
            'contact_name' => ['Primary contact', ['contact', 'contact name', 'person', 'primary contact']],
            'email' => ['Email', ['email', 'e-mail', 'email address']],
            'phone1' => ['Phone 1', ['phone', 'phone 1', 'mobile', 'phone number']],
            'phone2' => ['Phone 2', ['phone 2', 'alternate phone', 'alt phone']],
            'website' => ['Website', ['website', 'url', 'site']],
            'location' => ['Location', ['location', 'city']],
            'billing_address' => ['Billing address', ['address', 'billing address']],
            'industry' => ['Industry', ['industry', 'sector']],
            'source' => ['Client By (source)', ['source', 'client by', 'acquisition source']],
            'status' => ['Status', ['status']],
            'tax_id' => ['Tax ID / GSTIN', ['gstin', 'gst', 'tax id', 'tax']],
            'join_date' => ['Join date', ['join date', 'joined', 'start date', 'since']],
            'notes' => ['Notes', ['notes', 'note', 'comments', 'remarks']],
        ],
    ];

    /** @return array{headers: string[], rows: array<int, string[]>, truncated: bool} */
    public static function read(string $path): array
    {
        $handle = fopen($path, 'r');
        if (! $handle) {
            return ['headers' => [], 'rows' => [], 'truncated' => false];
        }

        // Excel exports are often semicolon- or tab-separated; pick whichever splits the header best.
        $first = (string) fgets($handle);
        $delimiter = collect([',', ';', "\t"])->sortByDesc(fn ($d) => substr_count($first, $d))->first();
        rewind($handle);

        $headers = [];
        $rows = [];
        $truncated = false;
        while (($line = fgetcsv($handle, null, $delimiter, '"', '\\')) !== false) {
            if ($line === [null] || trim(implode('', $line)) === '') {
                continue;
            }
            $line = array_map(fn ($v) => trim((string) $v), $line);
            if (! $headers) {
                $line[0] = preg_replace('/^\xEF\xBB\xBF/', '', $line[0]); // strip the UTF-8 BOM
                $headers = $line;

                continue;
            }
            if (count($rows) >= self::MAX_ROWS) {
                $truncated = true;
                break;
            }
            $rows[] = $line;
        }
        fclose($handle);

        return compact('headers', 'rows', 'truncated');
    }

    /** Guess which CSV column feeds each field from the header names. @return array<string, string> field => column index ('' = none) */
    public static function guessMapping(string $type, array $headers): array
    {
        // Headings and aliases are compared in the same simplified form, so "E-mail", "e_mail" and "E mail" all match.
        $simplify = fn ($h) => Str::of($h)->lower()->replace(['_', '-'], ' ')->squish()->toString();
        $normalised = array_map($simplify, $headers);
        $mapping = [];
        foreach (self::FIELDS[$type] as $field => [, $aliases]) {
            $index = collect($aliases)->map(fn ($alias) => array_search($simplify($alias), $normalised, true))->first(fn ($i) => $i !== false);
            $mapping[$field] = $index === null || $index === false || in_array((string) $index, $mapping, true) ? '' : (string) $index;
        }

        return $mapping;
    }

    /**
     * Apply the mapping to every row and decide what would happen to it.
     *
     * @return array<int, array{line:int, data:array<string, ?string>, status:string, reason:?string}> status: new | duplicate | invalid
     */
    public static function analyse(string $type, array $rows, array $mapping): array
    {
        $model = $type === 'leads' ? Lead::class : Client::class;
        $existingEmails = $model::query()->whereNotNull('email')->pluck('email')->map(fn ($e) => mb_strtolower($e))->flip();
        $existingNames = $model::query()->pluck('name')->map(fn ($n) => mb_strtolower(trim($n)))->flip();
        $seen = [];
        $result = [];

        foreach ($rows as $i => $row) {
            $data = [];
            foreach (array_keys(self::FIELDS[$type]) as $field) {
                $column = $mapping[$field] ?? '';
                $data[$field] = $column !== '' && isset($row[(int) $column]) && $row[(int) $column] !== '' ? Str::limit($row[(int) $column], $field === 'notes' ? 5000 : 255, '') : null;
            }

            $status = 'new';
            $reason = null;
            $email = $data['email'] ? mb_strtolower($data['email']) : null;
            $name = $data['name'] ? mb_strtolower($data['name']) : null;

            if (! $name) {
                [$status, $reason] = ['invalid', 'No name'];
            } elseif ($email && ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
                [$status, $reason] = ['invalid', 'Email is not valid'];
            } elseif ($email && isset($existingEmails[$email])) {
                [$status, $reason] = ['duplicate', 'Email already in the CRM'];
            } elseif (isset($existingNames[$name])) {
                [$status, $reason] = ['duplicate', 'Name already in the CRM'];
            } elseif (isset($seen[$email ?: $name])) {
                [$status, $reason] = ['duplicate', 'Repeated in this file'];
            }
            if ($status === 'new') {
                $seen[$email ?: $name] = true;
            }

            $result[] = ['line' => $i + 2, 'data' => $data, 'status' => $status, 'reason' => $reason];
        }

        return $result;
    }

    /** Import the analysed rows. Duplicates are skipped unless $includeDuplicates. @return array{imported:int, skipped:int} */
    public static function import(string $type, array $analysed, bool $includeDuplicates, int $userId): array
    {
        $lookup = function (string $lookupType, ?string $name): ?int {
            return $name ? Lookup::where('type', $lookupType)->whereRaw('lower(name) = ?', [mb_strtolower($name)])->value('id') : null;
        };
        $imported = 0;
        $skipped = 0;

        DB::transaction(function () use ($type, $analysed, $includeDuplicates, $userId, $lookup, &$imported, &$skipped) {
            foreach ($analysed as $row) {
                if ($row['status'] === 'invalid' || ($row['status'] === 'duplicate' && ! $includeDuplicates)) {
                    $skipped++;

                    continue;
                }
                $d = $row['data'];

                if ($type === 'leads') {
                    $stage = Str::of((string) $d['stage'])->lower()->replace(' ', '_')->toString();
                    Lead::create([
                        'name' => $d['name'], 'contact_name' => $d['contact_name'], 'email' => $d['email'] ? mb_strtolower($d['email']) : null,
                        'phone' => $d['phone'] ? Str::limit($d['phone'], 30, '') : null, 'website' => $d['website'], 'location' => $d['location'],
                        'acquisition_source_id' => $lookup('acquisition_source', $d['source']),
                        'estimated_value' => is_numeric($value = preg_replace('/[^\d.]/', '', (string) $d['estimated_value'])) ? $value : null,
                        'stage' => array_key_exists($stage, Lead::STAGES) ? $stage : 'new',
                        'notes' => $d['notes'], 'owner_id' => $userId, 'created_by' => $userId,
                    ]);
                } else {
                    $status = Str::of((string) $d['status'])->lower()->replace(' ', '_')->toString();
                    $client = Client::create([
                        'name' => $d['name'], 'contact_name' => $d['contact_name'], 'email' => $d['email'] ? mb_strtolower($d['email']) : null,
                        'phone1' => $d['phone1'] ? Str::limit($d['phone1'], 30, '') : null, 'phone2' => $d['phone2'] ? Str::limit($d['phone2'], 30, '') : null,
                        'website' => $d['website'], 'location' => $d['location'], 'billing_address' => $d['billing_address'],
                        'industry_id' => $lookup('industry', $d['industry']), 'acquisition_source_id' => $lookup('acquisition_source', $d['source']),
                        'status' => array_key_exists($status, Client::STATUSES) ? $status : 'active',
                        'tax_id' => $d['tax_id'] ? Str::limit($d['tax_id'], 40, '') : null, 'join_date' => self::date($d['join_date']),
                        'notes' => $d['notes'], 'created_by' => $userId,
                    ]);
                    if ($d['contact_name'] || $d['email']) {
                        $client->contacts()->create(['name' => $d['contact_name'] ?: $d['name'], 'email' => $client->email, 'phone' => $client->phone1, 'role' => 'primary', 'is_primary' => true]);
                    }
                }
                $imported++;
            }
        });

        return compact('imported', 'skipped');
    }

    protected static function date(?string $value): ?string
    {
        if (! $value) {
            return null;
        }
        foreach (['d/m/Y', 'd-m-Y', 'Y-m-d', 'd M Y', 'd.m.Y'] as $format) {
            try {
                $date = Carbon::createFromFormat($format, $value);
                if ($date && $date->format($format) === $value) {
                    return $date->toDateString();
                }
            } catch (\Throwable) {
                // try the next format
            }
        }

        return null;
    }
}
