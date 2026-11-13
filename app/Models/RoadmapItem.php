<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** One line of the Timeline & Change log: something released, being worked on, or planned. */
class RoadmapItem extends Model
{
    public const STATUSES = ['in_progress' => 'In progress', 'planned' => 'Planned', 'done' => 'Done'];

    protected $guarded = [];

    protected $casts = ['happened_on' => 'date', 'sort_order' => 'integer'];
}
