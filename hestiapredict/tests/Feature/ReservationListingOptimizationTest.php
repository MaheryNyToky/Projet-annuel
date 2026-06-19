<?php

namespace Tests\Feature;

use App\Models\Reservation;
use App\Models\Room;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReservationListingOptimizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_reservations_all_filters_pending_status_on_server(): void
    {
        $user = User::create([
            'name' => 'Reception Test',
            'email' => 'reservation-listing@example.com',
            'password' => 'password',
            'role' => 'receptionist',
            'is_blacklisted' => false,
        ]);

        $room1 = $this->createRoom('901');
        $room2 = $this->createRoom('902');

        $pending = $this->createReservation($user->id, 'Pending Client', 'en_attente');
        $pending->rooms()->attach($room1->id, ['price_snapshot_ariary' => 50000]);

        $arrived = $this->createReservation($user->id, 'Arrived Client', 'arrive');
        $arrived->rooms()->attach($room2->id, ['price_snapshot_ariary' => 50000]);

        $response = $this->getJson('/api/reservations/all?date=2026-06-20&status=pending');

        $response->assertOk();
        $response->assertJsonCount(1);
        $response->assertJsonFragment(['client_name' => 'Pending Client']);
        $response->assertJsonMissing(['client_name' => 'Arrived Client']);
    }

    public function test_reservations_all_status_returns_every_reservation_for_selected_date(): void
    {
        $user = User::create([
            'name' => 'Reception Test',
            'email' => 'reservation-listing-all@example.com',
            'password' => 'password',
            'role' => 'receptionist',
            'is_blacklisted' => false,
        ]);

        $this->createReservation($user->id, 'Pending Client', 'en_attente');
        $this->createReservation($user->id, 'Arrived Client', 'arrive');
        $this->createReservation($user->id, 'Paid Client', 'arrive', 'paid');
        $this->createReservation($user->id, 'Cancelled Client', 'annule');

        $response = $this->getJson('/api/reservations/all?date=2026-06-20&status=all');

        $response->assertOk();
        $response->assertJsonCount(4);
        $response->assertJsonFragment(['client_name' => 'Pending Client']);
        $response->assertJsonFragment(['client_name' => 'Arrived Client']);
        $response->assertJsonFragment(['client_name' => 'Paid Client']);
        $response->assertJsonFragment(['client_name' => 'Cancelled Client']);
    }

    public function test_reservations_all_filters_paid_status_on_server(): void
    {
        $user = User::create([
            'name' => 'Reception Test',
            'email' => 'reservation-listing-paid@example.com',
            'password' => 'password',
            'role' => 'receptionist',
            'is_blacklisted' => false,
        ]);

        $this->createReservation($user->id, 'Unpaid Client', 'arrive', 'unpaid');
        $this->createReservation($user->id, 'Paid Client', 'arrive', 'paid');

        $response = $this->getJson('/api/reservations/all?date=2026-06-20&status=paid');

        $response->assertOk();
        $response->assertJsonCount(1);
        $response->assertJsonFragment(['client_name' => 'Paid Client']);
        $response->assertJsonMissing(['client_name' => 'Unpaid Client']);
    }

    public function test_reservation_status_summary_returns_counts_without_full_listing(): void
    {
        $user = User::create([
            'name' => 'Reception Test',
            'email' => 'reservation-summary@example.com',
            'password' => 'password',
            'role' => 'receptionist',
            'is_blacklisted' => false,
        ]);

        $this->createReservation($user->id, 'Pending Client', 'en_attente');
        $this->createReservation($user->id, 'Arrived Client', 'arrive');
        $this->createReservation($user->id, 'Cancelled Client', 'annule');

        $response = $this->getJson('/api/dashboard/reservation-status-summary?date=2026-06-20');

        $response->assertOk();
        $response->assertJson([
            'date' => '2026-06-20',
            'pending' => 1,
            'arrived' => 1,
        ]);
    }

    private function createRoom(string $number): Room
    {
        return Room::create([
            'room_number' => $number,
            'type' => 'Chambre Double',
            'model' => 'Standard',
            'base_price_ariary' => 50000,
            'is_fixed_price' => false,
        ]);
    }

    private function createReservation(
        int $userId,
        string $name,
        string $status,
        string $paymentStatus = 'unbilled',
    ): Reservation
    {
        return Reservation::create([
            'user_id' => $userId,
            'client_name' => $name,
            'client_phone' => '0340000999',
            'customer_phone' => '0340000999',
            'customer_email' => strtolower(str_replace(' ', '.', $name)) . '@example.com',
            'booking_reference' => 'BR-' . uniqid(),
            'source' => 'Appel',
            'check_in_date' => '2026-06-20',
            'check_out_date' => '2026-06-21',
            'status' => $status,
            'payment_status' => $paymentStatus,
            'extra_beds' => 0,
            'extra_mattresses' => 0,
        ]);
    }
}
