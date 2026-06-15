<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('reservations', function (Blueprint $blueprint) {
            $blueprint->string('customer_phone')->nullable();
            $blueprint->string('customer_email')->nullable();
            $blueprint->string('booking_reference')->unique()->nullable();
            $blueprint->boolean('is_booking_com')->default(false);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('reservations', function (Blueprint $blueprint) {
            $blueprint->dropColumn(['customer_phone', 'customer_email', 'booking_reference', 'is_booking_com']);
        });
    }
};
