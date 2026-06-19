<?php

namespace Tests\Feature;

use App\Models\Reservation;
use App\Models\Room;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExtraCapacityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-06-19 12:00:00');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_booking_creation_rejects_when_extra_beds_exceed_night_capacity(): void
    {
        $user = User::create([
            'name' => 'Reception Test',
            'email' => 'reception-extra-beds@example.com',
            'password' => 'password',
            'role' => 'receptionist',
            'is_blacklisted' => false,
        ]);

        $room1 = Room::create([
            'room_number' => '601',
            'type' => 'Chambre Double',
            'model' => 'Standard',
            'base_price_ariary' => 50000,
            'is_fixed_price' => false,
        ]);

        $room2 = Room::create([
            'room_number' => '602',
            'type' => 'Chambre Double',
            'model' => 'Standard',
            'base_price_ariary' => 50000,
            'is_fixed_price' => false,
        ]);

        $reservation = Reservation::create([
            'user_id' => $user->id,
            'client_name' => 'Existing Client',
            'client_phone' => '0340000100',
            'customer_phone' => '0340000100',
            'customer_email' => 'existing@example.com',
            'booking_reference' => 'BR-' . uniqid(),
            'source' => 'Appel',
            'check_in_date' => '2026-06-20',
            'check_out_date' => '2026-06-22',
            'status' => 'en_attente',
            'payment_status' => 'unbilled',
            'extra_beds' => 2,
            'extra_mattresses' => 0,
        ]);

        $reservation->rooms()->attach($room1->id, [
            'price_snapshot_ariary' => 50000,
        ]);

        $response = $this->postJson('/api/bookings', [
            'client_name' => 'Overflow Client',
            'customer_phone' => '0340000101',
            'customer_email' => 'overflow@example.com',
            'check_in' => '2026-06-20',
            'check_out' => '2026-06-22',
            'room_ids' => [$room2->id],
            'room_prices' => [
                ['id' => $room2->id, 'price' => 50000],
            ],
            'extra_beds' => 5,
            'extra_mattresses' => 0,
            'source' => 'Appel',
            'receptionist_name' => $user->name,
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('extra_beds');
    }

    public function test_reservation_update_rejects_when_extra_mattresses_exceed_night_capacity(): void
    {
        $user = User::create([
            'name' => 'Reception Test',
            'email' => 'reception-extra-mattresses@example.com',
            'password' => 'password',
            'role' => 'receptionist',
            'is_blacklisted' => false,
        ]);

        $room1 = Room::create([
            'room_number' => '701',
            'type' => 'Chambre Double',
            'model' => 'Standard',
            'base_price_ariary' => 50000,
            'is_fixed_price' => false,
        ]);

        $room2 = Room::create([
            'room_number' => '702',
            'type' => 'Chambre Double',
            'model' => 'Standard',
            'base_price_ariary' => 55000,
            'is_fixed_price' => false,
        ]);

        $room3 = Room::create([
            'room_number' => '703',
            'type' => 'Chambre Double',
            'model' => 'Deluxe',
            'base_price_ariary' => 65000,
            'is_fixed_price' => false,
        ]);

        $blockingReservation = Reservation::create([
            'user_id' => $user->id,
            'client_name' => 'Blocking Client',
            'client_phone' => '0340000110',
            'customer_phone' => '0340000110',
            'customer_email' => 'blocker@example.com',
            'booking_reference' => 'BR-' . uniqid(),
            'source' => 'Appel',
            'check_in_date' => '2026-06-20',
            'check_out_date' => '2026-06-22',
            'status' => 'en_attente',
            'payment_status' => 'unbilled',
            'extra_beds' => 0,
            'extra_mattresses' => 4,
        ]);
        $blockingReservation->rooms()->attach($room1->id, [
            'price_snapshot_ariary' => 50000,
        ]);

        $reservation = Reservation::create([
            'user_id' => $user->id,
            'client_name' => 'Target Client',
            'client_phone' => '0340000111',
            'customer_phone' => '0340000111',
            'customer_email' => 'target@example.com',
            'booking_reference' => 'BR-' . uniqid(),
            'source' => 'Appel',
            'check_in_date' => '2026-06-20',
            'check_out_date' => '2026-06-22',
            'status' => 'en_attente',
            'payment_status' => 'unbilled',
            'extra_beds' => 0,
            'extra_mattresses' => 1,
        ]);
        $reservation->rooms()->attach($room2->id, [
            'price_snapshot_ariary' => 55000,
        ]);

        $response = $this->putJson("/api/reservations/{$reservation->id}", [
            'client_name' => 'Target Client',
            'customer_phone' => '0340000111',
            'customer_email' => 'target@example.com',
            'check_in' => '2026-06-20',
            'check_out' => '2026-06-22',
            'room_ids' => [$room2->id, $room3->id],
            'extra_beds' => 0,
            'extra_mattresses' => 3,
            'modified_by_name' => $user->name,
            'modified_by_role' => $user->role,
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('extra_mattresses');
    }

    public function test_extras_capacity_endpoint_reports_remaining_stock(): void
    {
        $user = User::create([
            'name' => 'Reception Test',
            'email' => 'reception-capacity@example.com',
            'password' => 'password',
            'role' => 'receptionist',
            'is_blacklisted' => false,
        ]);

        $room = Room::create([
            'room_number' => '801',
            'type' => 'Chambre Double',
            'model' => 'Standard',
            'base_price_ariary' => 50000,
            'is_fixed_price' => false,
        ]);

        $reservation = Reservation::create([
            'user_id' => $user->id,
            'client_name' => 'Capacity Client',
            'client_phone' => '0340000120',
            'customer_phone' => '0340000120',
            'customer_email' => 'capacity@example.com',
            'booking_reference' => 'BR-' . uniqid(),
            'source' => 'Appel',
            'check_in_date' => '2026-06-20',
            'check_out_date' => '2026-06-22',
            'status' => 'en_attente',
            'payment_status' => 'unbilled',
            'extra_beds' => 2,
            'extra_mattresses' => 4,
        ]);
        $reservation->rooms()->attach($room->id, [
            'price_snapshot_ariary' => 50000,
        ]);

        $response = $this->getJson('/api/dashboard/extras-capacity?check_in=2026-06-20&check_out=2026-06-22');

        $response->assertOk();
        $response->assertJson([
            'status' => 'success',
            'remaining_beds' => 4,
            'remaining_mattresses' => 2,
            'max_beds' => 6,
            'max_mattresses' => 6,
        ]);
    }
}
