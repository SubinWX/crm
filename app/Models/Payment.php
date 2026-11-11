<?php

namespace App\Models;

use App\Models\Concerns\LogsActivity;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class Payment extends Model
{
    use LogsActivity;

    protected $guarded = [];

    protected $casts = ['payment_date' => 'date', 'amount' => 'decimal:2'];

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function method(): BelongsTo
    {
        return $this->belongsTo(Lookup::class, 'payment_method_id');
    }

    /** "Payment To". Deactivated users are still returned so history stays intact. */
    public function receiver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'received_by');
    }

    public function recorder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }

    public function allocations(): HasMany
    {
        return $this->hasMany(PaymentAllocation::class);
    }

    public function activityLabel(): string
    {
        return money($this->amount).' on '.fmt_date($this->payment_date);
    }

    public function getAllocatedAttribute(): float
    {
        return round((float) $this->allocations->sum('amount'), 2);
    }

    public function getUnallocatedAttribute(): float
    {
        return round((float) $this->amount - $this->allocated, 2);
    }

    /**
     * Replace this payment's allocations.
     *
     * @param  array<int, float|string>  $amounts  invoice id => amount to apply
     */
    public function syncAllocations(array $amounts): void
    {
        $amounts = collect($amounts)->map(fn ($a) => round((float) $a, 2))->filter(fn ($a) => $a > 0);

        if (round($amounts->sum(), 2) > round((float) $this->amount, 2)) {
            throw ValidationException::withMessages(['allocations' => 'You cannot apply more than the payment amount.']);
        }

        DB::transaction(function () use ($amounts) {
            $previous = $this->allocations()->pluck('invoice_id');
            $invoices = Invoice::whereIn('id', $amounts->keys())->lockForUpdate()->get()->keyBy('id');

            foreach ($amounts as $invoiceId => $amount) {
                $invoice = $invoices->get($invoiceId);
                if (! $invoice || (int) $invoice->client_id !== (int) $this->client_id) {
                    throw ValidationException::withMessages(['allocations' => 'Invoice does not belong to this client.']);
                }
                if (! in_array($invoice->status, Invoice::ISSUED_STATUSES, true)) {
                    throw ValidationException::withMessages(['allocations' => "Invoice {$invoice->number} must be marked as sent before payments can be applied."]);
                }
                $others = (float) $invoice->allocations()->where('payment_id', '!=', $this->id)->sum('amount');
                if (round($others + $amount, 2) > round((float) $invoice->total, 2)) {
                    throw ValidationException::withMessages(['allocations' => "Amount applied to {$invoice->number} is more than its balance."]);
                }
            }

            $this->allocations()->whereNotIn('invoice_id', $amounts->keys())->delete();
            foreach ($amounts as $invoiceId => $amount) {
                $this->allocations()->updateOrCreate(['invoice_id' => $invoiceId], ['amount' => $amount]);
            }

            Invoice::whereIn('id', $previous->merge($amounts->keys())->unique())->get()
                ->each->refreshPaymentStatus();
        });

        $this->unsetRelation('allocations');
    }
}
