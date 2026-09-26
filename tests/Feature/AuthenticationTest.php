<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

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
