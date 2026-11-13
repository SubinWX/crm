<?php

namespace App\Models;

use App\Models\Concerns\LogsActivity;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
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

    /** The middle role is shown to people as "Admin"; in the code it is still called sub_admin. */
    public const ROLES = [self::ROLE_SUPER_ADMIN => 'Super Admin', self::ROLE_SUB_ADMIN => 'Admin', self::ROLE_STAFF => 'Staff'];

    /**
     * Access a Super Admin can switch on for each person, set in the user form.
     * Users, settings and the activity log always stay with Super Admins.
     */
    public const STAFF_PERMISSIONS = [
        'clients.view' => 'View clients, hosting and files',
        'clients.manage' => 'Add, edit and archive clients and hosting',
        'tasks.manage' => 'Create and update tasks',
        'posters.manage' => 'Upload and edit social media posters',
        'support.manage' => 'Answer and manage support tickets',
        'sales.manage' => 'Manage leads, follow-ups, quotes and proposals',
        'careers.manage' => 'Manage job vacancies and applications',
        'finance.manage' => 'Invoices, payments, expenses and GST reports',
        'team.manage' => 'Approve leave, manage attendance and holidays, see everyone’s timesheets',
    ];

    /** The same options grouped the way the menu is, for the user form. */
    public const PERMISSION_GROUPS = [
        'Clients & Work' => ['clients.view', 'clients.manage', 'tasks.manage', 'posters.manage'],
        'Sales' => ['sales.manage'],
        'Support' => ['support.manage'],
        'Careers' => ['careers.manage'],
        'Finance' => ['finance.manage'],
        'Team' => ['team.manage'],
    ];

    /**
     * Extra areas a Sub Admin may be given on top of their own clients. These areas show every
     * record in them, not only the Sub Admin's clients, so they are off unless switched on.
     */
    public const SUB_ADMIN_PERMISSIONS = ['sales.manage', 'support.manage', 'careers.manage', 'finance.manage'];

    /**
     * Areas an Admin can only pass on to staff when a Super Admin has given that area to the Admin.
     * The everyday staff areas (clients, tasks, posters) are always theirs to give.
     */
    public const GUARDED_PERMISSIONS = ['sales.manage', 'support.manage', 'careers.manage', 'finance.manage', 'team.manage'];

    /**
     * The access options $this person may tick for someone with the given role.
     * Super Admins may tick anything that exists for the role. Admins manage Staff only, and cannot
     * hand out a guarded area they have not been given themselves.
     */
    public function assignablePermissions(string $role): array
    {
        if ($this->isSuperAdmin()) {
            return self::grantable($role);
        }
        if (! $this->isSubAdmin() || $role !== self::ROLE_STAFF) {
            return [];
        }

        return array_values(array_filter(self::grantable($role), fn ($permission) => ! in_array($permission, self::GUARDED_PERMISSIONS, true) || $this->hasPermission($permission)));
    }

    /** The options that can be ticked for a role. Super Admins have everything, so nothing is ticked for them. */
    public static function grantable(string $role): array
    {
        return match ($role) {
            self::ROLE_STAFF => array_keys(self::STAFF_PERMISSIONS),
            self::ROLE_SUB_ADMIN => self::SUB_ADMIN_PERMISSIONS,
            default => [],
        };
    }

    protected $fillable = [
        'name', 'email', 'password', 'phone', 'alt_phone', 'date_of_birth', 'joined_on', 'avatar_path',
        'gender', 'personal_email', 'address', 'city', 'state', 'pincode', 'nationality', 'blood_group',
        'emergency_name', 'emergency_relation', 'emergency_phone', 'emergency_alt_phone', 'role', 'is_active', 'permissions',
        'invitation_token', 'invited_at', 'invited_by', 'last_login_at',
    ];

    protected $hidden = ['password', 'remember_token', 'invitation_token', 'two_factor_secret', 'two_factor_recovery_codes'];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'invited_at' => 'datetime',
            'last_login_at' => 'datetime',
            'date_of_birth' => 'date',
            'joined_on' => 'date',
            'password' => 'hashed',
            'is_active' => 'boolean',
            'permissions' => 'array',
            'two_factor_secret' => 'encrypted',
            'two_factor_recovery_codes' => 'encrypted:array',
            'two_factor_confirmed_at' => 'datetime',
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
        $granted = $this->permissions ?? [];
        if ($this->isSubAdmin()) {
            // Always their own clients (which ones is decided by the "client.access" gate), plus any extra area switched on for them.
            return $permission === 'clients.view'
                || (in_array($permission, self::SUB_ADMIN_PERMISSIONS, true) && in_array($permission, $granted, true));
        }
        // Managing clients implies being able to see them.
        if ($permission === 'clients.view' && in_array('clients.manage', $granted, true)) {
            return true;
        }

        return in_array($permission, $granted, true);
    }

    /** Two-step login is on once the authenticator app has been confirmed with a code. */
    public function hasTwoFactor(): bool
    {
        return $this->two_factor_confirmed_at !== null && filled($this->two_factor_secret);
    }

    /** A Super Admin who still has to set it up because "Require two-step login for Super Admins" is on. */
    public function mustSetUpTwoFactor(): bool
    {
        return $this->isSuperAdmin() && ! $this->hasTwoFactor() && setting('require_two_factor') === '1';
    }

    /** Each recovery code signs in once. Returns false when the code is not one of the unused ones. */
    public function useRecoveryCode(string $code): bool
    {
        $code = strtolower(preg_replace('/\s+/', '', $code));
        $codes = $this->two_factor_recovery_codes ?? [];
        foreach ($codes as $index => $stored) {
            if (hash_equals($stored, $code)) {
                unset($codes[$index]);
                $this->forceFill(['two_factor_recovery_codes' => array_values($codes)])->save();

                return true;
            }
        }

        return false;
    }

    public function clearTwoFactor(): void
    {
        $this->forceFill(['two_factor_secret' => null, 'two_factor_recovery_codes' => null, 'two_factor_confirmed_at' => null])->save();
    }

    /** Projects this person is on the team of. */
    public function projects(): BelongsToMany
    {
        return $this->belongsToMany(Project::class);
    }

    public function timeEntries(): HasMany
    {
        return $this->hasMany(TimeEntry::class);
    }

    public function leaveRequests(): HasMany
    {
        return $this->hasMany(LeaveRequest::class);
    }

    /** Super Admins and Staff are the working team for timesheets, leave and attendance. Sub Admins are outside it. */
    public function scopeTeam(Builder $query): Builder
    {
        return $query->where('role', '!=', self::ROLE_SUB_ADMIN);
    }

    public function equipment(): HasMany
    {
        return $this->hasMany(Equipment::class);
    }

    /** Notes of meetings held with this person. */
    public function meetingNotes(): HasMany
    {
        return $this->hasMany(MeetingNote::class);
    }

    public const GENDERS = ['male' => 'Male', 'female' => 'Female', 'other' => 'Other'];

    public const BLOOD_GROUPS = ['A+', 'A-', 'B+', 'B-', 'AB+', 'AB-', 'O+', 'O-'];

    /**
     * Whether $this person may open and edit $other's profile and write meeting notes about them:
     * Super Admins for anyone, Admins for Staff, and leave/attendance managers for the working team.
     */
    public function managesProfileOf(User $other): bool
    {
        if (! $this->is_active || $this->is($other)) {
            return false;
        }

        return $this->isSuperAdmin()
            || ($this->isSubAdmin() && $other->role === self::ROLE_STAFF)
            || ($this->hasPermission('team.manage') && $other->role === self::ROLE_STAFF);
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
