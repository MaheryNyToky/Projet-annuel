<?php

namespace Tests\Feature;

use App\Models\Reservation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReservationCancellationAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_only_admin_can_cancel_an_arrived_reservation(): void
    {
        $receptionist = User::create([
            'name' => 'Reception Test',
            'email' => 'reception-cancel@example.com',
            'password' => 'password',
            'role' => 'receptionist',
            'is_blacklisted' => false,
        ]);

        $admin = User::create([
            'name' => 'Admin Test',
            'email' => 'admin-cancel@example.com',
            'password' => 'password',
            'role' => 'admin',
            'is_blacklisted' => false,
        ]);

        $reservation = Reservation::create([
            'user_id' => $receptionist->id,
            'client_name' => 'Cancel Client',
            'client_phone' => '0340000009',
            'customer_phone' => '0340000009',
            'customer_email' => 'cancel@example.com',
            'booking_reference' => 'BR-' . uniqid(),
            'source' => 'direct',
            'check_in_date' => '2026-06-16',
            'check_out_date' => '2026-06-17',
            'status' => 'arrive',
            'payment_status' => 'paid',
            'extra_beds' => 0,
            'extra_mattresses' => 0,
        ]);

        $blocked = $this->postJson('/api/bookings/update-status', [
            'id' => $reservation->id,
            'status' => 'annule',
            'cancelled_by_name' => $receptionist->name,
            'cancelled_by_role' => $receptionist->role,
        ]);

        $blocked->assertStatus(403);
        $this->assertSame('arrive', Reservation::query()->findOrFail($reservation->id)->status);

        $allowed = $this->postJson('/api/bookings/update-status', [
            'id' => $reservation->id,
            'status' => 'annule',
            'cancelled_by_name' => $admin->name,
            'cancelled_by_role' => $admin->role,
        ]);

        $allowed->assertOk();
        $this->assertSame('annule', Reservation::query()->findOrFail($reservation->id)->status);
    }
}
