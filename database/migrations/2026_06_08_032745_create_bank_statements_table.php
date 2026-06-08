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
        Schema::create('bank_statements', function (Blueprint $table) {
            $table->id();
            $table->string('file_name');
            $table->string('file_path');
            $table->string('bank_type'); // AMEX TC, BANAMEX CH, BBVA CH, BBVA TC, BBVA US, SCOTIA CH
            $table->string('account_number')->nullable();
            $table->string('clabe')->nullable();
            $table->date('period_start')->nullable();
            $table->date('period_end')->nullable();
            $table->decimal('saldo_inicial', 15, 2)->default(0.00);
            $table->decimal('saldo_final', 15, 2)->default(0.00);
            $table->decimal('total_cargos', 15, 2)->default(0.00);
            $table->decimal('total_abonos', 15, 2)->default(0.00);
            $table->integer('count_cargos')->default(0);
            $table->integer('count_abonos')->default(0);
            $table->decimal('calculated_cargos', 15, 2)->default(0.00);
            $table->decimal('calculated_abonos', 15, 2)->default(0.00);
            $table->decimal('difference_cargos', 15, 2)->default(0.00);
            $table->decimal('difference_abonos', 15, 2)->default(0.00);
            $table->boolean('is_balanced')->default(false);
            $table->string('status')->default('pending'); // pending, processing, completed, failed
            $table->text('error_message')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('bank_statements');
    }
};
