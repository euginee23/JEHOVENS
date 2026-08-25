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
        Schema::table('room_bookings', function (Blueprint $table) {
            $table->timestamp('balance_settled_at')->nullable()->after('balance');
            $table->text('admin_note')->nullable()->after('status');
        });

        // A room must never take its bookings' payment history down with it.
        Schema::table('room_bookings', function (Blueprint $table) {
            $table->dropForeign(['room_id']);
            $table->foreign('room_id')->references('id')->on('rooms')->restrictOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('room_bookings', function (Blueprint $table) {
            $table->dropForeign(['room_id']);
            $table->foreign('room_id')->references('id')->on('rooms')->cascadeOnDelete();
        });

        Schema::table('room_bookings', function (Blueprint $table) {
            $table->dropColumn(['balance_settled_at', 'admin_note']);
        });
    }
};
