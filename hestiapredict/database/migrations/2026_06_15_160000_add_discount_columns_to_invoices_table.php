<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            if (! Schema::hasColumn('invoices', 'discount_mode')) {
                $table->string('discount_mode')->nullable()->after('tax_amount_ariary');
            }

            if (! Schema::hasColumn('invoices', 'discount_value')) {
                $table->decimal('discount_value', 10, 2)->nullable()->after('discount_mode');
            }

            if (! Schema::hasColumn('invoices', 'discount_amount_ariary')) {
                $table->integer('discount_amount_ariary')->default(0)->after('discount_value');
            }
        });
    }

    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            if (Schema::hasColumn('invoices', 'discount_amount_ariary')) {
                $table->dropColumn('discount_amount_ariary');
            }

            if (Schema::hasColumn('invoices', 'discount_value')) {
                $table->dropColumn('discount_value');
            }

            if (Schema::hasColumn('invoices', 'discount_mode')) {
                $table->dropColumn('discount_mode');
            }
        });
    }
};
