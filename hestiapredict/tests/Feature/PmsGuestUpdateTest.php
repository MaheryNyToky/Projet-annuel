<?php

namespace Tests\Feature;

use App\Models\Guest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class PmsGuestUpdateTest extends TestCase
{
    use RefreshDatabase;

    public function test_checkin_updates_guest_information_without_losing_visit_history(): void
    {
        $user = User::create([
            'name' => 'Reception Test',
            'email' => 'reception-test@example.com',
            'password' => 'password',
            'role' => 'receptionist',
            'is_blacklisted' => false,
        ]);

        $reservationId = $this->createReservation($user->id);

        Guest::create([
            'reservation_id' => $reservationId,
            'full_name' => 'Marie Razafindrakoto',
            'first_name' => 'Marie',
            'last_name' => 'Razafindrakoto',
            'phone_number' => '0341000001',
            'sex' => 'Femme',
            'id_document_number' => 'CIN-OLD-001',
            'loyalty_count' => 5,
            'date_of_birth' => '1988-01-01',
            'id_type' => 'CIN',
            'id_number' => 'CIN-OLD-001',
            'id_photo_path' => null,
        ]);

        $response = $this->postJson("/api/reservations/{$reservationId}/checkin", [
            'full_name' => 'Marie Razafindrakoto',
            'first_name' => 'Marie',
            'last_name' => 'Razafindrakoto',
            'customer_phone' => '0341000001',
            'phone_number' => '0341000001',
            'date_of_birth' => '1989-02-02',
            'sex' => 'Homme',
            'id_type' => 'CIN',
            'id_number' => 'CIN-OLD-001',
            'id_document_number' => 'CIN-OLD-001',
            'loyalty_count' => 5,
        ]);

        $response->assertOk();

        $guest = Guest::query()->where('reservation_id', $reservationId)->firstOrFail();
        $this->assertSame('1989-02-02', $guest->date_of_birth->toDateString());
        $this->assertSame('Homme', $guest->sex);
        $this->assertSame(5, (int) $guest->loyalty_count);

        $searchResponse = $this->getJson('/api/clients/search?q=CIN-OLD-001');
        $searchResponse->assertOk();
        $searchResponse->assertJsonPath('data.0.date_of_birth', '1989-02-02');
        $searchResponse->assertJsonPath('data.0.sex', 'Homme');
    }

    public function test_passport_validity_range_is_required_and_saved(): void
    {
        $user = User::create([
            'name' => 'Reception Test',
            'email' => 'reception-passport@example.com',
            'password' => 'password',
            'role' => 'receptionist',
            'is_blacklisted' => false,
        ]);

        $reservationId = $this->createReservation($user->id);

        $missingValidity = $this->postJson("/api/reservations/{$reservationId}/checkin", [
            'full_name' => 'Jean Rabe',
            'first_name' => 'Jean',
            'last_name' => 'Rabe',
            'customer_phone' => '0341000002',
            'phone_number' => '0341000002',
            'date_of_birth' => '1987-03-03',
            'sex' => 'Homme',
            'id_type' => 'Passeport',
            'id_number' => 'PP-NEW-777',
            'id_document_number' => 'PP-NEW-777',
            'passport_valid_from' => '2029-01-01',
            'loyalty_count' => 1,
        ]);

        $missingValidity->assertStatus(422);

        $response = $this->postJson("/api/reservations/{$reservationId}/checkin", [
            'full_name' => 'Jean Rabe',
            'first_name' => 'Jean',
            'last_name' => 'Rabe',
            'customer_phone' => '0341000002',
            'phone_number' => '0341000002',
            'date_of_birth' => '1987-03-03',
            'sex' => 'Homme',
            'id_type' => 'Passeport',
            'id_number' => 'PP-NEW-777',
            'id_document_number' => 'PP-NEW-777',
            'passport_valid_from' => '2029-01-01',
            'passport_valid_until' => '2029-12-31',
            'loyalty_count' => 1,
        ]);

        $response->assertOk();

        $guest = Guest::query()->where('reservation_id', $reservationId)->firstOrFail();
        $this->assertSame('2029-01-01', $guest->passport_valid_from?->toDateString());
        $this->assertSame('2029-12-31', $guest->passport_valid_until?->toDateString());
    }

    private function createReservation(int $userId): int
    {
        return DB::table('reservations')->insertGetId([
            'user_id' => $userId,
            'client_name' => 'Marie Razafindrakoto',
            'client_phone' => '0341000001',
            'customer_phone' => '0341000001',
            'customer_email' => 'marie@example.com',
            'booking_reference' => 'BR-' . uniqid(),
            'source' => 'direct',
            'check_in_date' => '2026-06-16',
            'check_out_date' => '2026-06-17',
            'status' => 'en_attente',
            'payment_status' => 'unbilled',
            'extra_beds' => 0,
            'extra_mattresses' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
