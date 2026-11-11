<?php

namespace App\Models;

use App\Models\Concerns\LogsActivity;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;

class QuoteTemplate extends Model
{
    use LogsActivity;

    /** Suggested sections for a new template. Admins can rename, reorder, hide or remove any of them. */
    public const STARTER_SECTIONS = [
        'Introduction', 'Scope of Work', 'Deliverables', 'Exclusions', 'Optional Add-ons',
        'Timeline', 'Milestones', 'Payment Terms', 'Quote Validity', 'References',
    ];

    protected $attributes = ['validity_days' => 30];

    protected $guarded = [];

    protected $casts = ['archived_at' => 'datetime'];

    public function sections(): HasMany
    {
        return $this->hasMany(QuoteTemplateSection::class)->orderBy('sort_order')->orderBy('id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(QuoteTemplateItem::class)->orderBy('sort_order')->orderBy('id');
    }

    public function files(): HasMany
    {
        return $this->hasMany(QuoteFile::class, 'owner_id')->where('owner_type', 'template');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->whereNull('archived_at');
    }

    public function duplicate(): self
    {
        return DB::transaction(function () {
            $copy = $this->replicate(['archived_at']);
            $copy->name = $this->name.' (copy)';
            $copy->save();
            foreach ($this->sections as $section) {
                $copy->sections()->create($section->only(['title', 'body', 'is_hidden', 'sort_order']));
            }
            foreach ($this->items as $item) {
                $copy->items()->create($item->only(['name', 'description', 'quantity', 'unit_price', 'billing_frequency', 'sort_order']));
            }

            return $copy;
        });
    }
}
