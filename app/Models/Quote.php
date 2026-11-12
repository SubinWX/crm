<?php

namespace App\Models;

use App\Casts\RichTextCast;
use App\Models\Concerns\LogsActivity;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class Quote extends Model
{
    use LogsActivity;

    public const STATUSES = ['draft' => 'Draft', 'sent' => 'Sent', 'accepted' => 'Accepted', 'declined' => 'Declined', 'expired' => 'Expired'];

    public const FREQUENCIES = ['one_time' => 'One-time', 'monthly' => 'Monthly', 'quarterly' => 'Quarterly', 'yearly' => 'Yearly'];

    public const FREQUENCY_SUFFIX = ['one_time' => '', 'monthly' => ' / month', 'quarterly' => ' / quarter', 'yearly' => ' / year'];

    protected $attributes = ['status' => 'draft', 'revision' => 1, 'discount_percent' => 0, 'tax_rate' => 0];

    protected $guarded = [];

    protected $casts = [
        'issue_date' => 'date', 'expiry_date' => 'date', 'follow_up_date' => 'date', 'sent_at' => 'datetime', 'responded_at' => 'datetime',
        'discount_percent' => 'decimal:2', 'tax_rate' => 'decimal:2', 'revision' => 'integer', 'notes' => RichTextCast::class,
    ];

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function lead(): BelongsTo
    {
        return $this->belongsTo(Lead::class);
    }

    public function contact(): BelongsTo
    {
        return $this->belongsTo(ClientContact::class, 'contact_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function sections(): HasMany
    {
        return $this->hasMany(QuoteSection::class)->orderBy('sort_order')->orderBy('id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(QuoteItem::class)->orderBy('sort_order')->orderBy('id');
    }

    public function revisions(): HasMany
    {
        return $this->hasMany(QuoteRevision::class)->orderByDesc('revision');
    }

    public function files(): HasMany
    {
        return $this->hasMany(QuoteFile::class, 'owner_id')->where('owner_type', 'quote');
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }

    public function partyName(): string
    {
        return $this->client?->name ?? $this->lead?->name ?? $this->recipient_company ?? '—';
    }

    public function isExpired(): bool
    {
        return $this->status === 'sent' && $this->expiry_date !== null && days_until($this->expiry_date) < 0;
    }

    /** Stored status, except a sent quote past its expiry date reads as "expired". */
    public function getDisplayStatusAttribute(): string
    {
        return $this->isExpired() ? 'expired' : $this->status;
    }

    public function scopeDisplayStatus(Builder $query, string $status): Builder
    {
        $today = today_local()->toDateString();

        return match ($status) {
            'expired' => $query->where('status', 'sent')->whereNotNull('expiry_date')->where('expiry_date', '<', $today),
            'sent' => $query->where('status', 'sent')->where(fn ($q) => $q->whereNull('expiry_date')->orWhere('expiry_date', '>=', $today)),
            default => $query->where('status', $status),
        };
    }

    /** Sent and still inside its validity period: waiting for the customer to answer. */
    public function scopeAwaitingResponse(Builder $query): Builder
    {
        return $query->displayStatus('sent');
    }

    public static function nextNumber(): string
    {
        $prefix = 'QUO-'.today_local()->format('Y').'-';
        $last = static::where('number', 'like', $prefix.'%')->orderByDesc('id')->value('number');
        $sequence = $last ? ((int) substr($last, strlen($prefix))) + 1 : 1;
        do {
            $number = $prefix.str_pad((string) $sequence++, 4, '0', STR_PAD_LEFT);
        } while (static::where('number', $number)->exists());

        return $number;
    }

    /**
     * One-time and recurring charges are totalled separately. The discount and tax apply to every line.
     *
     * @param  iterable<array{quantity:mixed, unit_price:mixed, billing_frequency:string}>  $items
     * @return array<string, array{subtotal:float, discount:float, tax:float, total:float}> keyed by billing frequency
     */
    public static function computeTotals(iterable $items, float $discountPercent, float $taxRate): array
    {
        $groups = [];
        foreach ($items as $item) {
            $frequency = array_key_exists($item['billing_frequency'] ?? '', self::FREQUENCIES) ? $item['billing_frequency'] : 'one_time';
            $groups[$frequency] = ($groups[$frequency] ?? 0) + round((float) ($item['quantity'] ?: 0) * (float) ($item['unit_price'] ?: 0), 2);
        }

        $totals = [];
        foreach (array_keys(self::FREQUENCIES) as $frequency) {
            if (! isset($groups[$frequency])) {
                continue;
            }
            $subtotal = round($groups[$frequency], 2);
            $discount = round($subtotal * $discountPercent / 100, 2);
            $tax = round(($subtotal - $discount) * $taxRate / 100, 2);
            $totals[$frequency] = ['subtotal' => $subtotal, 'discount' => $discount, 'tax' => $tax, 'total' => round($subtotal - $discount + $tax, 2)];
        }

        return $totals;
    }

    public function totals(): array
    {
        return self::computeTotals($this->items->toArray(), (float) $this->discount_percent, (float) $this->tax_rate);
    }

    /** Everything the PDF needs, as plain data. Stored with each issued revision. */
    public function toDocument(): array
    {
        $this->loadMissing(['sections', 'items']);

        return [
            'number' => $this->number,
            'revision' => $this->revision,
            'title' => $this->title,
            'currency' => $this->currency,
            'issue_date' => $this->issue_date?->toDateString(),
            'expiry_date' => $this->expiry_date?->toDateString(),
            // Only the stored path is kept; the image itself is read when a PDF is produced.
            'client_logo_path' => $this->client?->logo_path,
            'recipient' => [
                'name' => $this->recipient_name, 'email' => $this->recipient_email,
                'company' => $this->recipient_company, 'address' => $this->recipient_address,
            ],
            'sections' => $this->sections->where('is_hidden', false)->map(fn ($s) => ['title' => $s->title, 'body' => $s->body])->values()->all(),
            'items' => $this->items->map(fn ($i) => [
                'name' => $i->name, 'description' => $i->description, 'quantity' => (float) $i->quantity,
                'unit_price' => (float) $i->unit_price, 'billing_frequency' => $i->billing_frequency, 'amount' => (float) $i->amount,
            ])->values()->all(),
            'discount_percent' => (float) $this->discount_percent,
            'tax_rate' => (float) $this->tax_rate,
            'totals' => $this->totals(),
            'company' => [
                'name' => setting('company_name', config('app.name')), 'address' => setting('company_address'),
                'email' => setting('company_email'), 'phone' => setting('company_phone'), 'tax_id' => setting('company_tax_id'),
            ],
        ];
    }

    public static function renderPdf(array $document): string
    {
        return Pdf::loadView('pdf.quote', ['doc' => $document])->setPaper('a4')
            ->setOption(['isFontSubsettingEnabled' => true, 'isRemoteEnabled' => false])->output();
    }

    /**
     * Issue the current revision: freeze the content and store its PDF. Later edits to the quote
     * or to any template never touch this file.
     */
    public function markSent(?int $userId = null): QuoteRevision
    {
        return DB::transaction(function () use ($userId) {
            $document = $this->toDocument();
            $path = "quotes/{$this->id}/{$this->number}-rev{$this->revision}.pdf";
            Storage::disk('local')->put($path, self::renderPdf($document));

            $revision = $this->revisions()->updateOrCreate(
                ['revision' => $this->revision],
                ['pdf_path' => $path, 'snapshot' => $document, 'issued_at' => now(), 'created_by' => $userId ?? auth('web')->id()]
            );
            $this->forceFill(['status' => 'sent', 'sent_at' => now(), 'responded_at' => null])->save();

            if ($this->lead && in_array($this->lead->stage, ['new', 'contacted'], true)) {
                $this->lead->update(['stage' => 'proposal_sent']);
            }

            return $revision;
        });
    }

    /** Start a new editable revision of a quote that has already been issued. Earlier PDFs are kept. */
    public function revise(): void
    {
        if ($this->status === 'draft') {
            return;
        }
        $this->forceFill(['status' => 'draft', 'revision' => $this->revision + 1, 'sent_at' => null, 'responded_at' => null])->save();
    }

    public function respond(string $status): void
    {
        $this->forceFill(['status' => $status, 'responded_at' => now(), 'follow_up_date' => null])->save();
    }
}
