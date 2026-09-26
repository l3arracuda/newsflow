<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_is_redirected_from_dashboard_to_login(): void
    {
        $this->get('/dashboard')
            ->assertRedirect('/login');
    }

    public function test_admin_can_view_dashboard(): void
    {
        $this->withoutVite()
            ->actingAs(User::factory()->create(['is_admin' => true]))
            ->get('/dashboard')
            ->assertOk()
            ->assertSee('NewsFlow')
            ->assertSee('แดชบอร์ด');
    }

    public function test_non_admin_user_cannot_view_dashboard(): void
    {
        $this->actingAs(User::factory()->create(['is_admin' => false]))
            ->get('/dashboard')
            ->assertForbidden();
    }

    public function test_public_registration_is_disabled(): void
    {
        $this->get('/register')->assertNotFound();
        $this->post('/register')->assertNotFound();
    }
}
