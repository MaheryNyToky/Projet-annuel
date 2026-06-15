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
    Schema::table('users', function (Blueprint $table) {
        $table->boolean('is_blacklisted')->default(false); // true si banni
        $table->text('blacklist_reason')->nullable(); // Pourquoi il est banni
    });
}

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        //
    }
};
