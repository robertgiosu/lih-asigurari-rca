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
        Schema::create('audit_events', function (Blueprint $table) {
            $table->id();

            $table->uuid('correlation_id')->nullable()->index();
            $table->foreignId('user_id')->nullable()->index();
            $table->string('session_id')->nullable();

            // Ex: user.registered, quote.requested, policy.issued, pdf.downloaded
            $table->string('event')->index();

            // Obiectul asupra caruia s-a actionat, oricare ar fi modelul lui.
            $table->string('subject_type')->nullable();
            $table->unsignedBigInteger('subject_id')->nullable();

            $table->json('payload')->nullable();

            $table->string('ip', 45)->nullable();
            $table->text('user_agent')->nullable();

            $table->timestamp('created_at')->nullable()->index();

            $table->index(['subject_type', 'subject_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('audit_events');
    }
};
