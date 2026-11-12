<?php

namespace App\Models;

use App\Casts\RichTextCast;
use App\Models\Concerns\LogsActivity;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

/** A job opening shown on the public careers page (/careers) while it is open. */
class JobVacancy extends Model
{
    use LogsActivity;

    public const STATUSES = ['draft' => 'Draft', 'open' => 'Open', 'closed' => 'Closed'];

    public const EMPLOYMENT_TYPES = [
        'full_time' => 'Full-time', 'part_time' => 'Part-time', 'contract' => 'Contract',
        'internship' => 'Internship', 'freelance' => 'Freelance',
    ];

    public const WORK_MODES = ['onsite' => 'On-site', 'in_office' => 'In-office', 'hybrid' => 'Hybrid', 'remote' => 'Remote'];

    /** Addresses under /careers that a vacancy may not take. */
    public const RESERVED_SLUGS = ['general-application'];

    protected $guarded = [];

    protected $attributes = ['status' => 'draft', 'employment_type' => 'full_time', 'work_mode' => 'onsite', 'openings' => 1];

    protected $casts = ['closes_on' => 'date', 'openings' => 'integer', 'description' => RichTextCast::class, 'requirements' => RichTextCast::class];

    public function applications(): HasMany
    {
        return $this->hasMany(JobApplication::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** Open, and not past its closing date (in the business time zone). */
    public function scopeAccepting(Builder $query): Builder
    {
        return $query->where('status', 'open')
            ->where(fn ($q) => $q->whereNull('closes_on')->orWhere('closes_on', '>=', today_local()->toDateString()));
    }

    public function isAccepting(): bool
    {
        return $this->status === 'open' && ($this->closes_on === null || $this->closes_on->toDateString() >= today_local()->toDateString());
    }

    /** What the team sees: an open vacancy past its closing date reads as "Expired". */
    public function displayStatus(): string
    {
        return $this->status === 'open' && ! $this->isAccepting() ? 'expired' : $this->status;
    }

    public function publicUrl(): string
    {
        return route('careers.show', $this->slug);
    }

    public static function uniqueSlug(string $title, ?int $ignoreId = null): string
    {
        $base = Str::slug($title) ?: 'vacancy';
        $slug = $base;
        for ($n = 2; in_array($slug, self::RESERVED_SLUGS, true)
            || static::where('slug', $slug)->when($ignoreId, fn ($q) => $q->whereKeyNot($ignoreId))->exists(); $n++) {
            $slug = $base.'-'.$n;
        }

        return $slug;
    }
}
