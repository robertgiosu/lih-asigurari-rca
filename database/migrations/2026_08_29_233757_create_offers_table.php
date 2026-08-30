<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Asta e o oferta concreta de la un asigurator. E tabelul pe care il afisezi utilizatorului sortat dupa premium_amount.
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('offers', function (Blueprint $table) {
            $table->id();

            $table->foreignId('provider_quote_id')->constrained()->cascadeOnDelete();

            // Duplicat fata de provider_quotes.provider, dar scuteste un join
            // la fiecare afisare si face inspectia cu sqlite3 lizibila.
            $table->string('provider')->index();

            // 'offerId' de la API. Cu el cerem polita si PDF-ul, deci are index.
            $table->unsignedBigInteger('api_offer_id')->index();
            $table->string('provider_offer_code')->nullable();

            // decimal, NU float: banii nu se tin niciodata in virgula mobila.
            $table->decimal('premium_amount', 12, 2);
            $table->decimal('premium_amount_net', 12, 2)->nullable();
            $table->string('currency', 3)->default('RON');

            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();

            $table->decimal('reference_rate', 12, 2)->nullable();
            $table->string('bonus_malus_class', 5)->nullable();

            $table->decimal('commission_value', 12, 2)->nullable();
            $table->decimal('commission_percent', 5, 2)->nullable();

            $table->json('direct_compensation')->nullable();
            $table->json('installments')->nullable();

            $table->text('green_card_exclusions')->nullable();
            $table->text('notes')->nullable();
            $table->date('offer_expiry_date')->nullable();

            // Link-uri catre documentele asiguratorului (pot fi foarte lungi).
            $table->text('pid')->nullable();
            $table->text('toc')->nullable();
            $table->text('payment_link')->nullable();

            // Se completeaza in Pasul 18, cand descarcam PDF-ul.
            $table->string('pdf_path')->nullable();

            // Oferta exact cum a venit de la API, fara nicio prelucrare.
            $table->json('raw');

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('offers');
    }
};
