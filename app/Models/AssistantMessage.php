<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** One turn of a person's conversation with the AI assistant. Each person sees only their own. */
class AssistantMessage extends Model
{
    protected $guarded = [];
}
