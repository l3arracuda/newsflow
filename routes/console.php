<?php

use App\Models\User;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('newsflow:create-admin', function () {
    $name = text('Admin name', required: true);
    $email = text('Admin email', validate: function (string $value): ?string {
        if (! filter_var($value, FILTER_VALIDATE_EMAIL)) {
            return 'Enter a valid email address.';
        }

        return User::where('email', $value)->exists() ? 'That email address is already in use.' : null;
    });
    $password = password('Admin password (at least 12 characters)', validate: fn (string $value): ?string => strlen($value) >= 12 ? null : 'Use at least 12 characters.');
    $confirmation = password('Confirm password');

    if ($password !== $confirmation) {
        $this->error('The passwords do not match.');

        return 1;
    }

    User::create([
        'name' => $name,
        'email' => $email,
        'password' => $password,
        'is_admin' => true,
    ]);

    $this->info('Admin account created.');

    return 0;
})->purpose('Create an administrator account using hidden password prompts');

Artisan::command('newsflow:check-environment', function () {
    $errors = [];

    if (blank(config('app.key'))) {
        $errors[] = 'APP_KEY is missing. Run php artisan key:generate.';
    }

    if (config('database.default') !== 'mysql') {
        $errors[] = 'DB_CONNECTION must be mysql for the application runtime.';
    }

    foreach (['host', 'database', 'username'] as $setting) {
        if (blank(config("database.connections.mysql.{$setting}"))) {
            $errors[] = "MySQL {$setting} is not configured.";
        }
    }

    if ($errors !== []) {
        foreach ($errors as $error) {
            $this->error($error);
        }

        return 1;
    }

    try {
        DB::connection('mysql')->getPdo();
    } catch (Throwable) {
        $this->error('MySQL connection failed. Check the database service and .env settings.');

        return 1;
    }

    $this->info('Environment configuration is valid and MySQL is reachable.');

    return 0;
})->purpose('Validate required application settings and the MySQL connection');
