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
        Schema::create('profiles', function (Blueprint $table) {
            $table->id();

            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();

            $table->string('last_name')->nullable();
            $table->string('first_name')->nullable();

            // Criptate: textul rezultat e mult mai lung decat originalul, deci 'text', nu 'string'.
            $table->text('tax_id')->nullable();
              $table->text('id_number')->nullable();

              $table->date('birthdate')->nullable();
              $table->string('gender', 1)->nullable();
              $table->string('mobile_number', 20)->nullable();

              $table->string('id_type', 20)->nullable();
              $table->string('id_issue_authority')->nullable();
              $table->date('id_issue_date')->nullable();
              $table->date('driving_license_issue_date')->nullable();

              $table->string('county', 2)->nullable();
              $table->string('city')->nullable();
              $table->string('street')->nullable();
              $table->string('house_number', 20)->nullable();
              $table->string('building', 20)->nullable();
              $table->string('staircase', 20)->nullable();
              $table->string('apartment', 20)->nullable();
              $table->string('floor', 10)->nullable();
              $table->string('postcode', 10)->nullable();

    $table->boolean('has_disability')->default(false);
              $table->boolean('is_retired')->default(false);

              $table->timestamps();
          });
    }

    public function down(): void
    {
        Schema::dropIfExists('profiles');
    }
};
