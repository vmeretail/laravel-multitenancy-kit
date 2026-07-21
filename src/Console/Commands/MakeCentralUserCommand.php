<?php

declare(strict_types=1);

namespace VmeRetail\MultitenancyKit\Console\Commands;

use Illuminate\Console\Command;

use function Laravel\Prompts\password;
use function Laravel\Prompts\text;

final class MakeCentralUserCommand extends Command
{
    protected $signature = 'multitenancy-kit:make-user
        {--name= : The name of the user}
        {--email= : A valid and unique email address}
        {--password= : The password for the user}
        {--admin : Grant admin access to the landlord panel}';

    protected $description = 'Create a new central (landlord) user';

    public function handle(): int
    {
        $centralUserModel = config('multitenancy-kit.central_user_model');

        $user = $centralUserModel::create([
            'name' => $this->option('name') ?? text(
                label: 'Name',
                required: true,
            ),
            'email' => $this->option('email') ?? text(
                label: 'Email address',
                required: true,
                validate: ['email' => 'required|email|unique:'.(new $centralUserModel)->getTable().',email'],
            ),
            'password' => $this->option('password') ?? password(
                label: 'Password',
                required: true,
                validate: ['password' => 'required'],
            ),
            'is_admin' => $this->option('admin'),
        ]);

        $this->components->info("User [{$user->email}] created successfully.");

        if ($user->is_admin) {
            $this->components->info('Admin access granted.');
        }

        return self::SUCCESS;
    }
}
