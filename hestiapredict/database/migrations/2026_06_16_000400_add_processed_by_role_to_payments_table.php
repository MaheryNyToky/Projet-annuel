<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('payments', 'processed_by_role')) {
            Schema::table('payments', function (Blueprint $table) {
                $table->string('processed_by_role')->nullable()->after('processed_by_name');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('payments', 'processed_by_role')) {
            Schema::table('payments', function (Blueprint $table) {
                $table->dropColumn('processed_by_role');
            });
        }
    }
};
