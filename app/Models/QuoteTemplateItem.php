<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class QuoteTemplateItem extends Model
{
    protected $guarded = [];

    protected $casts = ['quantity' => 'decimal:2', 'unit_price' => 'decimal:2'];
}
