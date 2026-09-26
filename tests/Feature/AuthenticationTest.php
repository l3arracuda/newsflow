<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_create_admin_command_creates_account_with_hidden_password_prompts(): void
    {
        $this->artisan('newsflow:create-admin')
            ->expectsQuestion('Admin name', 'NewsFlow Admin')
            ->expectsQuestion('Admin email', 'admin@newsflow.test')
            ->expectsQuestion('Admin password (at least 12 characters)', 'CorrectHorseBatteryStaple!')
            ->expectsQuestion('Confirm password', 'CorrectHorseBatteryStaple!')
            ->expectsOutput('Admin account created.')
            ->assertExitCode(0);

        $this->assertDatabaseHas('users', [
            'name' => 'NewsFlow Admin',
            'email' => 'admin@newsflow.test',
            'is_admin' => true,
        ]);
    }

    public function test_admin_can_log_in_and_log_out(): void
    {
        $user = User::factory()->create([
            'email' => 'admin@example.test',
            'password' => 'CorrectHorseBatteryStaple!',
            'is_admin' => true,
        ]);

        $this->post('/login', [
            'email' => $user->email,
            'password' => 'CorrectHorseBatteryStaple!',
        ])->assertRedirect('/dashboard');

        $this->assertAuthenticatedAs($user);

        $this->post('/logout')->assertRedirect('/');
        $this->assertGuest();
    }
}
