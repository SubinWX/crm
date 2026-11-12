<?php

namespace App\Models;

use App\Models\Concerns\LogsActivity;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** One person's application, sent from the public careers page. The resume is a private file. */
class JobApplication extends Model
{
    use LogsActivity;

    public const STATUSES = [
        'new' => 'New', 'reviewing' => 'Reviewing', 'shortlisted' => 'Shortlisted', 'interview' => 'Interview',
        'offered' => 'Offered', 'hired' => 'Hired', 'rejected' => 'Rejected',
    ];

    public const STATUS_BADGES = [
        'new' => 'badge-blue', 'reviewing' => 'badge-gray', 'shortlisted' => 'badge-amber', 'interview' => 'badge-amber',
        'offered' => 'badge-green', 'hired' => 'badge-green', 'rejected' => 'badge-red',
    ];

    /** Colours for the status pie chart on the dashboard. */
    public const STATUS_COLORS = [
        'new' => '#0ea5e9', 'reviewing' => '#a8a29e', 'shortlisted' => '#f59e0b', 'interview' => '#f97316',
        'offered' => '#14b8a6', 'hired' => '#059669', 'rejected' => '#f87171',
    ];

    public const RESUME_RULES = ['file', 'max:5120', 'mimes:pdf,doc,docx'];

    protected $guarded = [];

    protected $attributes = ['status' => 'new'];

    protected $casts = ['interview_at' => 'datetime', 'experience_years' => 'decimal:1', 'rating' => 'integer'];

    /** Internal review fields: changing them is not worth an activity log line each. */
    public array $activityIgnored = ['notes', 'rating', 'reviewed_by'];

    public function vacancy(): BelongsTo
    {
        return $this->belongsTo(JobVacancy::class, 'job_vacancy_id');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    /** Browsers can show a PDF in a tab; Word files have to be downloaded and opened in Word. */
    public function resumeIsPdf(): bool
    {
        return strtolower(pathinfo((string) $this->resume_name, PATHINFO_EXTENSION)) === 'pdf';
    }

    public function activityLabel(): string
    {
        return $this->name.' for '.$this->vacancy_title;
    }
}
