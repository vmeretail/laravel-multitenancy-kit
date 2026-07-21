<?php

declare(strict_types=1);

namespace VmeRetail\MultitenancyKit\Tests\Fixtures;

use Illuminate\Foundation\Auth\User as Authenticatable;

final class TenantUser extends Authenticatable
{
    protected $table = 'users';

    protected $guarded = [];
}
