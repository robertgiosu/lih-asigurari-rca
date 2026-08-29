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
