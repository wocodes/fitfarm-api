<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Terminal extends Model
{
    use HasFactory;

    protected $fillable = [
        'tenant_id',
        'uuid',
        'name',
        'token_hash',
        'device_fingerprint',
        'registered_by',
        'last_ip_address',
        'last_seen_at',
        'status',
    ];

    protected $casts = [
        'last_seen_at' => 'datetime',
    ];

    protected $hidden = [
        'token_hash',
    ];

    protected static function booted()
    {
        static::creating(function ($terminal) {
            if (empty($terminal->uuid)) {
                $terminal->uuid = (string) Str::uuid();
            }
        });
    }

    public function tenant()
    {
        return $this->belongsTo(Tenant::class);
    }

    public function registeredByUser()
    {
        return $this->belongsTo(TenantUser::class, 'registered_by');
    }

    /**
     * Generate a cryptographically secure token and its SHA-256 hash.
     *
     * @return array [plainToken, tokenHash]
     */
    public static function generateToken(): array
    {
        $plainToken = 'fct_' . bin2hex(random_bytes(24));
        $tokenHash = hash('sha256', $plainToken);

        return [$plainToken, $tokenHash];
    }

    /**
     * Verify whether a plaintext token matches this terminal's hash.
     */
    public function verifyToken(string $plainToken): bool
    {
        return hash_equals($this->token_hash, hash('sha256', $plainToken));
    }
}
