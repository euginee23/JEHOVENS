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
        Schema::create('catering_orders', function (Blueprint $table) {
            $table->id();
            $table->string('reference')->unique();
            $table->foreignId('catering_package_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();

            $table->string('guest_name');
            $table->string('guest_phone');
            $table->string('guest_email');

            $table->date('event_date');
            $table->unsignedSmallInteger('guests');
            $table->boolean('include_skirting')->default(false);

            // Whole pesos, captured at order time so later price changes don't rewrite history.
            $table->unsignedInteger('price_per_head');
            $table->unsignedInteger('catering_total');
            $table->unsignedInteger('skirting_total');
            $table->unsignedInteger('total');
            $table->unsignedInteger('downpayment');
            $table->unsignedInteger('balance');

            $table->string('status')->default('pending');
            $table->timestamps();

            $table->index(['catering_package_id', 'event_date']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('catering_orders');
    }
};
