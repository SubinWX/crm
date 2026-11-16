<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** One line of a recurring invoice template. */
class RecurringInvoiceItem extends Model
{
    protected $guarded = [];

    protected $casts = ['quantity' => 'decimal:2', 'unit_price' => 'decimal:2'];
}
