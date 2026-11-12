<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** A saved email template (the form values, not the HTML), shared by the team. */
class EmailTemplate extends Model
{
    protected $guarded = [];

    protected $casts = ['data' => 'array'];

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
