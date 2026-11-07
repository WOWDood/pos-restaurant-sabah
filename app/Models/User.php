<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Laravel\Fortify\Contracts\PasskeyUser;
use Laravel\Fortify\PasskeyAuthenticatable;
use Laravel\Fortify\TwoFactorAuthenticatable;

/**
 * @property int $id
 * @property string $name
 * @property string $email
 * @property Carbon|null $email_verified_at
 * @property string $password
 * @property string|null $two_factor_secret
 * @property string|null $two_factor_recovery_codes
 * @property Carbon|null $two_factor_confirmed_at
 * @property string|null $remember_token
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['name', 'code', 'email', 'password', 'user_type', 'email_verified_at', 'staff_role_id'])]
#[Hidden(['password', 'two_factor_secret', 'two_factor_recovery_codes', 'remember_token'])]
class User extends Authenticatable implements MustVerifyEmail, PasskeyUser
{
    public const TYPE_ADMIN = 'admin';

    public const TYPE_STAFF = 'staff';

    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable, PasskeyAuthenticatable, TwoFactorAuthenticatable;

    public function isAdministrator(): bool
    {
        return $this->user_type === self::TYPE_ADMIN;
    }

    public function staffRole(): BelongsTo
    {
        return $this->belongsTo(StaffRole::class);
    }

    public function hasPermission(string $permission): bool
    {
        if (! array_key_exists($permission, StaffRole::PERMISSIONS)) {
            return false;
        }

        if ($this->isAdministrator() || $this->staff_role_id === null) {
            return true;
        }

        $permissions = $this->staffRole?->permissions ?? [];

        return in_array($permission, $permissions, true)
            || ($permission === 'view-orders' && in_array('use-pos', $permissions, true));
    }

    public function roleName(): string
    {
        return $this->isAdministrator() ? 'Administrator' : ($this->staffRole?->name ?? 'Staff');
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'staff_role_id' => 'integer',
        ];
    }

    /**
     * Get the user's initials
     */
    public function initials(): string
    {
        $initials = Str::initials($this->name, true);

        return Str::length($initials) > 1
            ? Str::substr($initials, 0, 1).Str::substr($initials, -1)
            : $initials;
    }
}
