<?php

declare(strict_types=1);

namespace VmeRetail\MultitenancyKit\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Spatie\Multitenancy\Models\Tenant as BaseTenant;

final class Tenant extends BaseTenant
{
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'domain',
        'database',
        'settings',
    ];

    public static function generateDatabaseName(string $slug): string
    {
        $landlordConnection = config('multitenancy-kit.landlord_database_connection_name');
        $landlordDatabase = config("database.connections.{$landlordConnection}.database");

        return $landlordDatabase.'__'.$slug;
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'settings' => 'json',
        ];
    }
}
