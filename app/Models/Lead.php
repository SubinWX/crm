<?php

namespace App\Models;

use App\Casts\RichTextCast;
use App\Models\Concerns\LogsActivity;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;

class Lead extends Model
{
    use LogsActivity;

    public const STAGES = ['new' => 'New', 'contacted' => 'Contacted', 'proposal_sent' => 'Proposal Sent', 'won' => 'Won', 'lost' => 'Lost'];

    public const OPEN_STAGES = ['new', 'contacted', 'proposal_sent'];

    protected $attributes = ['stage' => 'new'];

    protected $guarded = [];

    protected $casts = ['next_action_date' => 'date', 'converted_at' => 'datetime', 'estimated_value' => 'decimal:2', 'notes' => RichTextCast::class];

    public function source(): BelongsTo
    {
        return $this->belongsTo(Lookup::class, 'acquisition_source_id');
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function services(): BelongsToMany
    {
        return $this->belongsToMany(Lookup::class, 'lead_work_type', 'lead_id', 'lookup_id');
    }

    public function interactions(): HasMany
    {
        return $this->hasMany(Interaction::class);
    }

    public function tasks(): HasMany
    {
        return $this->hasMany(Task::class);
    }

    public function quotes(): HasMany
    {
        return $this->hasMany(Quote::class);
    }

    public function scopeOpen(Builder $query): Builder
    {
        return $query->whereIn('stage', self::OPEN_STAGES);
    }

    public function isConverted(): bool
    {
        return $this->client_id !== null;
    }

    /** Clients don't carry the lead id, so the lead's own activity is filed under the client once converted. */
    public function activityClientId(): ?int
    {
        return $this->client_id;
    }

    /** An existing client that looks like the same business (same email, or same name). */
    public function matchingClient(): ?Client
    {
        return Client::query()
            ->when($this->email, fn ($q) => $q->where('email', $this->email), fn ($q) => $q->whereRaw('lower(name) = ?', [mb_strtolower($this->name)]))
            ->first();
    }

    /**
     * Turn a won lead into a client without re-typing anything. Contacts, notes, the contact log,
     * tasks, quotes and activity all move across; the lead is kept and linked to the client.
     */
    public function convertToClient(?Client $existing = null, ?int $userId = null): Client
    {
        if ($this->client) {
            return $this->client;
        }

        return DB::transaction(function () use ($existing, $userId) {
            $client = $existing ?? Client::create([
                'name' => $this->name,
                'contact_name' => $this->contact_name,
                'email' => $this->email,
                'phone1' => $this->phone,
                'website' => $this->website,
                'location' => $this->location,
                'acquisition_source_id' => $this->acquisition_source_id,
                'account_manager_id' => $this->owner_id,
                'join_date' => today_local()->toDateString(),
                'status' => 'active',
                'notes' => $this->notes,
                'created_by' => $userId ?? auth('web')->id() ?? $this->created_by,
            ]);

            if (! $existing) {
                $client->workTypes()->sync($this->services()->pluck('lookups.id'));
            }
            if ($this->contact_name || $this->email) {
                $client->contacts()->firstOrCreate(
                    ['email' => $this->email, 'name' => $this->contact_name ?: $this->name],
                    ['phone' => $this->phone, 'role' => 'primary', 'is_primary' => ! $client->contacts()->where('is_primary', true)->exists()]
                );
            }

            // Carry the history across by linking it to the client as well as the lead.
            $this->interactions()->update(['client_id' => $client->id]);
            $this->tasks()->update(['client_id' => $client->id]);
            $this->quotes()->update(['client_id' => $client->id]);
            ActivityLog::where('subject_type', 'lead')->where('subject_id', $this->id)->update(['client_id' => $client->id]);

            $this->forceFill(['client_id' => $client->id, 'stage' => 'won', 'converted_at' => now(), 'next_action' => null, 'next_action_date' => null])->save();

            return $client;
        });
    }
}
