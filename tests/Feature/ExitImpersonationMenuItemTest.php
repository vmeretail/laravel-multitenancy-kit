<?php

declare(strict_types=1);

namespace VmeRetail\MultitenancyKit\Tests\Feature;

use Filament\Actions\Action;
use Filament\Events\ServingFilament;
use Filament\Facades\Filament;
use Filament\FilamentServiceProvider;
use Filament\Panel;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use ReflectionProperty;
use VmeRetail\MultitenancyKit\Tests\Fixtures\TenantUser;
use VmeRetail\MultitenancyKit\Tests\TestCase;

final class ExitImpersonationMenuItemTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config([
            'multitenancy.switch_tenant_tasks' => [],
            'multitenancy-kit.landlord_panel_id' => 'landlord',
            'auth.providers.tenant' => [
                'driver' => 'eloquent',
                'model' => TenantUser::class,
            ],
            'auth.guards.tenant' => [
                'driver' => 'session',
                'provider' => 'tenant',
            ],
        ]);

        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->string('password');
            $table->boolean('central_user')->default(false);
            $table->rememberToken();
            $table->timestamps();
        });
    }

    public function test_menu_item_is_registered_on_tenant_panel_for_impersonated_user(): void
    {
        $tenantPanel = $this->createAndRegisterPanel('tenant');

        $user = TenantUser::create([
            'name' => 'Central Admin',
            'email' => 'admin@example.com',
            'password' => 'password',
            'central_user' => true,
        ]);

        $this->actingAs($user, 'tenant');

        Filament::setCurrentPanel($tenantPanel);
        ServingFilament::dispatch();

        $this->assertTrue($this->panelHasExitImpersonationItem($tenantPanel));
    }

    public function test_menu_item_is_not_visible_for_regular_tenant_user(): void
    {
        $tenantPanel = $this->createAndRegisterPanel('tenant');

        $user = TenantUser::create([
            'name' => 'Tenant User',
            'email' => 'user@example.com',
            'password' => 'password',
            'central_user' => false,
        ]);

        $this->actingAs($user, 'tenant');

        Filament::setCurrentPanel($tenantPanel);
        ServingFilament::dispatch();

        $item = $this->getExitImpersonationItem($tenantPanel);

        $this->assertNotNull($item, 'The item should be registered on the panel.');
        $this->assertFalse($item->isVisible(), 'The item should not be visible for regular tenant users.');
    }

    public function test_menu_item_is_not_registered_on_landlord_panel(): void
    {
        $landlordPanel = $this->createAndRegisterPanel('landlord');

        Filament::setCurrentPanel($landlordPanel);
        ServingFilament::dispatch();

        $this->assertFalse($this->panelHasExitImpersonationItem($landlordPanel));
    }

    public function test_menu_item_is_not_registered_when_config_disabled(): void
    {
        config(['multitenancy-kit.show_exit_impersonation_menu_item' => false]);

        $tenantPanel = $this->createAndRegisterPanel('tenant');

        Filament::setCurrentPanel($tenantPanel);
        ServingFilament::dispatch();

        $this->assertFalse($this->panelHasExitImpersonationItem($tenantPanel));
    }

    protected function getPackageProviders($app): array
    {
        return [
            ...parent::getPackageProviders($app),
            FilamentServiceProvider::class,
        ];
    }

    private function createAndRegisterPanel(string $id): Panel
    {
        $panel = Panel::make()
            ->id($id)
            ->authGuard($id === 'landlord' ? 'web' : 'tenant');

        Filament::registerPanel($panel);

        return $panel;
    }

    /**
     * Read the raw user menu registrations from the panel to avoid triggering route resolution.
     *
     * @return array<Action>
     */
    private function getRawUserMenuItems(Panel $panel): array
    {
        return collect((new ReflectionProperty(Panel::class, 'userMenuItemGroups'))->getValue($panel))
            ->collapse()
            ->all();
    }

    private function panelHasExitImpersonationItem(Panel $panel): bool
    {
        return $this->getExitImpersonationItem($panel) !== null;
    }

    private function getExitImpersonationItem(Panel $panel): ?Action
    {
        foreach ($this->getRawUserMenuItems($panel) as $item) {
            if ($item instanceof Action && $item->getName() === 'exitImpersonation') {
                return $item;
            }
        }

        return null;
    }
}
