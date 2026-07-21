<?php

declare(strict_types=1);

namespace VmeRetail\MultitenancyKit\Filament\Resources;

use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\EditAction;
use Filament\Forms\Components;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Str;
use VmeRetail\MultitenancyKit\Actions\CreateImpersonationToken;
use VmeRetail\MultitenancyKit\Actions\RunTenantMigrations;
use VmeRetail\MultitenancyKit\Actions\RunTenantSeeders;
use VmeRetail\MultitenancyKit\Actions\SyncCentralUsersToTenant;
use VmeRetail\MultitenancyKit\Filament\Resources\TenantResource\Pages;
use VmeRetail\MultitenancyKit\Models\Tenant;

final class TenantResource extends Resource
{
    protected static ?string $model = null;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-building-office';

    public static function getModel(): string
    {
        return config('multitenancy-kit.tenant_model', Tenant::class);
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Components\TextInput::make('name')
                    ->required()
                    ->maxLength(255)
                    ->live()
                    ->afterStateUpdated(function (?string $state, Set $set): void {
                        $slug = Str::slug($state ?? '');
                        $set('domain', $slug.'.'.config('multitenancy-kit.central_domain'));
                        $set('database', Tenant::generateDatabaseName(Str::slug($state ?? '', '_')));
                    }),

                Components\TextInput::make('domain')
                    ->required()
                    ->unique(ignoreRecord: true)
                    ->maxLength(255)
                    ->disabled(fn (?Tenant $record): bool => $record !== null)
                    ->dehydrated(),

                Components\TextInput::make('database')
                    ->required()
                    ->unique(ignoreRecord: true)
                    ->maxLength(255)
                    ->disabled()
                    ->dehydrated()
                    ->visibleOn('create'),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->searchable(),

                TextColumn::make('domain')
                    ->searchable(),

                TextColumn::make('database')
                    ->searchable(),

                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable(),
            ])
            ->actions([
                ActionGroup::make([
                    EditAction::make(),

                    Action::make('login')
                        ->label('Log In')
                        ->icon('heroicon-o-arrow-right-on-rectangle')
                        ->color('success')
                        ->requiresConfirmation()
                        ->action(function (Tenant $record): void {
                            $centralUser = auth()->user();
                            $tenantUserModel = config('multitenancy-kit.tenant_user_model');

                            $tenantUser = null;
                            $record->execute(function () use ($centralUser, $tenantUserModel, &$tenantUser): void {
                                $tenantUser = $tenantUserModel::where('email', $centralUser->email)->first();
                            });

                            if (! $tenantUser) {
                                return;
                            }

                            $token = app(CreateImpersonationToken::class)->execute(
                                tenant: $record,
                                tenantUserId: $tenantUser->id,
                            );

                            $url = tenant_route($record->domain, 'multitenancy-kit.impersonate', ['token' => $token->token]);

                            redirect($url);
                        }),

                    Action::make('runMigrations')
                        ->label('Run Migrations')
                        ->icon('heroicon-o-command-line')
                        ->color('warning')
                        ->requiresConfirmation()
                        ->action(fn (Tenant $record) => app(RunTenantMigrations::class)->execute($record)),

                    Action::make('runSeeders')
                        ->label('Run Seeders')
                        ->icon('heroicon-o-circle-stack')
                        ->color('warning')
                        ->requiresConfirmation()
                        ->visible(fn (): bool => config('multitenancy-kit.tenant_seeder') !== null)
                        ->action(fn (Tenant $record) => app(RunTenantSeeders::class)->execute($record)),

                    Action::make('syncUsers')
                        ->label('Sync Users')
                        ->icon('heroicon-o-users')
                        ->color('warning')
                        ->requiresConfirmation()
                        ->visible(fn (): bool => config('multitenancy-kit.tenant_user_model') !== null)
                        ->successNotificationTitle('Users sync has been queued')
                        ->action(fn (Tenant $record) => app(SyncCentralUsersToTenant::class)->execute($record)),
                ]),
            ])
            ->bulkActions([]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListTenants::route('/'),
            'create' => Pages\CreateTenant::route('/create'),
            'edit' => Pages\EditTenant::route('/{record}/edit'),
        ];
    }
}
