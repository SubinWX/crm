<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** One person's attendance for one day: marked by checking in, or set by a manager on the sheet. */
class AttendanceRecord extends Model
{
    public const STATUSES = ['present' => 'Present', 'half_day' => 'Half day', 'wfh' => 'Work from home', 'absent' => 'Absent'];

    protected $guarded = [];

    protected $attributes = ['status' => 'present'];

    protected $casts = ['date' => 'date'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
