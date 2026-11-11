<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class QuoteSection extends Model
{
    protected $guarded = [];

    protected $casts = ['is_hidden' => 'boolean'];
}
