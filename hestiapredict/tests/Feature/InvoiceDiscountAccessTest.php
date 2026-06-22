<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class InvoiceDiscountAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_receptionist_discount_request_is_ignored_without_blocking_pdf_generation(): void
    {
        $user = User::create([
            'name' => 'Reception Test',
            'email' => 'reception-discount@example.com',
            'password' => 'password',
            'role' => 'receptionist',
            'is_blacklisted' => false,
        ]);

        $reservationId = DB::table('reservations')->insertGetId([
            'user_id' => $user->id,
            'client_name' => 'Discount Client',
            'client_phone' => '0341000003',
            'customer_phone' => '0341000003',
            'customer_email' => 'discount@example.com',
            'booking_reference' => 'BR-' . uniqid(),
            'source' => 'direct',
            'check_in_date' => '2026-06-16',
            'check_out_date' => '2026-06-17',
            'status' => 'arrive',
            'payment_status' => 'unbilled',
            'extra_beds' => 0,
            'extra_mattresses' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $invoiceId = DB::table('invoices')->insertGetId([
            'reservation_id' => $reservationId,
            'invoice_number' => 'FACT-TEST-DISCOUNT',
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

        $response = $this->postJson("/api/invoices/{$invoiceId}/generate-pdf", [
            'discount_mode' => 'amount',
            'discount_value' => 100,
            'actor_role' => 'receptionist',
        ]);

        $response->assertOk();
        $response->assertJsonPath('invoice.discount_amount_ariary', 0);
    }
}
