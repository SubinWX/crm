<?php

namespace App\Models;

use App\Notifications\Portal\PortalResetPassword;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

/**
 * An account on the public support portal. Uses its own "portal" guard and table,
 * so a portal login can never reach the team side of the CRM.
 */
class PortalUser extends Authenticatable
{
    use Notifiable;

    protected $fillable = ['name', 'email', 'password', 'google_id', 'phone', 'company', 'client_id', 'is_active', 'created_by', 'email_verified_at', 'last_login_at'];

    protected $hidden = ['password', 'remember_token', 'google_id'];

    protected $attributes = ['is_active' => true];

    protected function casts(): array
    {
        return ['password' => 'hashed', 'is_active' => 'boolean', 'email_verified_at' => 'datetime', 'last_login_at' => 'datetime'];
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function tickets(): HasMany
    {
        return $this->hasMany(SupportTicket::class);
    }

    /** Sub Admins only manage the portal users they added. */
    public function scopeVisibleTo(Builder $query, ?User $user): Builder
    {
        return $user?->isSubAdmin() ? $query->where('created_by', $user->id) : $query;
    }

    public function sendPasswordResetNotification($token): void
    {
        $this->notify(new PortalResetPassword($token));
    }

    public function signInMethod(): string
    {
        return match (true) {
            $this->google_id && $this->password => 'Google + password',
            (bool) $this->google_id => 'Google',
            default => 'Email + password',
        };
    }
}
