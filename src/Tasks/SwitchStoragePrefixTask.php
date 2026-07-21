<?php

declare(strict_types=1);

namespace VmeRetail\MultitenancyKit\Tasks;

use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Spatie\Multitenancy\Contracts\IsTenant;
use Spatie\Multitenancy\Tasks\SwitchTenantTask;

final class SwitchStoragePrefixTask implements SwitchTenantTask
{
    /** @var array<string, string|null> */
    private array $originalRoots = [];

    /** @var list<string> */
    private array $diskNames;

    public function __construct()
    {
        $this->diskNames = ['public', 'private'];

        foreach ($this->diskNames as $disk) {
            $this->originalRoots[$disk] = config("filesystems.disks.{$disk}.root");
        }
    }

    public function makeCurrent(IsTenant $tenant): void
    {
        $slug = Str::slug($tenant->name);

        foreach ($this->diskNames as $disk) {
            config(["filesystems.disks.{$disk}.root" => "tenants/{$slug}"]);
            Storage::forgetDisk($disk);
        }
    }

    public function forgetCurrent(): void
    {
        foreach ($this->diskNames as $disk) {
            config(["filesystems.disks.{$disk}.root" => $this->originalRoots[$disk]]);
            Storage::forgetDisk($disk);
        }
    }
}
