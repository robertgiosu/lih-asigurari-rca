<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Dupa ce un utilizatorul a ales o oferta a emis polita. Tabelul policies contine datele politei.
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('policies', function (Blueprint $table) {
            $table->id();

            $table->foreignId('offer_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->index();

            $table->string('provider')->index();

            $table->unsignedBigInteger('api_policy_id')->index();

            // 'number' e numeric in API, dar il tinem string: nu facem aritmetica
            // pe el si asa nu pierdem eventualele zerouri din fata.
            $table->string('series')->nullable();
            $table->string('number')->nullable();

            $table->decimal('premium_amount', 12, 2)->nullable();
            $table->string('currency', 3)->default('RON');
            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();

            // Obiectul 'payment' trimis la emitere - dovada a ce am declarat.
            $table->json('payment')->nullable();
            $table->json('installments')->nullable();

            $table->string('pdf_path')->nullable();
            $table->json('raw');

            $table->timestamps();

            $table->index(['series', 'number']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('policies');
    }
};
