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
        Schema::create('room_bookings', function (Blueprint $table) {
            $table->id();
            $table->string('reference')->unique();
            $table->foreignId('room_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();

            $table->string('guest_name');
            $table->string('guest_phone');
            $table->string('guest_email');

            // A stay can run past midnight, so the window is stored as datetimes.
            $table->dateTime('starts_at');
            $table->dateTime('ends_at');
            $table->unsignedSmallInteger('hours');

            $table->boolean('pay_in_full')->default(false);
            // Whole pesos, captured at booking time so later rate changes don't rewrite history.
            $table->unsignedInteger('total');
            $table->unsignedInteger('amount_paid');
            $table->unsignedInteger('balance');

            $table->string('status')->default('pending');
            $table->timestamps();

            $table->index(['room_id', 'starts_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('room_bookings');
    }
};
