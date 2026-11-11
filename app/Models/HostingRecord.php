<?php

namespace App\Models;

use App\Models\Concerns\LogsActivity;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class HostingRecord extends Model
{
    use LogsActivity;

    protected $attributes = ['status' => 'active', 'billing_frequency' => 'yearly'];

    public const FREQUENCIES = [
        'monthly' => 'Monthly', 'quarterly' => 'Quarterly', 'half_yearly' => 'Half-yearly',
        'yearly' => 'Yearly', 'biennial' => 'Every 2 years', 'triennial' => 'Every 3 years',
    ];

    public const FREQUENCY_MONTHS = [
        'monthly' => 1, 'quarterly' => 3, 'half_yearly' => 6, 'yearly' => 12, 'biennial' => 24, 'triennial' => 36,
    ];

    public const STATUSES = ['active' => 'Active', 'suspended' => 'Suspended', 'cancelled' => 'Cancelled'];

    protected $guarded = [];

    protected $casts = [
        'billing_start' => 'date', 'billing_end' => 'date', 'next_payment_date' => 'date', 'renewal_amount' => 'decimal:2',
    ];

    protected static function booted(): void
    {
        // A changed billing date or an inactive record invalidates reminders that were
        // claimed but not delivered yet. Future reminders are derived from the current
        // date on every scheduler run, so nothing else needs rebuilding.
        static::updated(function (HostingRecord $record) {
            if ($record->wasChanged(['next_payment_date', 'status', 'server_type_id'])) {
                ReminderLog::query()
                    ->where('subject_type', 'hosting')->where('subject_id', $record->id)
                    ->whereNull('sent_at')->whereNull('cancelled_at')
                    ->update(['cancelled_at' => now()]);
            }
        });
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function serverType(): BelongsTo
    {
        return $this->belongsTo(Lookup::class, 'server_type_id');
    }

    public function serverProvider(): BelongsTo
    {
        return $this->belongsTo(Lookup::class, 'server_provider_id');
    }

    public function renewals(): HasMany
    {
        return $this->hasMany(HostingRenewal::class)->orderByDesc('paid_on')->orderByDesc('id');
    }

    public function scopePaid(Builder $query): Builder
    {
        return $query->whereHas('serverType', fn ($q) => $q->where('is_paid', true));
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', 'active');
    }

    /** Records that should produce payment reminders. */
    public function scopeRemindable(Builder $query): Builder
    {
        return $query->active()->paid()->whereNotNull('next_payment_date')
            ->whereHas('client', fn ($q) => $q->whereNull('archived_at'));
    }

    public function isPaidType(): bool
    {
        return (bool) $this->serverType?->is_paid;
    }

    public function daysUntilDue(): ?int
    {
        return days_until($this->next_payment_date);
    }

    /** overdue | week | two_months | later | none */
    public function urgency(): string
    {
        if (! $this->next_payment_date || $this->status !== 'active') {
            return 'none';
        }
        $days = $this->daysUntilDue();
        if ($days < 0) {
            return 'overdue';
        }
        if ($days <= 7) {
            return 'week';
        }

        return local_date($this->next_payment_date)->lte(today_local()->addMonthsNoOverflow(2)) ? 'two_months' : 'later';
    }

    public function urgencyLabel(): string
    {
        $days = $this->daysUntilDue();

        return match ($this->urgency()) {
            'overdue' => 'Overdue by '.abs($days).' '.str('day')->plural(abs($days)),
            'week' => $days === 0 ? 'Due today' : 'Due in '.$days.' '.str('day')->plural($days),
            'two_months' => 'Due in '.$days.' days',
            'later' => 'In '.$days.' days',
            default => '—',
        };
    }

    public static function advance(?Carbon $date, string $frequency): ?Carbon
    {
        return $date?->copy()->addMonthsNoOverflow(self::FREQUENCY_MONTHS[$frequency] ?? 12);
    }

    /** Suggested values for the Record Renewal form. */
    public function renewalDefaults(): array
    {
        $nextStart = $this->billing_end?->copy()->addDay();
        $nextEnd = $nextStart ? self::advance($nextStart, $this->billing_frequency)->subDay() : null;

        return [
            'paid_on' => today_local()->toDateString(),
            'amount' => (string) $this->renewal_amount,
            'new_billing_start' => $nextStart?->toDateString(),
            'new_billing_end' => $nextEnd?->toDateString(),
            'next_payment_date' => self::advance($this->next_payment_date, $this->billing_frequency)?->toDateString(),
            'new_renewal_amount' => (string) $this->renewal_amount,
            'notes' => '',
        ];
    }

    /**
     * Store the completed billing period in the history and roll the record forward.
     */
    public function recordRenewal(array $data, ?int $userId = null): HostingRenewal
    {
        return DB::transaction(function () use ($data, $userId) {
            $renewal = $this->renewals()->create([
                'period_start' => $this->billing_start,
                'period_end' => $this->billing_end,
                'payment_due_date' => $this->next_payment_date,
                'paid_on' => $data['paid_on'],
                'amount' => $data['amount'],
                'next_payment_date' => $data['next_payment_date'],
                'notes' => $data['notes'] ?? null,
                'recorded_by' => $userId ?? auth()->id(),
            ]);

            $this->update([
                'billing_start' => $data['new_billing_start'] ?? $this->billing_start,
                'billing_end' => $data['new_billing_end'] ?? $this->billing_end,
                'next_payment_date' => $data['next_payment_date'],
                'renewal_amount' => $data['new_renewal_amount'] ?? $this->renewal_amount,
            ]);

            return $renewal;
        });
    }
}
