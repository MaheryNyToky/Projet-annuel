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
    Schema::create('reservations', function (Blueprint $table) {
        $table->id();
        $table->foreignId('user_id')->constrained()->onDelete('cascade'); // Le client
        $table->foreignId('room_id')->constrained()->onDelete('cascade'); // La chambre
        $table->date('check_in_date');
        $table->date('check_out_date');
        // Les statuts : en_attente, arrive, no_show_a_appeler, no_show_resolu
        $table->string('status')->default('en_attente'); 
        $table->string('source')->default('direct'); // direct ou booking
        $table->timestamps();
    });
}

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('reservations');
    }
};
