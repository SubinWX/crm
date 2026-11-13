<?php

namespace App\Models;

use App\Casts\RichTextCast;
use App\Models\Concerns\LogsActivity;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Client extends Model
{
    use HasFactory, LogsActivity;

    protected $attributes = ['status' => 'active'];

    public const STATUSES = ['lead' => 'Lead', 'active' => 'Active', 'on_hold' => 'On hold', 'inactive' => 'Inactive'];

    public const CHANNELS = ['email' => 'Email', 'phone' => 'Phone call', 'whatsapp' => 'WhatsApp', 'other' => 'Other'];

    protected $guarded = [];

    protected $casts = ['join_date' => 'date', 'archived_at' => 'datetime', 'bulk_opt_out_at' => 'datetime', 'notes' => RichTextCast::class];

    public function industry(): BelongsTo
    {
        return $this->belongsTo(Lookup::class, 'industry_id');
    }

    public function acquisitionSource(): BelongsTo
    {
        return $this->belongsTo(Lookup::class, 'acquisition_source_id');
    }

    public function referrer(): BelongsTo
    {
        return $this->belongsTo(Lookup::class, 'referrer_id');
    }

    public function accountManager(): BelongsTo
    {
        return $this->belongsTo(User::class, 'account_manager_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function workTypes(): BelongsToMany
    {
        return $this->belongsToMany(Lookup::class, 'client_work_type', 'client_id', 'lookup_id');
    }

    public function hostingRecords(): HasMany
    {
        return $this->hasMany(HostingRecord::class);
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function tasks(): HasMany
    {
        return $this->hasMany(Task::class);
    }

    public function contacts(): HasMany
    {
        return $this->hasMany(ClientContact::class)->orderByDesc('is_primary')->orderBy('name');
    }

    public function projects(): HasMany
    {
        return $this->hasMany(Project::class);
    }

    public function renewals(): HasMany
    {
        return $this->hasMany(Renewal::class);
    }

    /** Support tickets linked to this client. */
    public function supportTickets(): HasMany
    {
        return $this->hasMany(SupportTicket::class);
    }

    public function quotes(): HasMany
    {
        return $this->hasMany(Quote::class);
    }

    public function interactions(): HasMany
    {
        return $this->hasMany(Interaction::class);
    }

    public function files(): HasMany
    {
        return $this->hasMany(ClientFile::class);
    }

    /** Sub Admins only ever see the clients they added themselves. */
    public function scopeVisibleTo(Builder $query, ?User $user): Builder
    {
        return $user?->isSubAdmin() ? $query->where('created_by', $user->id) : $query;
    }

    public function scopeNotArchived(Builder $query): Builder
    {
        return $query->whereNull('archived_at');
    }

    /** URL of the brand logo, or null. */
    public function logoUrl(): ?string
    {
        return $this->logo_path ? route('clients.logo', ['client' => $this->id, 'v' => $this->updated_at?->timestamp]) : null;
    }

    public function isArchived(): bool
    {
        return $this->archived_at !== null;
    }

    /**
     * Financial position for the client.
     *
     * pending     = issued invoice totals − payments applied to those invoices
     * unallocated = payments received − payments applied to any invoice
     */
    public function financials(): array
    {
        $issued = $this->invoices()->whereIn('status', Invoice::ISSUED_STATUSES);
        $invoiced = (float) (clone $issued)->sum('total');
        $applied = (float) PaymentAllocation::query()
            ->whereIn('invoice_id', (clone $issued)->select('id'))
            ->sum('amount');
        $received = (float) $this->payments()->sum('amount');
        $allocated = (float) PaymentAllocation::query()
            ->whereIn('payment_id', $this->payments()->select('id'))
            ->sum('amount');

        return [
            'invoiced' => round($invoiced, 2),
            'applied' => round($applied, 2),
            'pending' => round($invoiced - $applied, 2),
            'received' => round($received, 2),
            'unallocated' => round($received - $allocated, 2),
        ];
    }
}
