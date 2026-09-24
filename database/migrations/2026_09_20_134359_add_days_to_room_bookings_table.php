<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Give room bookings the same day count halls and catering already carry.
 *
 * A stay used to be described entirely by `starts_at`, `ends_at` and `nights`, which was
 * enough while every booking ran over consecutive days. Once a guest can pick days that
 * are not consecutive, the span stops implying how many days were actually sold, so the
 * count has to be stored like it is on the other two tables.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('room_bookings', function (Blueprint $table) {
            $table->unsignedSmallInteger('days')->default(1)->after('nights');
        });

        // Every existing stay is consecutive, so its nights are its days — bar a day use,
        // which has no nights and occupies exactly one day.
        DB::table('room_bookings')->update([
            'days' => DB::raw('CASE WHEN nights > 0 THEN nights ELSE 1 END'),
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('room_bookings', function (Blueprint $table) {
            $table->dropColumn('days');
        });
    }
};
