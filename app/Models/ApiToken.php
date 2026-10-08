<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

class ApiToken extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'name',
        'token_hash',
        'expires_at',
        'last_used_at',
        'last_used_ip',
        'trusted_ip',
        'revoked_at',
    ];

    protected $casts = [
        'expires_at' => 'datetime',
        'last_used_at' => 'datetime',
        'revoked_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isActive(): bool
    {
        if ($this->revoked_at !== null) {
            return false;
        }

        if ($this->expires_at !== null && $this->expires_at->isPast()) {
            return false;
        }

        return true;
    }

    public function isExpired(): bool
    {
        return $this->expires_at !== null && $this->expires_at->isPast();
    }

    public function markUsed(?string $ip): void
    {
        $now = now();
        $shouldUpdateUsage = $this->last_used_at === null || $this->last_used_at->lt($now->copy()->subMinute());

        if (! $shouldUpdateUsage && $this->last_used_ip === $ip) {
            return;
        }

        $this->forceFill([
            'last_used_at' => $now,
            'last_used_ip' => $ip,
        ])->save();
    }

    public function isRequestIpAllowed(?string $requestIp): bool
    {
        $trustedIp = trim((string) $this->trusted_ip);
        $requestIp = trim((string) $requestIp);

        if ($trustedIp === '') {
            return true;
        }

        if ($requestIp === '') {
            return false;
        }

        $trustedBinary = @inet_pton($trustedIp);
        $requestBinary = @inet_pton($requestIp);

        if ($trustedBinary !== false && $requestBinary !== false) {
            return hash_equals($trustedBinary, $requestBinary);
        }

        return $trustedIp === $requestIp;
    }

    /**
     * @return array{plain_text: string, hash: string}
     */
    public static function issueToken(?string $prefix = null): array
    {
        $prefix = $prefix ?? (string) config('api.tokens.prefix', 'bl_live_');
        $secret = Str::random(64);
        $plain = $prefix.$secret;

        return [
            'plain_text' => $plain,
            'hash' => hash('sha256', $plain),
        ];
    }

    public static function defaultExpiresAt(): Carbon
    {
        $ttlDays = max(1, (int) config('api.tokens.default_ttl_days', 365));

        return now()->addDays($ttlDays);
    }
}
