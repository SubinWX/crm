<?php

namespace App\Models;

use App\Casts\RichTextCast;
use Illuminate\Database\Eloquent\Model;

class QuoteSection extends Model
{
    protected $guarded = [];

    protected $casts = ['is_hidden' => 'boolean', 'body' => RichTextCast::class];
}
