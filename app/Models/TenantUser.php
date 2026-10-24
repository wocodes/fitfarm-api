<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;

class TenantUser extends Authenticatable
{
    use HasApiTokens, HasFactory, HasRoles, Notifiable;

    protected $guard_name = 'sanctum';

    protected $fillable = [
        'tenant_id',
        'name',
        'email',
        'phone',
        'gender',
        'password',
        'role',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $casts = [
        'password' => 'hashed',
    ];

    public function tenant()
    {
        return $this->belongsTo(Tenant::class);
    }

    /**
     * Determine whether this user is a platform-level super admin.
     */
    public function isSuperAdmin(): bool
    {
        return $this->tenant_id === null && in_array(strtolower((string) $this->role), [
            'super_admin',
            'superadmin',
            'role_super_admin',
        ], true);
    }

    /**
     * Determine whether this user can administer the active gym tenant.
     */
    public function isTenantOwner(): bool
    {
        return $this->isSuperAdmin()
            || strtolower((string) $this->role) === 'owner';
    }
}
