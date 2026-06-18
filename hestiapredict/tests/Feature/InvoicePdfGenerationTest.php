<?php

namespace Tests\Feature;

use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Reservation;
use App\Models\Room;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class InvoicePdfGenerationTest extends TestCase
{
    use RefreshDatabase;

    public function test_generate_pdf_persists_pdf_and_document_type(): void
    {
        $user = User::create([
            'name' => 'Admin Test',
            'email' => 'admin-test@example.com',
            'password' => 'password',
            'role' => 'admin',
            'is_blacklisted' => false,
        ]);

        $room = Room::create([
            'room_number' => '201',
            'type' => 'Chambre Double',
            'model' => 'Standard',
            'base_price_ariary' => 50000,
            'is_fixed_price' => false,
        ]);

        $reservation = Reservation::create([
            'user_id' => $user->id,
            'client_name' => 'Pdf Client',
            'client_phone' => '0340000004',
            'customer_phone' => '0340000004',
            'customer_email' => 'pdf@example.com',
            'booking_reference' => 'BR-' . uniqid(),
            'source' => 'direct',
            'check_in_date' => '2026-06-16',
            'check_out_date' => '2026-06-17',
            'status' => 'arrive',
            'payment_status' => 'unbilled',
            'extra_beds' => 0,
            'extra_mattresses' => 0,
        ]);

        $reservation->rooms()->attach($room->id, [
            'price_snapshot_ariary' => 50000,
        ]);

        $invoice = Invoice::create([
            'reservation_id' => $reservation->id,
            'invoice_number' => null,
            'total_amount_ariary' => 50000,
            'tax_amount_ariary' => 0,
            'discount_mode' => null,
            'discount_value' => null,
            'discount_amount_ariary' => 0,
            'deposit_amount_ariary' => 0,
            'pdf_path' => null,
            'finalized_at' => null,
            'status' => 'open',
            'document_type' => 'facture',
        ]);

        InvoiceItem::create([
            'invoice_id' => $invoice->id,
            'description' => 'Séjour chambre',
            'type' => 'room',
            'amount_ariary' => 50000,
            'quantity' => 1,
        ]);

        $response = $this->postJson("/api/invoices/{$invoice->id}/generate-pdf", [
            'pricing_mode' => 'fixed',
            'document_type' => 'proforma',
            'actor_role' => 'admin',
        ]);

        $response->assertOk();
        $response->assertJsonPath('invoice.document_type', 'proforma');

        $refreshed = Invoice::query()->findOrFail($invoice->id);
        $this->assertSame('proforma', $refreshed->document_type);
        $this->assertNotNull($refreshed->invoice_number);
        $this->assertNotNull($refreshed->pdf_path);
        $this->assertTrue(Storage::disk('local')->exists($refreshed->pdf_path));
    }
}
