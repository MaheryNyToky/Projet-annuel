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
        if (Schema::hasTable('invoices')) {
            Schema::table('invoices', function (Blueprint $table) {
                if (! Schema::hasColumn('invoices', 'deposit_amount_ariary')) {
                    $table->integer('deposit_amount_ariary')->default(0);
                }

                if (! Schema::hasColumn('invoices', 'discount_mode')) {
                    $table->string('discount_mode')->nullable();
                }

                if (! Schema::hasColumn('invoices', 'discount_value')) {
                    $table->decimal('discount_value', 10, 2)->nullable();
                }

                if (! Schema::hasColumn('invoices', 'discount_amount_ariary')) {
                    $table->integer('discount_amount_ariary')->default(0);
                }

                if (! Schema::hasColumn('invoices', 'pdf_path')) {
                    $table->string('pdf_path')->nullable();
                }

                if (! Schema::hasColumn('invoices', 'finalized_at')) {
                    $table->timestamp('finalized_at')->nullable();
                }
            });

            return;
        }

        Schema::create('invoices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('reservation_id')->constrained()->onDelete('cascade');
            $table->string('invoice_number')->unique()->nullable();
            $table->integer('total_amount_ariary')->default(0);
            $table->integer('tax_amount_ariary')->default(0);
            $table->string('discount_mode')->nullable();
            $table->decimal('discount_value', 10, 2)->nullable();
            $table->integer('discount_amount_ariary')->default(0);
            $table->integer('deposit_amount_ariary')->default(0);
            $table->string('pdf_path')->nullable();
            $table->timestamp('finalized_at')->nullable();
            $table->string('status')->default('open'); // open, partial, paid, finalized
            $table->timestamps();

            $table->unique('reservation_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('invoices');
    }
};
