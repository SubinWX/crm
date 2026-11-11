<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class QuoteTemplateSection extends Model
{
    protected $guarded = [];

    protected $casts = ['is_hidden' => 'boolean'];
}
