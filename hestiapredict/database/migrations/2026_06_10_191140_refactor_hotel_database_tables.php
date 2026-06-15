<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. On nettoie les anciennes tables pour repartir sur du propre
        Schema::dropIfExists('booking_room');
        Schema::dropIfExists('reservations');
        Schema::dropIfExists('rooms');

        // 2. Table des Chambres (Fidèle aux specs du Kamoro Hotel)
        Schema::create('rooms', function (Blueprint $table) {
            $table->id();
            $table->string('room_number')->unique(); // Ex: "01", "103"
            $table->string('type'); // Double, Twin, Triple, Familiale
            $table->string('model'); // Standard, Supérieure
            $table->integer('base_price_ariary'); // Prix plancher officiel
            $table->timestamps();
        });

        // 3. Mise à jour ou recréation de la table Users pour inclure les rôles
        // Si la table existe déjà, on s'assure juste d'avoir les bonnes colonnes
        if (Schema::hasTable('users')) {
            Schema::table('users', function (Blueprint $table) {
                if (!Schema::hasColumn('users', 'role')) {
                    $table->string('role')->default('receptionist'); // admin ou receptionist
                }
            });
        }

        // 4. Table des Réservations (La commande principale)
        Schema::create('reservations', function (Blueprint $table) {
            $table->id();
            $table->string('client_name');
            $table->string('client_phone');
            // Traçabilité : quel réceptionniste a rentré la résa ?
            $table->foreignId('user_id')->nullable()->constrained('users')->onDelete('set null'); 
            $table->date('check_in_date');
            $table->date('check_out_date');
            // Statuts gérés par l'app Flutter : en_attente, arrive, annule, no_show
            $table->string('status')->default('en_attente');
            $table->string('source')->default('direct'); // direct ou booking
            $table->timestamps();
        });

        // 5. Table Pivot : Gère le fait qu'une réservation possède plusieurs chambres
        Schema::create('booking_room', function (Blueprint $table) {
            $table->id();
            $table->foreignId('reservation_id')->constrained()->onDelete('cascade');
            $table->foreignId('room_id')->constrained()->onDelete('cascade');
            // Sécurité : On stocke le prix auquel la chambre a été vendue à cet instant T 
            // (Utile car le prix fluctue avec les prédictions de l'IA !)
            $table->integer('price_snapshot_ariary'); 
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('booking_room');
        Schema::dropIfExists('reservations');
        Schema::dropIfExists('rooms');
    }
};