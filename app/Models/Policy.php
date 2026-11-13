<?php

namespace App\Models;

use App\Casts\RichTextCast;
use Illuminate\Database\Eloquent\Model;

/** One of the company's rules or policies. Everyone reads them on their profile; Super Admins write them. */
class Policy extends Model
{
    protected $guarded = [];

    protected $casts = ['body' => RichTextCast::class, 'sort_order' => 'integer'];
}
