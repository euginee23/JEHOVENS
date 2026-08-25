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
        Schema::table('bookings', function (Blueprint $table) {
            // Set when the remaining balance is collected, usually on the day of the event.
            $table->timestamp('balance_settled_at')->nullable()->after('balance');

            // Free-text note the admin can leave against a booking.
            $table->text('admin_note')->nullable()->after('status');
        });

        // A hall must never take its bookings' payment history down with it. Deleting a
        // hall is blocked outright; the admin deactivates it instead.
        Schema::table('bookings', function (Blueprint $table) {
            $table->dropForeign(['hall_id']);
            $table->foreign('hall_id')->references('id')->on('halls')->restrictOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->dropForeign(['hall_id']);
            $table->foreign('hall_id')->references('id')->on('halls')->cascadeOnDelete();
        });

        Schema::table('bookings', function (Blueprint $table) {
            $table->dropColumn(['balance_settled_at', 'admin_note']);
        });
    }
};
