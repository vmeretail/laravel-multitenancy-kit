<?php

declare(strict_types=1);

namespace VmeRetail\MultitenancyKit\Models;

use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Multitenancy\Models\Concerns\UsesLandlordConnection;

final class CentralUser extends Authenticatable implements FilamentUser
{
    use HasFactory;
    use Notifiable;
    use UsesLandlordConnection;

    protected $table = 'users';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'is_admin',
    ];

    /**
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    public function canAccessPanel(Panel $panel): bool
    {
        $panelId = config('multitenancy-kit.landlord_panel_id');

        if ($panel->getId() !== $panelId) {
            return false;
        }

        $accessClass = config('multitenancy-kit.landlord_panel_access');

        if ($accessClass) {
            return app($accessClass)->canAccess($this);
        }

        return $this->is_admin;
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_admin' => 'boolean',
        ];
    }
}
