<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Every webhook the gateway has delivered, so none is acted on twice.
 *
 * PayMongo retries until it gets a 2xx, and will happily deliver the same event more than
 * once. Without this a retry would confirm a booking a second time and send the guest a
 * second receipt. The unique `event_id` is what makes handling idempotent; the payload is
 * kept so a payment that went wrong can be traced without asking PayMongo.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('payment_webhook_events', function (Blueprint $table) {
            $table->id();
            $table->string('provider')->default('paymongo');
            $table->string('event_id')->unique();
            $table->string('event_type');
            $table->json('payload');
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();

            $table->index(['provider', 'event_type']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payment_webhook_events');
    }
};
