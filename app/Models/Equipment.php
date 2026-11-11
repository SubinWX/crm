<?php

namespace App\Models;

use App\Models\Concerns\LogsActivity;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Equipment extends Model
{
    use LogsActivity;

    public const CONDITIONS = ['new' => 'New', 'good' => 'Good', 'fair' => 'Fair', 'needs_repair' => 'Needs repair', 'retired' => 'Retired'];

    protected $table = 'equipment';

    protected $attributes = ['condition' => 'good'];

    protected $guarded = [];

    protected $casts = ['assigned_on' => 'date', 'purchase_date' => 'date', 'purchase_cost' => 'decimal:2'];

    public function type(): BelongsTo
    {
        return $this->belongsTo(Lookup::class, 'type_id');
    }

    /** The person the item is issued to. */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
