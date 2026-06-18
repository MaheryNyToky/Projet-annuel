<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('guests', 'passport_valid_from')) {
            Schema::table('guests', function (Blueprint $table) {
                $table->date('passport_valid_from')->nullable()->after('date_of_birth');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('guests', 'passport_valid_from')) {
            Schema::table('guests', function (Blueprint $table) {
                $table->dropColumn('passport_valid_from');
            });
        }
    }
};
