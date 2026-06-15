<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('rooms', function (Blueprint $table) {
            if (!Schema::hasColumn('rooms', 'is_fixed_price')) {
                $table->boolean('is_fixed_price')->default(false)->after('base_price_ariary');
            }
        });

        foreach (['02', '25'] as $roomNumber) {
            DB::table('rooms')->updateOrInsert(
                ['room_number' => $roomNumber],
                [
                    'type' => 'Chambre Double',
                    'model' => 'Standard (état dégradé)',
                    'base_price_ariary' => 95000,
                    'is_fixed_price' => true,
                    'updated_at' => now(),
                    'created_at' => now(),
                ],
            );
        }
    }

    public function down(): void
    {
        DB::table('rooms')->whereIn('room_number', ['02', '25'])->delete();

        Schema::table('rooms', function (Blueprint $table) {
            if (Schema::hasColumn('rooms', 'is_fixed_price')) {
                $table->dropColumn('is_fixed_price');
            }
        });
    }
};
