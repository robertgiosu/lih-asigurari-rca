<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * Cand rulezi php artisan migrate, Laravel citeste fisierele din migrations si le traduce in CREATE TABLE ... pentru baza
 * de date. Apoi noteaza in fisierul migrations ca fisierul asta a fost rulat ca sa nu-l mai ruleze a doua oara.
 *
 * De ce nu scriem SQL manual? Pentru ca migrarile sunt in Git, iar un coleg pe proiect cand face git pull && php artisan
 * migrate va avea exact aceeasi baza de date ca tine.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('api_logs', function (Blueprint $table) { // creeaza tabelul api_logs si primesti un obiect $table pe care adaugi coloanele
            $table->id(); // scurtatura pentru BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY

            // Leaga toate apelurile facute in cadrul aceleiasi actiuni din Web.
            $table->uuid('correlation_id')->index(); // coloana de tip UUID cu index pe coloana

            // Deliberat FARA ->constrained(): un log nu trebuie sa depinda de
            // existenta randurilor pe care le descrie. Vezi explicatia de mai jos.
            $table->foreignId('user_id')->nullable()->index(); // foreign key + poate fi si null
            $table->foreignId('quote_request_id')->nullable()->index();

            // Asiguratorul pentru care s-a facut apelul.
            // Null la apelurile care nu tin de un asigurator: /auth, /nomenclature.
            $table->string('provider')->nullable()->index();

            $table->string('method', 10);
            $table->text('url');

            $table->json('request_headers')->nullable();
            $table->json('request_body')->nullable();

            $table->unsignedSmallInteger('response_status')->nullable();
            $table->json('response_body')->nullable();

            // Cat a durat apelul. Null daca s-a rupt conexiunea inainte de raspuns.
            $table->unsignedInteger('duration_ms')->nullable();

            // Mesajul exceptiei, cand apelul nu a ajuns niciodata la un raspuns HTTP.
            $table->text('error')->nullable();

            $table->string('ip', 45)->nullable();

            $table->timestamp('created_at')->nullable()->index();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('api_logs');
    }
};
