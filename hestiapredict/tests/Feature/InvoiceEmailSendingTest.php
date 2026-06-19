<?php

namespace Tests\Feature;

use App\Models\Invoice;
use App\Models\Reservation;
use App\Models\Room;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class InvoiceEmailSendingTest extends TestCase
{
    use RefreshDatabase;

    public function test_invoice_email_can_be_sent_when_pdf_exists(): void
    {
        Storage::fake('local');
        Mail::shouldReceive('raw')->once()->andReturnNull();

        $user = User::create([
            'name' => 'Admin Test',
            'email' => 'admin-email@example.com',
            'password' => 'password',
            'role' => 'admin',
            'is_blacklisted' => false,
        ]);

        $room = Room::create([
            'room_number' => '601',
            'type' => 'Chambre Double',
            'model' => 'Standard',
            'base_price_ariary' => 50000,
            'is_fixed_price' => false,
        ]);

        $reservation = Reservation::create([
            'user_id' => $user->id,
            'client_name' => 'Email Client',
            'client_phone' => '0340000013',
            'customer_phone' => '0340000013',
            'customer_email' => 'email@example.com',
            'booking_reference' => 'BR-' . uniqid(),
            'source' => 'direct',
            'check_in_date' => '2026-06-16',
            'check_out_date' => '2026-06-17',
            'status' => 'arrive',
            'payment_status' => 'paid',
            'extra_beds' => 0,
            'extra_mattresses' => 0,
        ]);

        $reservation->rooms()->attach($room->id, [
            'price_snapshot_ariary' => 50000,
        ]);

        $invoice = Invoice::create([
            'reservation_id' => $reservation->id,
            'invoice_number' => 'FACT-EMAIL-0001',
            'total_amount_ariary' => 50000,
            'tax_amount_ariary' => 0,
            'discount_mode' => null,
            'discount_value' => null,
            'discount_amount_ariary' => 0,
            'deposit_amount_ariary' => 0,
            'pdf_path' => 'invoices/FACT-EMAIL-0001.pdf',
            'finalized_at' => now(),
            'status' => 'paid',
            'document_type' => 'facture',
        ]);

        Storage::disk('local')->put($invoice->pdf_path, '%PDF-1.4 mock');

        $response = $this->postJson("/api/invoices/{$invoice->id}/send-email", [
            'email' => 'recipient@example.com',
        ]);

        $response->assertOk();
        $response->assertJsonPath('message', 'Facture envoyée par email');
    }
}
