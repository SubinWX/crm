<?php

namespace App\Models;

use App\Casts\RichTextCast;
use Illuminate\Database\Eloquent\Model;

class QuoteTemplateSection extends Model
{
    protected $guarded = [];

    protected $casts = ['is_hidden' => 'boolean', 'body' => RichTextCast::class];
}
