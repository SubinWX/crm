<?php

namespace App\Models;

use App\Models\Concerns\LogsActivity;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Expense extends Model
{
    use LogsActivity;

    public const FREQUENCIES = ['weekly' => 'Weekly', 'monthly' => 'Monthly', 'quarterly' => 'Quarterly', 'yearly' => 'Yearly'];

    protected $guarded = [];

    protected $casts = ['expense_date' => 'date', 'amount' => 'decimal:2', 'is_recurring' => 'boolean'];

    public function category(): BelongsTo
    {
        return $this->belongsTo(Lookup::class, 'category_id');
    }

    public function method(): BelongsTo
    {
        return $this->belongsTo(Lookup::class, 'payment_method_id');
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function activityLabel(): string
    {
        return trim(($this->payee ?: 'Expense').' '.money($this->amount));
    }
}
