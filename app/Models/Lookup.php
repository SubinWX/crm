<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

class Lookup extends Model
{
    protected $guarded = [];

    protected $casts = ['is_active' => 'boolean', 'is_paid' => 'boolean'];

    public const TYPES = [
        'work_type' => 'Work types',
        'industry' => 'Industries',
        'acquisition_source' => 'Acquisition sources (Client By)',
        'referrer' => 'Referrers (Referred By)',
        'server_type' => 'Server types',
        'server_provider' => 'Server providers / plans',
        'payment_method' => 'Payment methods',
        'expense_category' => 'Expense categories',
        'equipment_type' => 'Equipment types',
    ];

    public function scopeOfType(Builder $query, string $type): Builder
    {
        return $query->where('type', $type)->orderBy('sort_order')->orderBy('name');
    }

    /**
     * Active values for a dropdown, plus any inactive values already used by the
     * record being edited so existing data is preserved.
     */
    public static function options(string $type, array|int|string|null $keep = null): Collection
    {
        $keep = array_filter((array) $keep);

        return static::query()
            ->where('type', $type)
            ->where(fn ($q) => $q->where('is_active', true)->when($keep, fn ($q) => $q->orWhereIn('id', $keep)))
            ->orderBy('sort_order')->orderBy('name')
            ->get();
    }

    public function usageCount(): int
    {
        return match ($this->type) {
            'work_type' => \DB::table('client_work_type')->where('lookup_id', $this->id)->count(),
            'industry' => Client::where('industry_id', $this->id)->count(),
            'acquisition_source' => Client::where('acquisition_source_id', $this->id)->count(),
            'referrer' => Client::where('referrer_id', $this->id)->count(),
            'server_type' => HostingRecord::where('server_type_id', $this->id)->count(),
            'server_provider' => HostingRecord::where('server_provider_id', $this->id)->count(),
            'payment_method' => Payment::where('payment_method_id', $this->id)->count() + Expense::where('payment_method_id', $this->id)->count(),
            'expense_category' => Expense::where('category_id', $this->id)->count(),
            'equipment_type' => Equipment::where('type_id', $this->id)->count(),
            default => 0,
        };
    }
}
