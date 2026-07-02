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
        Schema::table('bank_statement_lines', function (Blueprint $table) {
            $table->text('casos')->nullable()->after('etiqueta');
            $table->text('sugerencia')->nullable()->after('casos');
            $table->string('contacto')->nullable()->after('sugerencia');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('bank_statement_lines', function (Blueprint $table) {
            $table->dropColumn(['casos', 'sugerencia', 'contacto']);
        });
    }
};
