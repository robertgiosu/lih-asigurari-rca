<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/* cand utilizatorul apeleaza "calculeaza", exista un rand in quote_requests, dar aplicatia face apeluri separate catre
 * Omniasig, Groupama, Allianz. Fiecare apel poate merge sau poate crapa independent. provider_quotes inregistreaza rezultatul fiecarui apel
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('provider_quotes', function (Blueprint $table) {
            $table->id();

            // Aici cheia straina e reala: un raspuns fara cererea lui nu inseamna nimic.
            $table->foreignId('quote_request_id')->constrained()->cascadeOnDelete();

            $table->string('provider')->index();

            $table->string('status'); // ok | error
            $table->unsignedSmallInteger('http_status')->nullable();
            $table->unsignedInteger('duration_ms')->nullable();
            $table->text('error_message')->nullable();

            $table->unsignedTinyInteger('offers_count')->default(0);

            $table->timestamps();

            // Un singur rand per asigurator per cerere.
            $table->unique(['quote_request_id', 'provider']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('provider_quotes');
    }
};
