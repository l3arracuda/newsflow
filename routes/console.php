<?php

use App\Models\User;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('newsflow:create-admin', function () {
    $name = trim((string) $this->ask('Admin name'));

    while ($name === '') {
        $this->error('Admin name is required.');
        $name = trim((string) $this->ask('Admin name'));
    }

    $email = trim((string) $this->ask('Admin email'));

    while (! filter_var($email, FILTER_VALIDATE_EMAIL) || User::where('email', $email)->exists()) {
        $this->error(filter_var($email, FILTER_VALIDATE_EMAIL) ? 'That email address is already in use.' : 'Enter a valid email address.');
        $email = trim((string) $this->ask('Admin email'));
    }

    $password = (string) $this->secret('Admin password (at least 12 characters)');

    while (strlen($password) < 12) {
        $this->error('Use at least 12 characters.');
        $password = (string) $this->secret('Admin password (at least 12 characters)');
    }

    $confirmation = (string) $this->secret('Confirm password');

    while ($password !== $confirmation) {
        $this->error('The passwords do not match.');
        $confirmation = (string) $this->secret('Confirm password');
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
