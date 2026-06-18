<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('payments', 'payment_context')) {
            Schema::table('payments', function (Blueprint $table) {
                $table->string('payment_context')->default('payment')->after('payment_method');
            });
        }

        if (Schema::hasColumn('payments', 'payment_context')) {
            DB::table('payments')
                ->whereNull('payment_context')
                ->update(['payment_context' => 'payment']);
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('payments', 'payment_context')) {
            Schema::table('payments', function (Blueprint $table) {
                $table->dropColumn('payment_context');
            });
        }
    }
};
