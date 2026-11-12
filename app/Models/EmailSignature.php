<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** A saved email signature (the form values, not the HTML). A person can keep several, each with a name. */
class EmailSignature extends Model
{
    protected $guarded = [];

    protected $casts = ['data' => 'array'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
