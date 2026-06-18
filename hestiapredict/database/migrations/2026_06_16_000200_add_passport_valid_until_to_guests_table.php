<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('guests', function (Blueprint $table) {
            if (! Schema::hasColumn('guests', 'passport_valid_until')) {
                $table->date('passport_valid_until')->nullable()->after('date_of_birth');
            }
        });
    }

    public function down(): void
    {
        Schema::table('guests', function (Blueprint $table) {
            if (Schema::hasColumn('guests', 'passport_valid_until')) {
                $table->dropColumn('passport_valid_until');
            }
        });
    }
};
