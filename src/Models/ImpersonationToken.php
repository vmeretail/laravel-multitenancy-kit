<?php

declare(strict_types=1);

namespace VmeRetail\MultitenancyKit\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use Spatie\Multitenancy\Models\Concerns\UsesLandlordConnection;

final class ImpersonationToken extends Model
{
    use UsesLandlordConnection;

    public $incrementing = false;

    public $timestamps = false;

    protected $table = 'tenant_user_impersonation_tokens';

    protected $primaryKey = 'token';

    protected $keyType = 'string';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'tenant_id',
        'tenant_user_id',
        'auth_guard',
        'redirect_url',
    ];

    protected static function booted(): void
    {
        self::creating(function (self $token): void {
            $token->token = Str::random(128);
            $token->created_at = now();
        });
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'created_at' => 'datetime',
        ];
    }
}
