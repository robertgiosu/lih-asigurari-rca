<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// cererea de cotatie ce a completat utilizatorul in formular, salvat inainte de a apela vreun asigurator.
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('quote_requests', function (Blueprint $table) {
            $table->id();

            // Identificatorul folosit in URL-uri (/oferta/{uuid}), ca sa nu expunem id-uri secventiale.
            $table->uuid('uuid')->unique();

            // Aceeasi valoare ca in api_logs si audit_events pentru aceasta actiune.
            $table->uuid('correlation_id')->index();

            // Nullable: se pot cere oferte si fara cont.
            $table->foreignId('user_id')->nullable()->index();

            // INIMA TRASABILITATII: tot ce a completat utilizatorul, asa cum a trecut de validare.
            // Se scrie inainte de orice apel la asiguratori.
            $table->json('input');

            $table->string('status')->default('pending')->index(); // pending | completed | failed

            // Copii ale catorva campuri din 'input', ca lista din /istoric sa nu
            // desfaca JSON-ul pentru fiecare rand afisat.
            $table->string('license_plate')->nullable()->index();
            $table->string('policyholder_name')->nullable();
            $table->date('start_date')->nullable();

            $table->string('ip', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->string('session_id')->nullable();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('quote_requests');
    }
};
