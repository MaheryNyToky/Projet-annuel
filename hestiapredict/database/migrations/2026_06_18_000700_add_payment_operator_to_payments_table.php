<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('payments', 'payment_operator')) {
            Schema::table('payments', function (Blueprint $table) {
                $table->string('payment_operator', 40)->nullable()->after('payment_method');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('payments', 'payment_operator')) {
            Schema::table('payments', function (Blueprint $table) {
                $table->dropColumn('payment_operator');
            });
        }
    }
};
