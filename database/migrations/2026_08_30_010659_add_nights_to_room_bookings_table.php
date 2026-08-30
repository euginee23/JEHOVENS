<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Rooms already store a real datetime span in `starts_at` / `ends_at`, so a stay
     * running over several days needs no new date columns. What the span cannot say is
     * *how* the stay was sold: zero nights means a day-use booking priced by the chosen
     * 6/12/24-hour rate block, and one or more nights means an overnight stay priced at
     * the room's 24-hour rate for each night.
     */
    public function up(): void
    {
        Schema::table('room_bookings', function (Blueprint $table) {
            $table->unsignedSmallInteger('nights')->default(0)->after('hours');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('room_bookings', function (Blueprint $table) {
            $table->dropColumn('nights');
        });
    }
};
