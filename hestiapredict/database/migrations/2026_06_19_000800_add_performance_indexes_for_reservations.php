<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('reservations', function (Blueprint $table) {
            $table->index(['status', 'check_in_date', 'check_out_date'], 'reservations_status_dates_idx');
            $table->index(['check_in_date', 'check_out_date'], 'reservations_dates_idx');
            $table->index(['payment_status', 'status'], 'reservations_payment_status_idx');
            $table->index('created_at', 'reservations_created_at_idx');
        });

        Schema::table('booking_room', function (Blueprint $table) {
            $table->index(['reservation_id', 'room_id'], 'booking_room_reservation_room_idx');
            $table->index(['room_id', 'reservation_id'], 'booking_room_room_reservation_idx');
        });

        Schema::table('payments', function (Blueprint $table) {
            $table->index(['invoice_id', 'payment_context', 'created_at'], 'payments_invoice_context_created_idx');
        });
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->dropIndex('payments_invoice_context_created_idx');
        });

        Schema::table('booking_room', function (Blueprint $table) {
            $table->dropIndex('booking_room_reservation_room_idx');
            $table->dropIndex('booking_room_room_reservation_idx');
        });

        Schema::table('reservations', function (Blueprint $table) {
            $table->dropIndex('reservations_status_dates_idx');
            $table->dropIndex('reservations_dates_idx');
            $table->dropIndex('reservations_payment_status_idx');
            $table->dropIndex('reservations_created_at_idx');
        });
    }
};
