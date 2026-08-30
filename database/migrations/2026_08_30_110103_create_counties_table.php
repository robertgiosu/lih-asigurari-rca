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
        Schema::create('counties', function (Blueprint $table) {
            $table->id();

            // 'county_code' de la API: AR, CJ, B... Exact valoarea trimisa in address.county.
            $table->string('code', 2)->unique();

            $table->string('name');

            // Codul SIRUTA al judetului. Nu il trimitem la API, dar il pastram
            // ca sa nu pierdem informatie din nomenclator.
            $table->unsignedInteger('siruta')->nullable();

            // Ne spun cand a fost ultima sincronizare.
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('counties');
    }
};
