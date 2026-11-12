<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** A company holiday. Not counted as a leave day, and shown as "H" on the attendance sheet. */
class Holiday extends Model
{
    protected $guarded = [];

    protected $casts = ['date' => 'date'];
}
