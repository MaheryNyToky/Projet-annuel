<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('invoices', 'document_type')) {
            Schema::table('invoices', function (Blueprint $table) {
                $table->string('document_type')->default('facture')->after('status');
            });
        }

        if (Schema::hasColumn('invoices', 'document_type')) {
            DB::table('invoices')
                ->whereNull('document_type')
                ->update(['document_type' => 'facture']);
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('invoices', 'document_type')) {
            Schema::table('invoices', function (Blueprint $table) {
                $table->dropColumn('document_type');
            });
        }
    }
};
