<?php

namespace Tests\Feature;

use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Reservation;
use App\Models\Room;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ClientHistorySearchTest extends TestCase
{
    use RefreshDatabase;

    public function test_client_history_search_returns_past_present_and_future_reservations(): void
    {
        $user = User::create([
            'name' => 'Reception Test',
            'email' => 'reception-test@example.com',
            'password' => 'password',
            'role' => 'receptionist',
            'is_blacklisted' => false,
        ]);

        $room = Room::create([
            'room_number' => '101',
            'type' => 'Chambre Double',
            'model' => 'Standard',
            'base_price_ariary' => 50000,
            'is_fixed_price' => false,
        ]);

        $past = $this->createReservation($user->id, $room->id, 'Marius Rakoto', '2026-06-10', '2026-06-11');
        $present = $this->createReservation($user->id, $room->id, 'Marius Rakoto', '2026-06-16', '2026-06-18');
        $future = $this->createReservation($user->id, $room->id, 'Marius Rakoto', '2026-06-20', '2026-06-22');

        $invoice = Invoice::create([
            'reservation_id' => $present->id,
            'invoice_number' => 'FACT-2026-0001',
            'total_amount_ariary' => 1000,
            'tax_amount_ariary' => 0,
            'discount_mode' => null,
            'discount_value' => null,
            'discount_amount_ariary' => 0,
            'deposit_amount_ariary' => 0,
            'pdf_path' => null,
            'finalized_at' => null,
            'status' => 'paid',
            'document_type' => 'facture',
        ]);

        InvoiceItem::create([
            'invoice_id' => $invoice->id,
            'description' => 'Séjour chambre',
            'type' => 'room',
            'amount_ariary' => 1000,
            'quantity' => 1,
        ]);

        $response = $this->getJson('/api/dashboard/client-history?q=Marius%20Rakoto');

        $response->assertOk();
        $response->assertJsonPath('status', 'success');
        $response->assertJsonCount(3, 'data');
        $response->assertJsonPath('data.0.period', 'futur');
        $response->assertJsonPath('data.1.period', 'présent');
        $response->assertJsonPath('data.2.period', 'passé');
        $response->assertJsonPath('data.1.invoice_status', 'paid');
        $response->assertJsonPath('data.0.invoice_number', null);
        $this->assertSame($past->id, (int) $response->json('data.2.id'));
        $this->assertSame($future->id, (int) $response->json('data.0.id'));
    }

    private function createReservation(int $userId, int $roomId, string $clientName, string $checkIn, string $checkOut): Reservation
    {
        $reservation = Reservation::create([
            'user_id' => $userId,
            'client_name' => $clientName,
            'client_phone' => '0340000001',
            'customer_phone' => '0340000001',
            'customer_email' => 'client@example.com',
            'booking_reference' => 'BR-' . uniqid(),
            'source' => 'direct',
            'check_in_date' => $checkIn,
            'check_out_date' => $checkOut,
            'status' => 'en_attente',
            'payment_status' => 'unbilled',
            'extra_beds' => 0,
            'extra_mattresses' => 0,
        ]);

        $reservation->rooms()->attach($roomId, [
            'price_snapshot_ariary' => 50000,
        ]);

        return $reservation;
    }
}
