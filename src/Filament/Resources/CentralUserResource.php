<?php

declare(strict_types=1);

namespace VmeRetail\MultitenancyKit\Filament\Resources;

use BackedEnum;
use Filament\Actions\EditAction;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use VmeRetail\MultitenancyKit\Filament\Resources\CentralUserResource\Pages\CreateCentralUser;
use VmeRetail\MultitenancyKit\Filament\Resources\CentralUserResource\Pages\EditCentralUser;
use VmeRetail\MultitenancyKit\Filament\Resources\CentralUserResource\Pages\ListCentralUsers;
use VmeRetail\MultitenancyKit\Models\CentralUser;

final class CentralUserResource extends Resource
{
    protected static ?string $model = null;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-users';

    protected static ?string $navigationLabel = 'Users';

    protected static ?string $modelLabel = 'User';

    public static function getModel(): string
    {
        return config('multitenancy-kit.central_user_model', CentralUser::class);
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
                TextInput::make('name')
                    ->required()
                    ->maxLength(255),

                TextInput::make('email')
                    ->email()
                    ->required()
                    ->unique(ignoreRecord: true)
                    ->maxLength(255),

                TextInput::make('password')
                    ->password()
                    ->required(fn (?CentralUser $record): bool => $record === null)
                    ->dehydrated(fn (?string $state): bool => filled($state))
                    ->maxLength(255),

                Toggle::make('is_admin')
                    ->label('Administrator')
                    ->default(false),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->searchable(),

                TextColumn::make('email')
                    ->searchable(),

                IconColumn::make('is_admin')
                    ->label('Admin')
                    ->boolean(),

                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable(),
            ])
            ->actions([
                EditAction::make(),
            ])
            ->bulkActions([]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListCentralUsers::route('/'),
            'create' => CreateCentralUser::route('/create'),
            'edit' => EditCentralUser::route('/{record}/edit'),
        ];
    }
}
