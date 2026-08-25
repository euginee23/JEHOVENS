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
        Schema::create('bookings', function (Blueprint $table) {
            $table->id();
            $table->string('reference')->unique();
            $table->foreignId('hall_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();

            $table->string('guest_name');
            $table->string('guest_phone');
            $table->string('guest_email');

            $table->date('booking_date');
            $table->unsignedTinyInteger('start_hour');
            $table->unsignedTinyInteger('end_hour');
            $table->unsignedTinyInteger('hours');

            $table->boolean('include_skirting')->default(false);
            // Whole pesos, captured at booking time so later price changes don't rewrite history.
            $table->unsignedInteger('rent_total');
            $table->unsignedInteger('skirting_total');
            $table->unsignedInteger('total');
            $table->unsignedInteger('downpayment');
            $table->unsignedInteger('balance');

            $table->string('status')->default('pending');
            $table->timestamps();

            $table->index(['hall_id', 'booking_date']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('bookings');
    }
};
