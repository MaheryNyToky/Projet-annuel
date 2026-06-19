<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardAdminLoginTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_is_redirected_to_dashboard_login(): void
    {
        $response = $this->get('/dashboard');

        $response->assertRedirect('/dashboard/login');
    }

    public function test_admin_can_login_and_access_dashboard(): void
    {
        $this->withoutMiddleware(ValidateCsrfToken::class);

        User::create([
            'name' => 'Admin Web',
            'email' => 'admin.web@example.com',
            'password' => 'secret123',
            'role' => 'admin',
            'is_blacklisted' => false,
        ]);

        $login = $this->post('/dashboard/login', [
            'email' => 'admin.web@example.com',
            'password' => 'secret123',
        ]);

        $login->assertRedirect('/dashboard');
        $this->assertAuthenticated();
        $this->assertSame('admin', auth()->user()->role);

        $dashboard = $this->get('/dashboard');
        $dashboard->assertOk();
        $dashboard->assertSee('Kamoro Hotel - Tableau de bord');
    }

    public function test_receptionist_cannot_access_dashboard_login(): void
    {
        $this->withoutMiddleware(ValidateCsrfToken::class);

        User::create([
            'name' => 'Reception Web',
            'email' => 'reception.web@example.com',
            'password' => 'secret123',
            'role' => 'receptionist',
            'is_blacklisted' => false,
        ]);

        $login = $this->post('/dashboard/login', [
            'email' => 'reception.web@example.com',
            'password' => 'secret123',
        ]);

        $login->assertSessionHasErrors('email');
        $this->assertGuest();
    }
}
