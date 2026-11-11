<?php

namespace App\Models;

use App\Models\Concerns\LogsActivity;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, LogsActivity, Notifiable;

    public const ROLE_SUPER_ADMIN = 'super_admin';

    public const ROLE_STAFF = 'staff';

    /** Minimal access: may add clients and see only the clients they added. */
    public const ROLE_SUB_ADMIN = 'sub_admin';

    public const ROLES = [self::ROLE_SUPER_ADMIN => 'Super Admin', self::ROLE_SUB_ADMIN => 'Sub Admin', self::ROLE_STAFF => 'Staff'];

    /** Permissions a Super Admin can grant to Staff. Finance, users and settings stay Super Admin only. */
    public const STAFF_PERMISSIONS = [
        'clients.view' => 'View clients, hosting and files',
        'clients.manage' => 'Add, edit and archive clients and hosting',
        'tasks.manage' => 'Create and update tasks',
        'posters.manage' => 'Upload and edit social media posters',
        'support.manage' => 'Answer and manage support tickets',
        'sales.manage' => 'Manage leads, follow-ups, quotes and proposals',
    ];

    protected $fillable = [
        'name', 'email', 'password', 'phone', 'alt_phone', 'date_of_birth', 'avatar_path', 'role', 'is_active', 'permissions',
        'invitation_token', 'invited_at', 'invited_by', 'last_login_at',
    ];

    protected $hidden = ['password', 'remember_token', 'invitation_token'];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'invited_at' => 'datetime',
            'last_login_at' => 'datetime',
            'date_of_birth' => 'date',
            'password' => 'hashed',
            'is_active' => 'boolean',
            'permissions' => 'array',
        ];
    }

    public function isSuperAdmin(): bool
    {
        return $this->role === self::ROLE_SUPER_ADMIN;
    }

    public function isSubAdmin(): bool
    {
        return $this->role === self::ROLE_SUB_ADMIN;
    }

    public function hasPermission(string $permission): bool
    {
        if (! $this->is_active) {
            return false;
        }
        if ($this->isSuperAdmin()) {
            return true;
        }
        if ($this->isSubAdmin()) {
            // Fixed, minimal access. Which clients they can open is decided by the "client.access" gate.
            return $permission === 'clients.view';
        }
        $granted = $this->permissions ?? [];
        // Managing clients implies being able to see them.
        if ($permission === 'clients.view' && in_array('clients.manage', $granted, true)) {
            return true;
        }

        return in_array($permission, $granted, true);
    }

    public function equipment(): HasMany
    {
        return $this->hasMany(Equipment::class);
    }

    /** Which sign-in page this account uses: "admin" (/login, Super Admins and Sub Admins) or "staff" (/staff). */
    public function loginArea(): string
    {
        return $this->role === self::ROLE_STAFF ? 'staff' : 'admin';
    }

    public function loginUrl(): string
    {
        return $this->loginArea() === 'staff' ? route('staff.login') : route('login');
    }

    public function hasPendingInvitation(): bool
    {
        return $this->password === null;
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeSuperAdmins(Builder $query): Builder
    {
        return $query->where('role', self::ROLE_SUPER_ADMIN);
    }

    /** Active Super Admins: recipients of every internal notification and valid "Payment To" choices. */
    public static function activeSuperAdmins()
    {
        return static::query()->active()->superAdmins()->orderBy('name')->get();
    }

    /** URL of the headshot, or null. The timestamp busts the browser cache after a replacement. */
    public function avatarUrl(): ?string
    {
        return $this->avatar_path ? route('users.avatar', ['user' => $this->id, 'v' => $this->updated_at?->timestamp]) : null;
    }

    public function getRoleLabelAttribute(): string
    {
        return self::ROLES[$this->role] ?? $this->role;
    }
}
