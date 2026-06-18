<?php

namespace Tests\Feature;

use App\Models\Guest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class PmsLoyaltyCountTest extends TestCase
{
    use RefreshDatabase;

    public function test_loyalty_count_increments_only_when_invoice_becomes_paid(): void
    {
        $user = User::create([
            'name' => 'Reception Test',
            'email' => 'reception-test@example.com',
            'password' => 'password',
            'role' => 'receptionist',
            'is_blacklisted' => false,
        ]);

        $reservationId = $this->createReservation($user->id);

        $checkInResponse = $this->postJson("/api/reservations/{$reservationId}/checkin", [
            'full_name' => 'Alice Dupont',
            'customer_phone' => '0340000001',
            'phone_number' => '0340000001',
            'date_of_birth' => '1990-01-01',
            'sex' => 'Femme',
            'id_type' => 'CIN',
            'id_number' => 'CIN-123456',
            'id_document_number' => 'CIN-123456',
            'loyalty_count' => 7,
            'first_name' => 'Alice',
            'last_name' => 'Dupont',
        ]);

        $checkInResponse->assertOk();
        $this->assertSame(7, (int) Guest::query()->where('reservation_id', $reservationId)->value('loyalty_count'));

        $invoiceId = DB::table('invoices')->insertGetId([
            'reservation_id' => $reservationId,
            'invoice_number' => null,
            'total_amount_ariary' => 1000,
            'tax_amount_ariary' => 0,
            'discount_mode' => null,
            'discount_value' => null,
            'discount_amount_ariary' => 0,
            'deposit_amount_ariary' => 0,
            'pdf_path' => null,
            'finalized_at' => null,
            'status' => 'open',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $firstPayment = $this->postJson("/api/invoices/{$invoiceId}/payments", [
            'amount_ariary' => 400,
            'payment_method' => 'Espèces',
        ]);

        $firstPayment->assertOk();
        $this->assertSame(7, (int) Guest::query()->where('reservation_id', $reservationId)->value('loyalty_count'));

        $secondPayment = $this->postJson("/api/invoices/{$invoiceId}/payments", [
            'amount_ariary' => 600,
            'payment_method' => 'Espèces',
        ]);

        $secondPayment->assertOk();
        $this->assertSame(8, (int) Guest::query()->where('reservation_id', $reservationId)->value('loyalty_count'));
    }

    private function createReservation(int $userId): int
    {
        return DB::table('reservations')->insertGetId([
            'user_id' => $userId,
            'client_name' => 'Alice Dupont',
            'client_phone' => '0340000001',
            'customer_phone' => '0340000001',
            'customer_email' => 'alice@example.com',
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
