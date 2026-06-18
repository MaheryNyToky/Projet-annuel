<?php

use App\Support\PhoneNumber;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('reservations')
            ->orderBy('id')
            ->chunkById(500, function ($reservations): void {
                foreach ($reservations as $reservation) {
                    $normalized = PhoneNumber::normalize($reservation->customer_phone ?? $reservation->client_phone ?? null);

                    DB::table('reservations')
                        ->where('id', $reservation->id)
                        ->update([
                            'client_phone' => $normalized,
                            'customer_phone' => $normalized,
                        ]);
                }
            });

        DB::table('guests')
            ->orderBy('id')
            ->chunkById(500, function ($guests): void {
                foreach ($guests as $guest) {
                    $normalized = PhoneNumber::normalize($guest->phone_number ?? null);

                    DB::table('guests')
                        ->where('id', $guest->id)
                        ->update([
                            'phone_number' => $normalized,
                        ]);
                }
            });
    }

    public function down(): void
    {
        // Irreversible: on ne peut pas restaurer les anciens formats de façon fiable.
    }
};
