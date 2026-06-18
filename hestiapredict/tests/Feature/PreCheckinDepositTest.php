<?php

namespace Tests\Feature;

use App\Models\Reservation;
use App\Models\Room;
use App\Models\User;
use App\Models\Invoice;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PreCheckinDepositTest extends TestCase
{
    use RefreshDatabase;

    public function test_deposit_can_be_recorded_before_checkin_and_tracks_validator_role(): void
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

        $reservation = Reservation::create([
            'user_id' => $user->id,
            'client_name' => 'Paul Razaf',
            'client_phone' => '0340000003',
            'customer_phone' => '0340000003',
            'customer_email' => 'paul@example.com',
            'booking_reference' => 'BR-' . uniqid(),
            'source' => 'Appel',
            'check_in_date' => '2026-06-20',
            'check_out_date' => '2026-06-21',
            'status' => 'en_attente',
            'payment_status' => 'unbilled',
            'extra_beds' => 0,
            'extra_mattresses' => 0,
        ]);

        $reservation->rooms()->attach($room->id, [
            'price_snapshot_ariary' => 50000,
        ]);

        $folioResponse = $this->getJson("/api/reservations/{$reservation->id}/folio");
        $folioResponse->assertOk();

        $invoiceId = $folioResponse->json('id');
        $this->assertNotEmpty($invoiceId);

        $paymentResponse = $this->postJson("/api/reservations/{$reservation->id}/deposit", [
            'amount_ariary' => 20000,
            'payment_method' => 'Espèces',
            'processed_by_name' => 'Réception Test',
            'processed_by_role' => 'receptionist',
            'reference' => 'ACPT-001',
        ]);

        $paymentResponse->assertOk();
        $paymentResponse->assertJsonPath('payment.amount_ariary', 20000);
        $paymentResponse->assertJsonPath('payment.payment_method', 'Espèces');
        $paymentResponse->assertJsonPath('payment.payment_context', 'deposit');
        $paymentResponse->assertJsonPath('payment.processed_by_name', 'Réception Test');
        $paymentResponse->assertJsonPath('payment.processed_by_role', 'receptionist');
        $paymentResponse->assertJsonPath('invoice.status', 'partial');
        $paymentResponse->assertJsonPath('invoice.deposit_amount_ariary', 20000);
        $paymentResponse->assertJsonPath('invoice.balance_amount_ariary', 30000);

        $invoice = Invoice::query()->findOrFail($invoiceId);
        $this->assertNotNull($invoice->invoice_number);
        $this->assertNotNull($invoice->pdf_path);
        $this->assertTrue(Storage::disk('local')->exists($invoice->pdf_path));
    }
}
