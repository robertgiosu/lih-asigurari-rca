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
        Schema::create('localities', function (Blueprint $table) {
            $table->id();

            // Legatura catre counties.code. Fara cheie straina - vezi explicatia.
            $table->string('county_code', 2)->index();

            // 'name' de la API. Se trimite ca atare in address.city.
            $table->string('name')->index();

            // 'rang' de la API: 1=Bucuresti, 2=municipiu, 3=oras, 4=comuna, 5=sat.
            // Il folosim ca sa afisam intai localitatile mari in dropdown.
            $table->unsignedTinyInteger('rang')->nullable();

            // 'siruta' de la API. Se trimite ca address.cityCode.
            $table->unsignedInteger('siruta')->index();

            $table->timestamps();

            // Permite sincronizari repetate fara duplicate (upsert).
            $table->unique(['county_code', 'siruta']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('localities');
    }
};
