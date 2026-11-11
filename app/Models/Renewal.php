<?php

namespace App\Models;

use App\Models\Concerns\LogsActivity;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;

/** Domain, maintenance, retainer and other renewals. Hosting keeps its own records. */
class Renewal extends Model
{
    use LogsActivity;

    public const TYPES = ['domain' => 'Domain', 'maintenance' => 'Maintenance', 'retainer' => 'Retainer', 'other' => 'Other'];

    /** Days before the renewal date on which Super Admins are reminded, when nothing is set in Settings. */
    public const DEFAULT_REMINDER_DAYS = ['domain' => '60,7', 'maintenance' => '30,7', 'retainer' => '14,3', 'other' => '30,7'];

    public const STATUSES = ['active' => 'Active', 'cancelled' => 'Cancelled'];

    protected $attributes = ['status' => 'active', 'frequency' => 'yearly', 'type' => 'domain'];

    protected $guarded = [];

    protected $casts = ['renewal_date' => 'date', 'amount' => 'decimal:2'];

    protected static function booted(): void
    {
        // Same rule as hosting: a changed date or status drops reminders that were queued but not delivered.
        static::updated(function (Renewal $renewal) {
            if ($renewal->wasChanged(['renewal_date', 'status', 'type'])) {
                ReminderLog::where('subject_type', 'renewal')->where('subject_id', $renewal->id)
                    ->whereNull('sent_at')->whereNull('cancelled_at')->update(['cancelled_at' => now()]);
            }
        });
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function logs(): HasMany
    {
        return $this->hasMany(RenewalLog::class)->orderByDesc('paid_on')->orderByDesc('id');
    }

    public function scopeRemindable(Builder $query): Builder
    {
        return $query->where('status', 'active')->whereNotNull('renewal_date')
            ->whereHas('client', fn ($q) => $q->whereNull('archived_at'));
    }

    /**
     * Reminder offsets for a renewal type, largest first, e.g. [60, 7].
     *
     * @return int[]
     */
    public static function reminderDays(string $type): array
    {
        $raw = (string) setting("renewal_reminder_days_{$type}", self::DEFAULT_REMINDER_DAYS[$type] ?? '30,7');
        $days = collect(explode(',', $raw))->map(fn ($d) => (int) trim($d))->filter(fn ($d) => $d > 0 && $d <= 365)->unique()->sortDesc()->values()->all();

        return $days ?: [30, 7];
    }

    public function daysUntilDue(): ?int
    {
        return days_until($this->renewal_date);
    }

    /** overdue | soon | later | none — "soon" means inside the first reminder window for this type. */
    public function urgency(): string
    {
        if (! $this->renewal_date || $this->status !== 'active') {
            return 'none';
        }
        $days = $this->daysUntilDue();

        return $days < 0 ? 'overdue' : ($days <= max(self::reminderDays($this->type)) ? 'soon' : 'later');
    }

    public function recordRenewal(array $data, ?int $userId = null): RenewalLog
    {
        return DB::transaction(function () use ($data, $userId) {
            $log = $this->logs()->create([
                'due_date' => $this->renewal_date, 'paid_on' => $data['paid_on'], 'amount' => $data['amount'],
                'next_date' => $data['next_date'], 'recorded_by' => $userId ?? auth('web')->id(),
            ]);
            $this->update(['renewal_date' => $data['next_date'], 'amount' => $data['new_amount'] ?? $this->amount]);

            return $log;
        });
    }
}
