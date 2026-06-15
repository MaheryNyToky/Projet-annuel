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
        Schema::table('reservations', function (Blueprint $table) {
            if (! Schema::hasColumn('reservations', 'extra_beds')) {
                $table->integer('extra_beds')->default(0);
            }

            if (! Schema::hasColumn('reservations', 'extra_mattresses')) {
                $table->integer('extra_mattresses')->default(0);
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('reservations', function (Blueprint $table) {
            if (Schema::hasColumn('reservations', 'extra_beds')) {
                $table->dropColumn('extra_beds');
            }

            if (Schema::hasColumn('reservations', 'extra_mattresses')) {
                $table->dropColumn('extra_mattresses');
            }
        });
    }
};
