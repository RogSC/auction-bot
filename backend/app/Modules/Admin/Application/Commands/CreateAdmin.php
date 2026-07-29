<?php

declare(strict_types=1);

namespace App\Modules\Admin\Application\Commands;

use App\Models\Admin;
use Illuminate\Console\Command;

class CreateAdmin extends Command
{
    protected $signature = 'admin:create
        {email : Email address of the administrator}
        {name : Display name of the administrator}
        {--password= : Password; omitted means it will be requested securely}';

    protected $description = 'Create an active administrator for the Filament panel';

    public function handle(): int
    {
        $email = mb_strtolower((string) $this->argument('email'));

        if (Admin::query()->where('email', $email)->exists()) {
            $this->components->error('An administrator with this email already exists.');

            return self::FAILURE;
        }

        $password = $this->option('password') ?? $this->secret('Password');

        if (! is_string($password) || $password === '') {
            $this->components->error('Password must not be empty.');

            return self::FAILURE;
        }

        Admin::query()->create([
            'email' => $email,
            'name' => (string) $this->argument('name'),
            'password' => $password,
            'is_active' => true,
        ]);

        $this->components->info("Administrator {$email} was created.");

        return self::SUCCESS;
    }
}
