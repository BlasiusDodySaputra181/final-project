<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Setup\Account;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

#[Fillable(['identity_number', 'name', 'email', 'password', 'role'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, mixed>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    public const ROLE_OFFICER = 'Petugas';

    public const ROLE_COORDINATOR = 'Koordinator';

    public const ROLE_STUDENT = 'Mahasiswa';

    public function isOfficer(): bool
    {
        return Account::isOfficer($this);
    }

    public function isCoordinator(): bool
    {
        return Account::isCoordinator($this);
    }

    public function isStudent(): bool
    {
        return Account::isStudent($this);
    }

    public function isAdmin(): bool
    {
        return Account::isAdmin($this);
    }

    /**
     * Format an 18-digit identity number (NIP): XXXXXXXX XXXXXX X XXX
     */
    public function formattedIdentityNumber(): string
    {
        return Account::formattedIdentityNumber($this);
    }

    public function membership(): HasOne
    {
        return $this->hasOne(Membership::class);
    }

    public function visitors(): HasMany
    {
        return $this->hasMany(Visitor::class);
    }

    public function destructions(): HasMany
    {
        return $this->hasMany(Destruction::class);
    }

    public function reports(): HasMany
    {
        return $this->hasMany(Report::class);
    }
}
